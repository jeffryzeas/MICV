#!/usr/bin/env python3
"""Adaptador MTProto: una operación explícita, JSON por stdin/stdout, sin secretos en disco."""
import asyncio
import hashlib
import json
import logging
from pathlib import Path
import re
import secrets
import sys
from telethon import TelegramClient, errors, functions, types
from telethon.sessions import StringSession

logging.disable(logging.CRITICAL)

class Fallo(Exception):
    def __init__(self, codigo, espera=0):
        self.codigo, self.espera = codigo, espera

def numero(value):
    if not isinstance(value, str) or not re.fullmatch(r"\+[1-9][0-9]{7,14}", value):
        raise Fallo("destino")
    return value

def nombre(user):
    return " ".join(x for x in [getattr(user, "first_name", ""), getattr(user, "last_name", "")] if x)[:200]

async def propia(client, data):
    if not await client.is_user_authorized():
        raise Fallo("sesion")
    me = await client.get_me()
    if not me or getattr(me, "bot", False) or getattr(me, "deleted", False):
        raise Fallo("cuenta")
    numero('+'+str(getattr(me,"phone","")).lstrip('+'))
    if not (data.get('modo_login')=='qr' and data.get('accion')=='clave') and str(getattr(me, "phone", "")).lstrip("+") != numero(data["telefono"])[1:]:
        raise Fallo("cuenta")
    if data.get("cuenta_id") and str(me.id) != str(data["cuenta_id"]):
        raise Fallo("cuenta")
    return me

def mensaje_id(result, random_id):
    if isinstance(result, types.UpdateShortSentMessage):
        return str(result.id)
    for update in getattr(result, "updates", []):
        if isinstance(update, types.UpdateMessageID) and update.random_id == random_id:
            return str(update.id)
    # No se interpreta una respuesta sin el vínculo random_id como confirmación de este envío.
    raise Fallo("servicio")

async def operacion(client, d):
    accion = d.get("accion")
    if accion == "codigo":
        sent = await client.send_code_request(numero(d["telefono"]))
        return {"codigo_hash": sent.phone_code_hash, "estado": "codigo"}
    if accion == "verificar":
        if not re.fullmatch(r"[0-9]{4,8}", str(d.get("codigo", ""))) or not d.get("codigo_hash"):
            raise Fallo("codigo")
        try:
            await client.sign_in(phone=numero(d["telefono"]), code=d["codigo"], phone_code_hash=d["codigo_hash"])
        except errors.SessionPasswordNeededError:
            return {"estado": "clave"}
    elif accion == "clave":
        if not isinstance(d.get("clave"), str) or not d["clave"] or len(d["clave"]) > 256:
            raise Fallo("clave")
        await client.sign_in(password=d["clave"])
    elif accion == "desconectar":
        await client.log_out()
        return {"estado": "desconectada"}
    elif accion not in ("resolver", "enviar"):
        raise Fallo("servicio")

    me = await propia(client, d)
    if accion in ("verificar", "clave"):
        return {"estado": "conectada", "id": str(me.id), "nombre": nombre(me), "usuario": getattr(me, "username", None) or "",'telefono':'+'+str(me.phone).lstrip('+')}
    if accion == "resolver":
        phone = numero(d.get("destino"))
        etiqueta = str(d.get("contacto", ""))[:80].strip()
        if not etiqueta:
            raise Fallo("destino")
        # Una sola búsqueda, iniciada por el administrador; no enumeración de contactos.
        cid = secrets.randbelow(2**62) + 1
        result = await client(functions.contacts.ImportContactsRequest(contacts=[types.InputPhoneContact(client_id=cid, phone=phone, first_name=etiqueta, last_name="")]))
        imported = next((x for x in result.imported if x.client_id == cid), None)
        user = next((u for u in result.users if imported and u.id == imported.user_id), None)
        if user is None:
            # Solo un contacto ya conocido con ese teléfono exacto; no se busca otra identidad.
            try:
                user = await client.get_entity(phone)
            except (ValueError, TypeError):
                raise Fallo("destino") from None
            if str(getattr(user, "phone", "")).lstrip("+") != phone[1:]:
                raise Fallo("destino")
        if not isinstance(user, types.User) or user.bot or user.deleted or user.access_hash is None:
            raise Fallo("destino")
        return {"id": str(user.id), "access_hash": str(user.access_hash), "nombre": nombre(user), "usuario": user.username or ""}

    peer = d.get("peer", {})
    if not isinstance(peer, dict) or not re.fullmatch(r"[1-9][0-9]{0,18}", str(peer.get("id", ""))) or not re.fullmatch(r"-?[0-9]{1,19}", str(peer.get("access_hash", ""))):
        raise Fallo("destino")
    to = types.InputPeerUser(user_id=int(peer["id"]), access_hash=int(peer["access_hash"]))
    texto = d.get("mensaje", "")
    if not isinstance(texto, str) or not texto or len(texto.encode("utf-16-le")) // 2 > (1024 if d.get("tipo") == "cv" else 4096):
        raise Fallo("archivo")
    rid = int(d.get("random_id", 0))
    if not 0 < rid < 2**63:
        raise Fallo("servicio")
    if d.get("tipo") == "cv":
        path = Path(d.get("archivo", ""))
        if not path.is_absolute() or not path.is_file() or path.stat().st_size > 18 * 1024 * 1024:
            raise Fallo("archivo")
        data = path.read_bytes()
        if not data.startswith(b"%PDF-") or hashlib.sha256(data).hexdigest() != d.get("archivo_sha256"):
            raise Fallo("archivo")
        archivo_nombre = str(d.get("archivo_nombre", "CV.pdf"))
        if not re.fullmatch(r"[A-Za-z0-9_.-]{1,150}\.pdf", archivo_nombre):
            raise Fallo("archivo")
        uploaded = await client.upload_file(str(path), file_name=archivo_nombre, part_size_kb=256)
        media = types.InputMediaUploadedDocument(file=uploaded, mime_type="application/pdf", attributes=[types.DocumentAttributeFilename(archivo_nombre)], force_file=True)
        request = functions.messages.SendMediaRequest(peer=to, media=media, message=texto, random_id=rid)
    elif d.get("tipo") == "mensaje":
        request = functions.messages.SendMessageRequest(peer=to, message=texto, random_id=rid, no_webpage=True)
    else:
        raise Fallo("archivo")
    result = await client(request)
    return {"mensaje_id": mensaje_id(result, rid)}

