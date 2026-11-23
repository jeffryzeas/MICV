#!/usr/bin/env python3
"""TikTokLive 7.0.1: eventos públicos hacia stdout NDJSON, sin credenciales de usuario."""
import argparse
import hashlib
import asyncio
import json
import re
import signal
import sys
import time
import uuid
from html.parser import HTMLParser
from urllib.parse import urlparse
from datetime import datetime, timezone
from email.utils import parsedate_to_datetime


def ahora_ms():
    return time.time_ns() // 1_000_000


def enviar(tipo, **datos):
    print(json.dumps({"v": 1, "tipo": tipo, "capturado_ms": ahora_ms(), **datos}, ensure_ascii=True), flush=True)


def comentario(evento, sala):
    usuario = getattr(evento, "user", None)
    comun = getattr(evento, "common", None)
    ts = int(getattr(comun, "create_time", 0) or 0)
    texto = str(getattr(evento, "comment", None) or getattr(evento, "content", "") or "")
    avatar = getattr(usuario, "avatar_thumb", None)
    urls = getattr(avatar, "url_list", None) or []
    unique_id = str(getattr(usuario, "unique_id", "") or getattr(usuario, "display_id", "") or "")
    uid = str(getattr(usuario, "id_str", "") or getattr(usuario, "id", 0) or getattr(usuario, "user_id", "") or "")
    return dict(sala=str(sala), id=str(getattr(comun, "msg_id", 0) or uuid.uuid4()),
                autor=unique_id[:100] or uid[:80] or "sin_identificador",
                nombre=str(getattr(usuario, "nickname", "") or "")[:150],
                texto=texto[:4000],
                ocurrido_ms=(ts * 1000 if ts and ts < 10**12 else ts) or ahora_ms(),
                publico={"id_evento_fuente": "tiktok" if getattr(comun, "msg_id", 0) else "generado_por_adaptador", "fecha_declarada_original": str(ts) if ts else None, "fecha_evento_origen": "plataforma" if ts else "reloj_capturador", "id_usuario": uid[:80] or None, "usuario": unique_id[:100] or None,
                         "avatar": next((str(u) for u in urls if isinstance(u, str) and len(u)<=2048), None),
                         "bio": str(getattr(usuario, "bio_description", "") or "")[:500] or None,
                         "idioma_comentario": str(getattr(evento, "content_language", "") or "")[:20] or None,
                         "texto_recortado": len(texto)>4000, "sha256_texto_original": hashlib.sha256(texto.encode("utf-8")).hexdigest(),
                         "fuente": "TikTokLive 7.0.1", "adaptador": "MiCV 2.0"})



def perfil_publico(owner):
    if not isinstance(owner, dict):
        return {}
    avatar = owner.get("avatar_thumb") or owner.get("avatar_medium") or owner.get("avatarThumb")
    if isinstance(avatar, dict):
        avatar = next(iter(avatar.get("url_list") or []), None)
    region = str(owner.get("region") or owner.get("country") or "").upper()
    return {"id_usuario": str(owner.get("id_str") or owner.get("id") or owner.get("user_id") or "")[:80],
            "usuario": str(owner.get("display_id") or owner.get("unique_id") or owner.get("uniqueId") or "")[:100],
            "nombre": str(owner.get("nickname") or "")[:150],
            "bio": str(owner.get("signature") or owner.get("bio_description") or "")[:500],
            "avatar": avatar if isinstance(avatar, str) and len(avatar) <= 2048 else None,
            "region": region if re.fullmatch(r"[A-Z]{2}", region) else None}


