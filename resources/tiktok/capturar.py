#!/usr/bin/env python3
"""TikTokLive 7.0.1: eventos públicos hacia stdout NDJSON, sin credenciales de usuario."""
import argparse
import asyncio
import json
import re
import signal
import sys
import time
import uuid
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
    return dict(sala=str(sala), id=str(getattr(comun, "msg_id", 0) or uuid.uuid4()),
                autor=str(getattr(usuario, "unique_id", "") or getattr(usuario, "user_id", "") or "sin_identificador")[:100],
                nombre=str(getattr(usuario, "nickname", "") or "")[:150],
                texto=str(getattr(evento, "comment", None) or getattr(evento, "content", "") or "")[:4000],
                ocurrido_ms=(ts * 1000 if ts and ts < 10**12 else ts) or ahora_ms())


def restringido(perfil):
    valores = [perfil.get(k) for k in ("secret", "private_account", "is_private")]
    return any(v is True or str(v).lower() in {"1", "true", "private"} for v in valores)


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
    if isinstance(exc, AgeRestrictedError):
        estado, codigo = "restringido", "acceso_restringido"
        detalle = "TikTok indica una restricción de acceso. La captura se pausó; no se intentará eludirla."
    elif isinstance(exc, UserOfflineError):
        estado, codigo = "sin_live", "sin_live"
        detalle = "Esperando un LIVE público. El monitoreo sigue activado y comenzará a capturar cuando lo detecte."
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


async def conectar_con_limite(client, listo, segundos=60):
    """Limita el establecimiento de conexión, sin cortar un LIVE ya conectado."""
    conexion = asyncio.create_task(client.connect(fetch_room_info=True, process_connect_events=False))
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
    try:
        client = TikTokLiveClient(unique_id=usuario)

        @client.on(ConnectEvent)
        async def inicio(event):
            nonlocal autorizado, finalizado
            info = client.room_info or {}
            perfil = info.get("owner") or {}
            listo.set()
            if restringido(perfil):
                finalizado = True
                enviar("estado", estado="restringido", detalle="La cuenta o la transmisión restringen el acceso. La captura se pausó; no se intentará eludir la restricción.")
                asyncio.create_task(client.disconnect())
                return
            autorizado = True
            enviar("inicio", sala=str(event.room_id), titulo=str(info.get("title") or "Live público")[:255],
                   perfil={"nombre": str(perfil.get("nickname") or "")[:150], "bio": str(perfil.get("signature") or "")[:500]})

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

        async def parar():
            nonlocal finalizado
            finalizado = True
            await client.disconnect()

        loop = asyncio.get_running_loop()
        for sig in (signal.SIGTERM, signal.SIGINT):
            loop.add_signal_handler(sig, lambda: asyncio.create_task(parar()))
        tarea = asyncio.create_task(latidos())
        enviar("estado", estado="conectando", detalle="Monitoreo automático activado. Comprobando si hay un LIVE público disponible.")
        await conectar_con_limite(client, listo)
    except Exception as exc:
        finalizado = True
        informar_error(exc)
    finally:
        if tarea:
            tarea.cancel()
            await asyncio.gather(tarea, return_exceptions=True)
        if client:
            try:
                await client.disconnect(close_client=True)
            except Exception:
                pass


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("usuario")
    args = parser.parse_args()
    if not re.fullmatch(r"[a-z0-9_.]{2,24}", args.usuario):
        sys.exit(2)
    asyncio.run(ejecutar(args.usuario))