def clasificar(e):
    if isinstance(e, Fallo):
        return e.codigo, e.espera
    # Los nombres se cotejan, las descripciones originales jamás se imprimen.
    n = type(e).__name__
    if n == "FloodWaitError":
        return "limite", max(1, min(86400, int(e.seconds)))
    if n in ("PeerFloodError", "PhoneNumberFloodError", "PhonePasswordFloodError"):
        return "limite", 86400
    if n in ("ApiIdInvalidError", "ApiIdPublishedFloodError", "PhoneNumberInvalidError", "PhoneNumberBannedError"):
        return "credenciales", 0
    if n in ("PhoneCodeInvalidError", "PhoneCodeEmptyError"):
        return "codigo", 0
    if n in ("PhoneCodeExpiredError", "PhoneCodeHashEmptyError"):
        return "codigo_caducado", 0
    if n == "PasswordHashInvalidError":
        return "clave", 0
    if n in ("AuthKeyUnregisteredError", "SessionRevokedError", "AuthKeyDuplicatedError", "UserDeactivatedError", "UserDeactivatedBanError"):
        return "sesion", 0
    if n in ("UserPrivacyRestrictedError", "UserIsBlockedError", "YouBlockedUserError", "ChatWriteForbiddenError", "PeerIdInvalidError", "UserNotMutualContactError", "PremiumAccountRequiredError"):
        return "privacidad", 0
    if isinstance(e, (ConnectionError, TimeoutError, OSError)):
        return "conexion", 0
    if n in ("PhoneMigrateError", "NetworkMigrateError", "UserMigrateError", "ServerError", "RpcCallFailError", "TimedOutError", "AuthRestartError"):
        return "telegram_temporal", 0
    return "servicio", 0

def diagnostico(e):
    n = type(e).__name__
    if isinstance(e, (ConnectionError, TimeoutError, OSError)):
        return "CONEXION"
    if n in ("PhoneMigrateError", "NetworkMigrateError", "UserMigrateError"):
        return "CENTRO_DATOS"
    if n in ("ServerError", "RpcCallFailError", "TimedOutError", "AuthRestartError"):
        return "TELEGRAM_TEMPORAL"
    return "ADAPTADOR_INTERNO" if n in ("ValueError", "TypeError", "AttributeError", "RuntimeError") else "SIN_CLASIFICAR"

async def ejecutar(d, factory=TelegramClient):
    client = None
    try:
        if d.get("v") != 1 or not isinstance(d.get("api_id"), int) or not 0 < d["api_id"] <= 2147483647 or not re.fullmatch(r"[a-fA-F0-9]{32}", str(d.get("api_hash", ""))):
            raise Fallo("credenciales")
        session = StringSession(d.get("sesion") or "")
        # La autenticación necesita continuar tras PHONE_MIGRATE/NETWORK_MIGRATE.
        # Los envíos conservan cero reintentos automáticos ante un resultado incierto.
        client = factory(session, d["api_id"], d["api_hash"], device_model="Mi CV", system_version="Linux", app_version="1.0", lang_code="es", system_lang_code="es", timeout=12, connection_retries=1, request_retries=(0 if d.get("accion") == "enviar" else 2), raise_last_call_error=True, auto_reconnect=False, flood_sleep_threshold=0, receive_updates=False)
        await client.connect()
        result = await operacion(client, d)
        return {"v": 1, "ok": True, "sesion": client.session.save() if client.session else "", **result}
    except Exception as e:
        codigo, espera = clasificar(e)
        return {"v": 1, "ok": False, "error": codigo, "espera": espera, "diagnostico": diagnostico(e)}
    finally:
        if client:
            try:
                await client.disconnect()
            except Exception:
                pass

def main():
    try:
        raw = sys.stdin.buffer.read(65537)
        if len(raw) > 65536:
            raise ValueError()
        data = json.loads(raw)
        if not isinstance(data, dict):
            raise ValueError()
        out = asyncio.run(ejecutar(data))
    except Exception:
        out = {"v": 1, "ok": False, "error": "servicio", "espera": 0}
    sys.stdout.write(json.dumps(out, ensure_ascii=True))

if __name__ == "__main__":
    main()