def video_publico(info):
    """Solo HLS entregado en la sala pública, sin consultar perfiles privados."""
    stream = info.get("stream_url") or {}
    if not isinstance(stream, dict):
        return None
    candidatos = [stream.get("hls_pull_url")]
    mapa = stream.get("hls_pull_url_map") or {}
    if isinstance(mapa, dict):
        candidatos.extend(mapa.values())
    try:
        data = json.loads(stream["live_core_sdk_data"]["pull_data"]["stream_data"])["data"]
        for calidad in ("origin", "sd", "hd", "ld"):
            candidatos.append(data.get(calidad, {}).get("main", {}).get("hls"))
    except (KeyError, ValueError, TypeError, AttributeError):
        pass
    for url in candidatos:
        if not isinstance(url, str) or len(url) > 2048:
            continue
        try:
            parsed = urlparse(url)
            host = (parsed.hostname or "").lower()
            if parsed.scheme == "https" and not parsed.username and not parsed.password and parsed.port in (None, 443) and any(host.endswith("." + dominio) for dominio in ("tiktokcdn.com", "tiktokcdn-us.com", "tiktokcdn-eu.com")):
                return url
        except ValueError:
            pass
    return None


class SalaNoDeterminada(Exception):
    """No existe una asociación pública suficiente para localizar la sala."""


class InformacionSalaNoDisponible(Exception):
    """Respuesta sin metadatos suficientes; no equivale a una restricción confirmada."""


class InfoSalaPublica:
    """Mismo endpoint del SDK, separando prompts de una respuesta vacía."""
    def __init__(self, web):
        self.web = web

    async def __call__(self, room_id=None, unique_id=None):
        from TikTokLive.client.errors import AgeRestrictedError, UserOfflineError
        from TikTokLive.client.web.web_settings import WebDefaults
        if room_id and unique_id:
            raise ValueError("Indique una sola referencia de sala")
        if unique_id:
            if not re.fullmatch(r"[a-zA-Z0-9_.]{2,24}", unique_id):
                raise ValueError("Usuario inválido")
            ruta, params = "/room/info_by_user/", {"unique_id": unique_id}
        else:
            sala = str(room_id or self.web.params.get("room_id") or "")
            if not re.fullmatch(r"[1-9][0-9]{4,39}", sala):
                raise ValueError("Sala inválida")
            ruta, params = "/room/info/", {"room_id": sala}
        respuesta = await asyncio.wait_for(self.web.get(url=WebDefaults.tiktok_webcast_url + ruta, extra_params=params), 15)
        respuesta.raise_for_status()
        data = respuesta.json()
        if not isinstance(data, dict):
            raise InformacionSalaNoDisponible()
        info = data.get("data")
        if isinstance(info, dict) and "prompts" in info:
            raise AgeRestrictedError()
        if data.get("status_code", 0) != 0 or not isinstance(info, dict) or len(info) <= 1:
            raise InformacionSalaNoDisponible()
        if info.get("status") == 4:
            raise UserOfflineError()
        return info


class EstadoPublicoHTML(HTMLParser):
    """Lee únicamente JSON de estado; no ejecuta scripts ni sigue enlaces."""
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.activo = False
        self.partes = []
        self.estados = []

    def handle_starttag(self, tag, attrs):
        if tag == "script" and dict(attrs).get("id") in ("SIGI_STATE", "__UNIVERSAL_DATA_FOR_REHYDRATION__"):
            self.activo = True
            self.partes = []

    def handle_data(self, data):
        if self.activo:
            self.partes.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.activo:
            self.activo = False
            try:
                self.estados.append(json.loads("".join(self.partes)))
            except (ValueError, TypeError):
                pass


def usuario_de_perfil_html(html, usuario):
    parser = EstadoPublicoHTML()
    parser.feed(html)
    for estado in parser.estados:
        if not isinstance(estado, dict):
            continue
        scope = estado.get("__DEFAULT_SCOPE__") or {}
        detalle = scope.get("webapp.user-detail") or {}
        candidatos = [(detalle.get("userInfo") or {}).get("user")]
        modulo = estado.get("UserModule") or {}
        users = modulo.get("users") or {}
        if isinstance(users, dict):
            candidatos.extend(users.values())
        for perfil in candidatos:
            if isinstance(perfil, dict) and str(perfil.get("uniqueId") or "").lower() == usuario.lower():
                return perfil
    return None


