#!/usr/bin/env python3
"""TikTokLive 7.0.1: eventos públicos hacia stdout NDJSON, sin cookies ni credenciales."""
import argparse
import asyncio
import json
import signal
import sys
import time
import uuid


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


async def ejecutar(usuario):
    try:
        from TikTokLive import TikTokLiveClient
        from TikTokLive.events import ConnectEvent, CommentEvent, DisconnectEvent, LiveEndEvent, RoomUserSeqEvent
        from TikTokLive.client.errors import AgeRestrictedError, UserOfflineError, UserNotFoundError, SignatureRateLimitError
    except ImportError:
        enviar("estado", estado="error", detalle="Falta TikTokLive. Ejecute el instalador del módulo.")
        return
    client = TikTokLiveClient(unique_id=usuario)
    autorizado = False
    finalizado = False
    espectadores = 0
    ultimo_conteo = 0.0

    @client.on(ConnectEvent)
    async def inicio(event):
        nonlocal autorizado
        info = client.room_info or {}
        perfil = info.get("owner") or {}
        if restringido(perfil):
            enviar("estado", estado="restringido", detalle="La cuenta o la transmisión restringen el acceso. No se capturará contenido.")
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
        nonlocal espectadores, ultimo_conteo
        espectadores = max(0, int(getattr(event, "total", 0) or 0))
        if autorizado and time.monotonic() - ultimo_conteo >= 5:
            ultimo_conteo = time.monotonic()
            enviar("espectadores", sala=str(client.room_id), cantidad=espectadores)

    @client.on(DisconnectEvent)
    async def salida(_):
        if autorizado and not finalizado:
            enviar("estado", estado="sin_live", detalle="Conexión interrumpida. Se volverá a comprobar el live público.")

    async def latidos():
        while True:
            enviar("latido")
            await asyncio.sleep(10)

    async def parar():
        await client.disconnect()

    loop = asyncio.get_running_loop()
    for sig in (signal.SIGTERM, signal.SIGINT):
        loop.add_signal_handler(sig, lambda: asyncio.create_task(parar()))
    tarea = asyncio.create_task(latidos())
    try:
        enviar("estado", estado="conectando", detalle="Comprobando la transmisión pública en TikTok.")
        await client.connect(fetch_room_info=True, process_connect_events=False)
    except (AgeRestrictedError, UserNotFoundError):
        enviar("estado", estado="restringido", detalle="No hay acceso público a esta cuenta o transmisión. Se pausó el monitoreo.")
    except UserOfflineError:
        enviar("estado", estado="sin_live", detalle="No hay un live público disponible en este momento.")
    except SignatureRateLimitError:
        enviar("estado", estado="limitado", detalle="TikTok o el proveedor de firma limitaron las peticiones. Se esperará antes de reintentar.")
    except Exception as exc:
        if "private" in type(exc).__name__.lower() or "restricted" in type(exc).__name__.lower():
            enviar("estado", estado="restringido", detalle="El contenido está restringido. Se pausó el monitoreo.")
        else:
            enviar("estado", estado="error", detalle="No se pudo conectar con TikTok. Revise la disponibilidad del servicio y la versión del capturador.")
    finally:
        tarea.cancel()
        try:
            await client.disconnect(close_client=True)
        except Exception:
            pass


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("usuario")
    args = parser.parse_args()
    import re
    if not re.fullmatch(r"[a-z0-9_.]{2,24}", args.usuario):
        sys.exit(2)
    asyncio.run(ejecutar(args.usuario))
