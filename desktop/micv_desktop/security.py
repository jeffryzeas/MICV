"""Firmas de licencia y protección de la clave local por usuario de Windows."""
from __future__ import annotations
import base64, ctypes, hashlib, hmac, json, os, secrets, sys, time
from pathlib import Path
from urllib.parse import urlsplit
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

class AccessError(Exception):
    pass

def check_profile(profile: dict, *, allow_local: bool = False) -> dict:
    url = str(profile.get('server_url', '')).rstrip('/')
    parts = urlsplit(url)
    if parts.username or parts.password or parts.query or parts.fragment or not parts.hostname:
        raise AccessError('El archivo de conexión contiene una dirección inválida.')
    if parts.scheme != 'https' and not (allow_local and parts.scheme == 'http' and parts.hostname in ('127.0.0.1', 'localhost')):
        raise AccessError('La conexión requiere HTTPS. Use el archivo descargado por su administrador.')
    try:
        raw = base64.b64decode(profile['public_key'], validate=True)
        if len(raw) != 32:
            raise ValueError()
    except (ValueError, KeyError) as exc:
        raise AccessError('La clave pública de conexión no es válida.') from exc
    return {'version': 1, 'server_url': url, 'public_key': profile['public_key']}

def verify_lease(envelope: dict, profile: dict, device: str, *, now: float | None = None, valid: bool = True) -> dict:
    try:
        payload = base64.b64decode(envelope['payload'], validate=True)
        signature = base64.b64decode(envelope['signature'], validate=True)
        Ed25519PublicKey.from_public_bytes(base64.b64decode(profile['public_key'], validate=True)).verify(signature, payload)
        claims = json.loads(payload)
        if claims['version'] != 1 or claims['device'] != device or claims['issuer'].rstrip('/') != profile['server_url']:
            raise ValueError()
        if not isinstance(claims['scopes'], list) or not isinstance(claims['valid_until'], int) or claims['valid_until'] <= claims['issued_at']:
            raise ValueError()
        if claims['valid_until'] - claims['issued_at'] > 30 * 86400 + 60:
            raise ValueError()
        now = time.time() if now is None else now
        if valid and (now >= claims['valid_until'] or now + 300 < claims['issued_at']):
            raise AccessError('La autorización sin conexión venció o la fecha del equipo no es correcta. Conéctese para renovarla.')
        return claims
    except AccessError:
        raise
    except Exception as exc:
        raise AccessError('No se pudo verificar la firma de la licencia de este equipo.') from exc

def _dpapi(raw: bytes, protect: bool) -> bytes:
    from ctypes import wintypes
    class Blob(ctypes.Structure):
        _fields_ = [('length', wintypes.DWORD), ('data', ctypes.POINTER(ctypes.c_ubyte))]
    source = ctypes.create_string_buffer(raw)
    incoming = Blob(len(raw), ctypes.cast(source, ctypes.POINTER(ctypes.c_ubyte)))
    outgoing = Blob()
    crypt = ctypes.WinDLL('crypt32', use_last_error=True)
    function = crypt.CryptProtectData if protect else crypt.CryptUnprotectData
    function.argtypes = [ctypes.POINTER(Blob), ctypes.c_void_p, ctypes.c_void_p, ctypes.c_void_p, ctypes.c_void_p, wintypes.DWORD, ctypes.POINTER(Blob)]
    function.restype = wintypes.BOOL
    if not function(ctypes.byref(incoming), None, None, None, None, 1, ctypes.byref(outgoing)):
        raise AccessError('Windows no pudo abrir la clave local. Use el mismo perfil de usuario con el que activó el programa.')
    try:
        return ctypes.string_at(outgoing.data, outgoing.length)
    finally:
        free = ctypes.WinDLL('kernel32').LocalFree
        free.argtypes = [ctypes.c_void_p]
        free.restype = ctypes.c_void_p
        free(outgoing.data)

class Vault:
    def __init__(self, folder: Path):
        folder.mkdir(parents=True, exist_ok=True, mode=0o700)
        self.path = folder / 'local.key'
        windows = sys.platform == 'win32'
        if not self.path.exists():
            key = secrets.token_bytes(32)
            encoded = b'DPAPI1' + _dpapi(key, True) if windows else b'DEV1' + key
            with self.path.open('xb') as handle:
                os.chmod(self.path, 0o600)
                handle.write(encoded)
        encoded = self.path.read_bytes()
        if windows and encoded.startswith(b'DPAPI1'):
            key = _dpapi(encoded[6:], False)
        elif not windows and encoded.startswith(b'DEV1'):
            key = encoded[4:]
        else:
            raise AccessError('La clave local pertenece a otra plataforma o perfil. No se borraron sus datos.')
        self.cipher = AESGCM(key)
        self.algorithm = algorithms.AES(key)

    def pack(self, context: str, value) -> bytes:
        nonce = secrets.token_bytes(12)
        return nonce + self.cipher.encrypt(nonce, json.dumps(value, ensure_ascii=False, sort_keys=True).encode(), context.encode())

    def unpack(self, context: str, value: bytes):
        return json.loads(self.cipher.decrypt(value[:12], value[12:], context.encode()))

    def seal_backup(self, source, target):
        nonce = secrets.token_bytes(12)
        cipher = Cipher(self.algorithm, modes.GCM(nonce)).encryptor()
        cipher.authenticate_additional_data(b'micv-profile-backup-v1')
        target.write(nonce)
        while chunk := source.read(1024 * 1024):
            target.write(cipher.update(chunk))
        target.write(cipher.finalize())
        target.write(cipher.tag)

    def open_backup(self, source, target):
        nonce, tail = source.read(12), source.read(16)
        if len(nonce) != 12 or len(tail) != 16:
            raise AccessError('El respaldo está incompleto.')
        cipher = Cipher(self.algorithm, modes.GCM(nonce)).decryptor()
        cipher.authenticate_additional_data(b'micv-profile-backup-v1')
        while chunk := source.read(1024 * 1024):
            block = tail + chunk
            target.write(cipher.update(block[:-16]))
            tail = block[-16:]
        target.write(cipher.finalize_with_tag(tail))


def pin_hash(pin: str, salt: str | None = None) -> dict:
    if len(pin) < 6 or len(pin) > 64:
        raise AccessError('Use un PIN o contraseña local de 6 a 64 caracteres.')
    salt = salt or secrets.token_hex(16)
    digest = hashlib.pbkdf2_hmac('sha256', pin.encode(), bytes.fromhex(salt), 600_000).hex()
    return {'salt': salt, 'hash': digest}

def pin_valid(pin: str, saved: dict) -> bool:
    try:
        return hmac.compare_digest(pin_hash(pin, saved['salt'])['hash'], saved['hash'])
    except (AccessError, ValueError, KeyError):
        return False