def sala_de_perfil(perfil, usuario):
    if not isinstance(perfil, dict):
        return None
    alias = str(perfil.get("uniqueId") or perfil.get("unique_id") or "")
    if alias and alias.lower() != usuario.lower():
        return None
    valor = perfil.get("roomId")
    # No convertir floats: perdería precisión en IDs de 19 dígitos.
    if type(valor) not in (str, int):
        return None
    sala = str(valor)
    return sala if re.fullmatch(r"[1-9][0-9]{4,39}", sala) else None


async def senal_publica(client, info, sala):
    """La consulta secundaria pública puede entregar HLS para invitados."""
    url = video_publico(info)
    if url:
        return url
    respuesta = await asyncio.wait_for(client.web.get(
        url="https://www.tiktok.com/api/live/detail/",
        extra_params={"aid": "1988", "roomID": str(sala)}), 15)
    respuesta.raise_for_status()
    data = respuesta.json()
    if data.get("statusCode", 0) != 0:
        return None
    detalle = data.get("LiveRoomInfo") or {}
    if not isinstance(detalle, dict) or detalle.get("status") != 2 or "prompts" in detalle:
        return None
    return video_publico({"stream_url": {"hls_pull_url": detalle.get("liveUrl")}})


def espera_indicada(respuesta):
    """Respeta la espera del proveedor sin almacenar cabeceras ni URLs."""
    espera = 300
    headers = getattr(respuesta, "headers", {})
    retry = str(headers.get("Retry-After", ""))
    try:
        segundos = int(retry) if retry.isdigit() else (parsedate_to_datetime(retry) - datetime.now(timezone.utc)).total_seconds()
        espera = max(espera, int(segundos))
    except (ValueError, TypeError, OverflowError):
        pass
    try:
        espera = max(espera, int(headers.get("RateLimit-Reset", 0)) - int(time.time()))
    except (ValueError, TypeError, OverflowError):
        pass
    return min(86400, espera)


def clasificar_error(exc):
    """Solo códigos, clase y estado HTTP: nunca str(exc), tokens o cuerpos remotos."""
    from TikTokLive.client.errors import AgeRestrictedError, UserOfflineError, UserNotFoundError, SignAPIError, SignatureRateLimitError, WebcastBlockedError
    import httpx

    clase = type(exc).__name__
    clase = clase if re.fullmatch(r"[A-Za-z0-9_]{1,80}", clase) else "Exception"
    respuesta = getattr(exc, "response", None)
    http = getattr(respuesta, "status_code", None)
    http = http if isinstance(http, int) and 100 <= http <= 599 else None
    estado, codigo, espera = "error", "conexion_desconocida", None
    detalle = "No se pudo conectar con TikTok. El monitoreo sigue activado y se reintentará automáticamente."
    if isinstance(exc, SalaNoDeterminada):
        estado, codigo = "no_determinado", "sala_no_publicada"
        detalle = "TikTok no publicó una sala activa asociada a este @usuario. Puede estar como invitado sin que esa relación sea pública. El monitoreo sigue activado y volverá a comprobarlo."
    elif isinstance(exc, InformacionSalaNoDisponible):
        codigo, espera = "metadatos_no_disponibles", 300
        detalle = "Se localizó una sala, pero TikTok no entregó metadatos públicos verificables. El monitoreo sigue activado y volverá a comprobarlo; no se clasifica como una restricción confirmada."
    elif isinstance(exc, AgeRestrictedError):
        estado, codigo, espera = "acceso_pendiente", "acceso_live_pendiente", 300
        detalle = "TikTok no habilitó el acceso a este LIVE para el capturador. Esto no indica que el perfil sea privado. El monitoreo sigue activado y volverá a comprobarlo."
    elif isinstance(exc, UserOfflineError):
        estado, codigo = "sin_live", "sin_live"
        detalle = "No se detectó una sala pública activa. Esto no descarta que participe como invitado en otra sala. El monitoreo sigue activado y volverá a comprobarlo."
    elif isinstance(exc, SignatureRateLimitError) or http == 429:
        estado, codigo, espera = "limitado", "limite_peticiones", espera_indicada(respuesta)
        detalle = "TikTok o el proveedor de firma limitaron las peticiones. El monitoreo sigue activado y respetará la espera indicada."
    elif isinstance(exc, WebcastBlockedError) or http in (401, 403):
        codigo, espera = "solicitud_rechazada", 300
        detalle = "TikTok o el servicio de firma rechazaron la conexión desde el VPS. Se reintentará sin desactivar el monitoreo. Revise el diagnóstico del servicio."
    elif isinstance(exc, UserNotFoundError):
        codigo = "cuenta_no_disponible"
        detalle = "TikTok no devolvió una cuenta de LIVE disponible. Verifique el @usuario; el monitoreo sigue activado y volverá a comprobarlo."
    elif isinstance(exc, (asyncio.TimeoutError, httpx.TimeoutException)):
        codigo = "tiempo_agotado"
        detalle = "TikTok no respondió dentro del tiempo permitido. El monitoreo sigue activado y volverá a conectar automáticamente."
    elif isinstance(exc, (httpx.ConnectError, ConnectionError, OSError)):
        codigo = "conexion_red"
        detalle = "No se pudo establecer la conexión de red con TikTok o la firma. Revise DNS, certificados y salida HTTPS del VPS; el monitoreo sigue activado."
    elif isinstance(exc, SignAPIError):
        codigo, espera = "servicio_firma", 300
        detalle = "Falló el servicio externo de firma de TikTokLive. El monitoreo sigue activado y volverá a comprobarlo; revise el diagnóstico del servicio."
    elif isinstance(exc, httpx.HTTPStatusError):
        codigo = "respuesta_http"
        detalle = "TikTok devolvió una respuesta HTTP no disponible. El monitoreo sigue activado y se reintentará automáticamente."
    elif isinstance(exc, (json.JSONDecodeError, KeyError, ValueError)):
        codigo = "respuesta_incompatible"
        detalle = "TikTok devolvió un formato que el capturador no pudo interpretar. El monitoreo sigue activado; revise la compatibilidad de TikTokLive."
    datos = dict(estado=estado, codigo=codigo, clase=clase, http=http, detalle=detalle)
    if espera is not None:
        datos["espera_segundos"] = espera
    return datos


def informar_error(exc):
    datos = clasificar_error(exc)
    enviar("diagnostico", **{k: datos[k] for k in ("codigo", "clase", "http")})
    codigo = datos.pop("codigo")
    clase = datos.pop("clase")
    http = datos.pop("http")
    if datos["estado"] not in ("sin_live", "restringido"):
        datos["detalle"] += f" Código: {codigo} / {clase}" + (f" / HTTP {http}." if http else ".")
    enviar("estado", **datos)


async def conectar_con_limite(client, listo, segundos=60, **opciones):
    """Limita el establecimiento de conexión, sin cortar un LIVE ya conectado."""
    conexion = asyncio.create_task(client.connect(fetch_room_info=True, process_connect_events=True, **opciones))
    inicio = asyncio.create_task(listo.wait())
    try:
        terminadas, _ = await asyncio.wait({conexion, inicio}, timeout=segundos, return_when=asyncio.FIRST_COMPLETED)
        if not terminadas:
            raise asyncio.TimeoutError()
        await conexion
    finally:
        for tarea in (conexion, inicio):
            if not tarea.done():
                tarea.cancel()
        await asyncio.gather(conexion, inicio, return_exceptions=True)


async def conectar_con_comprobacion(client, listo, usuario):
    from TikTokLive.client.errors import UserOfflineError, UserNotFoundError
    from TikTokLive.client.web.routes.fetch_room_id_live_html import FailedParseRoomIdError
    from TikTokLive.client.web.routes.fetch_room_id_api import FetchRoomIdAPIRoute
    recuperables = (UserOfflineError, UserNotFoundError, FailedParseRoomIdError)
    client._micv_fuente_sala = "sdk"
    client._micv_perfil_objetivo = None
    try:
        await conectar_con_limite(client, listo)
        return
    except recuperables:
        if listo.is_set():
            raise
    enviar("diagnostico", codigo="verificando_sala", clase="ConsultaPublica", http=None)
    intentadas = set()

    async def intentar(perfil, fuente):
        sala = sala_de_perfil(perfil, usuario)
        if not sala or sala in intentadas:
            return False
        intentadas.add(sala)
        client._micv_fuente_sala = fuente
        client._micv_perfil_objetivo = perfil_publico(perfil)
        try:
            # Se conserva la comprobación de LIVE y de acceso del SDK.
            await conectar_con_limite(client, listo, room_id=int(sala), fetch_live_check=True)
            return True
        except UserOfflineError:
            if listo.is_set():
                raise
            return False

    try:
        data = await asyncio.wait_for(FetchRoomIdAPIRoute.fetch_user_room_data(web=client.web, unique_id=usuario), 15)
        perfil = (data.get("data") or {}).get("user")
        if isinstance(perfil, dict):
            alias = str(perfil.get("uniqueId") or usuario)
            if alias.lower() == usuario.lower():
                enviar("perfil", perfil=perfil_publico(perfil))
                if await intentar(perfil, "api_usuario"):
                    return
    except (UserNotFoundError, FailedParseRoomIdError, KeyError, json.JSONDecodeError):
        if listo.is_set():
            raise
    # Solo el perfil del usuario solicitado: nunca recomendaciones u otras cuentas.
    respuesta = await asyncio.wait_for(client.web.get(url=f"https://www.tiktok.com/@{usuario}", base_params=False), 15)
    respuesta.raise_for_status()
    if len(respuesta.content) > 2 * 1024 * 1024:
        raise ValueError("Estado público demasiado grande")
    perfil = usuario_de_perfil_html(respuesta.text, usuario)
    if perfil:
        enviar("perfil", perfil=perfil_publico(perfil))
        if await intentar(perfil, "perfil_publico"):
            return
    raise SalaNoDeterminada()


async def ejecutar(usuario):
    try:
        from TikTokLive import TikTokLiveClient
        from TikTokLive.events import ConnectEvent, CommentEvent, DisconnectEvent, LiveEndEvent, RoomUserSeqEvent
    except ImportError:
        enviar("estado", estado="error", detalle="Falta TikTokLive. Ejecute el instalador del módulo; el monitoreo permanece activado.")
        return
    client = None
    autorizado = False
    finalizado = False
    ultimo_conteo = 0.0
    listo = asyncio.Event()
    tarea = None
    tarea_video = None
    tarea_senal = None
    try:
        client = TikTokLiveClient(unique_id=usuario)
        if getattr(client, "web", None):
            client.web.fetch_room_info = InfoSalaPublica(client.web)

        @client.on(ConnectEvent)
        async def inicio(event):
            nonlocal autorizado, finalizado, tarea_senal
            info = client.room_info or {}
            perfil = info.get("owner") or {}
            listo.set()
            # La privacidad del perfil no determina la visibilidad del LIVE.
            # Solo se aceptan eventos que TikTok entregue a esta conexión sin login.
            autorizado = True
            titular = perfil_publico(perfil)
            objetivo = getattr(client, "_micv_perfil_objetivo", None)
            if titular.get("usuario", "").lower() == usuario.lower():
                objetivo = titular
            enviar("inicio", sala=str(event.room_id), perfil_objetivo=objetivo or {},
                   deteccion={"cuenta_consultada": usuario, "fuente_sala": getattr(client, "_micv_fuente_sala", "sdk")}, titulo=str(info.get("title") or "Live público")[:255],
                   perfil=perfil_publico(perfil),
                   video_hls=video_publico(info))
            if not video_publico(info):
                async def completar_senal():
                    try:
                        url = await senal_publica(client, info, event.room_id)
                        if autorizado and not finalizado and str(client.room_id) == str(event.room_id) and url:
                            enviar("video", sala=str(event.room_id), video_hls=url)
                    except Exception as exc:
                        d = clasificar_error(exc)
                        enviar("diagnostico", codigo="senal_publica", clase=d["clase"], http=d["http"])
                tarea_senal = asyncio.create_task(completar_senal())

        @client.on(CommentEvent)
        async def chat(event):
            if autorizado and not finalizado:
                enviar("comentario", **comentario(event, client.room_id))

        @client.on(LiveEndEvent)
        async def fin(_):
            nonlocal finalizado
            if autorizado:
                finalizado = True
                enviar("fin", sala=str(client.room_id))
                asyncio.create_task(client.disconnect())

        @client.on(RoomUserSeqEvent)
        async def audiencia(event):
            nonlocal ultimo_conteo
            cantidad = max(0, int(getattr(event, "total", 0) or 0))
            if autorizado and not finalizado and time.monotonic() - ultimo_conteo >= 5:
                ultimo_conteo = time.monotonic()
                enviar("espectadores", sala=str(client.room_id), cantidad=cantidad)

        @client.on(DisconnectEvent)
        async def salida(_):
            if autorizado and not finalizado:
                enviar("estado", estado="error", detalle="La conexión con el LIVE se interrumpió. El monitoreo sigue activado y volverá a conectar automáticamente.")

        async def latidos():
            while True:
                enviar("latido")
                await asyncio.sleep(10)

        async def refrescar_video():
            while True:
                await asyncio.sleep(300)
                if not autorizado or finalizado:
                    continue
                try:
                    info = await asyncio.wait_for(client.web.fetch_room_info(), 15)
                    enviar("video", sala=str(client.room_id), video_hls=await senal_publica(client, info, client.room_id))
                    perfil = perfil_publico(info.get("owner") or {})
                    if perfil.get("usuario", "").lower() == usuario.lower():
                        enviar("perfil", perfil=perfil)
                except Exception as exc:
                    datos = clasificar_error(exc)
                    enviar("diagnostico", codigo="video_metadatos", clase=datos["clase"], http=datos["http"])
                    if datos['estado'] == 'acceso_pendiente':
                        enviar("video", sala=str(client.room_id), video_hls=None)
                    await asyncio.sleep(max(0, datos.get("espera_segundos", 300) - 300))

        async def parar():
            nonlocal finalizado
            finalizado = True
            await client.disconnect()

        loop = asyncio.get_running_loop()
        for sig in (signal.SIGTERM, signal.SIGINT):
            loop.add_signal_handler(sig, lambda: asyncio.create_task(parar()))
        tarea = asyncio.create_task(latidos())
        tarea_video = asyncio.create_task(refrescar_video())
        enviar("estado", estado="conectando", detalle="Monitoreo automático activado. Comprobando si hay un LIVE público disponible.")
        await conectar_con_comprobacion(client, listo, usuario)
    except Exception as exc:
        finalizado = True
        informar_error(exc)
    finally:
        if tarea_senal:
            tarea_senal.cancel()
            await asyncio.gather(tarea_senal, return_exceptions=True)
        if tarea:
            tarea.cancel()
            await asyncio.gather(tarea, return_exceptions=True)
        if tarea_video:
            tarea_video.cancel()
            await asyncio.gather(tarea_video, return_exceptions=True)
        if client:
            try:
                await client.disconnect()
                if getattr(client, "web", None):
                    await client.web.close()
            except Exception:
                pass


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("usuario")
    args = parser.parse_args()
    if not re.fullmatch(r"[a-z0-9_.]{2,24}", args.usuario):
        sys.exit(2)
    asyncio.run(ejecutar(args.usuario))
