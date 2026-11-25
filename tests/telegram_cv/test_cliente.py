import asyncio
import hashlib
import importlib.util
from pathlib import Path
from types import SimpleNamespace
import tempfile
import unittest
from telethon import errors, types, functions
from telethon import TelegramClient
from telethon.sessions import StringSession
from unittest.mock import AsyncMock

spec = importlib.util.spec_from_file_location("telegram_cv", Path(__file__).resolve().parents[2] / "resources/telegram-cv/cliente.py")
tg = importlib.util.module_from_spec(spec)
spec.loader.exec_module(tg)

class ClienteFalso:
    def __init__(self):
        self.me = types.User(id=8476238020, first_name="Jeffry", phone="50688887777")
        self.calls = []
        self.authorized = True
        self.password_required = False
        self.hidden = False
        self.recipient = types.User(id=7000000000001, first_name="Empresa", access_hash=-9000000000000000000, phone="50588887777")
        self.result = None

    async def is_user_authorized(self): return self.authorized
    async def get_me(self): return self.me
    async def send_code_request(self, phone):
        self.calls.append(("codigo", phone))
        return SimpleNamespace(phone_code_hash="HASH-FICTICIO")
    async def sign_in(self, **kwargs):
        self.calls.append(("login", kwargs))
        if self.password_required and "code" in kwargs:
            raise errors.SessionPasswordNeededError(request=None)
    async def get_entity(self, phone):
        if self.hidden: raise ValueError("No entregado")
        return self.recipient
    async def upload_file(self, path, **kwargs):
        self.calls.append(("archivo", path, kwargs))
        return types.InputFile(id=12, parts=1, name="cv.pdf", md5_checksum="a" * 32)
    async def __call__(self, request):
        self.calls.append(request)
        if isinstance(request, functions.contacts.ImportContactsRequest):
            if self.hidden: return types.contacts.ImportedContacts(imported=[], popular_invites=[], retry_contacts=[], users=[])
            return types.contacts.ImportedContacts(imported=[types.ImportedContact(user_id=self.recipient.id, client_id=request.contacts[0].client_id)], popular_invites=[], retry_contacts=[], users=[self.recipient])
        return self.result or SimpleNamespace(updates=[types.UpdateMessageID(id=99, random_id=request.random_id)])

class TelegramCvTest(unittest.TestCase):
    def setUp(self):
        self.c = ClienteFalso()
        self.d = {"telefono": "+50688887777", "cuenta_id": "8476238020", "accion": "enviar", "peer": {"id": "7000000000001", "access_hash": "-9000000000000000000"}, "mensaje": "Saludos <b>sin formato</b>", "random_id": "7123456789000000000", "tipo": "mensaje"}
    def runop(self, **changes): return asyncio.run(tg.operacion(self.c, dict(self.d, **changes)))
    def test_resolver_telefono_conserva_id_y_hash_grandes(self):
        r = self.runop(accion="resolver", destino="+50588887777", contacto="Empresa de prueba")
        self.assertEqual(r["id"], "7000000000001")
        self.assertEqual(r["access_hash"], "-9000000000000000000")
        self.assertEqual(len(self.c.calls), 1)
    def test_privacidad_no_busca_otra_cuenta_ni_envia(self):
        self.c.hidden = True
        with self.assertRaises(tg.Fallo) as e: self.runop(accion="resolver", destino="+50588887777", contacto="Empresa")
        self.assertEqual(e.exception.codigo, "destino")
        self.assertEqual(len(self.c.calls), 1)
    def test_no_acepta_contacto_distinto_en_resolucion_fallback(self):
        original = self.c.__class__.__call__
        async def empty(obj, req):
            if isinstance(req, functions.contacts.ImportContactsRequest): return types.contacts.ImportedContacts(imported=[], popular_invites=[], retry_contacts=[], users=[])
            return await original(obj, req)
        from unittest.mock import patch
        self.c.recipient.phone = "50599998888"
        with patch.object(ClienteFalso, "__call__", empty), self.assertRaises(tg.Fallo): self.runop(accion="resolver", destino="+50588887777", contacto="Empresa")
    def test_no_envia_desde_otra_cuenta_o_sesion_revocada(self):
        for cambio in ({"cuenta_id": "111111"}, {"telefono": "+50699998888"}):
            with self.assertRaises(tg.Fallo): self.runop(**cambio)
        self.c.authorized = False
        with self.assertRaises(tg.Fallo): self.runop()
        self.assertEqual(self.c.calls, [])
    def test_mensaje_plano_un_solo_rpc_y_random_id_persistido(self):
        r = self.runop()
        self.assertEqual(r["mensaje_id"], "99")
        req = self.c.calls[0]
        self.assertIsInstance(req, functions.messages.SendMessageRequest)
        self.assertEqual(req.random_id, int(self.d["random_id"]))
        self.assertTrue(req.no_webpage)
        self.assertEqual(req.message, self.d["mensaje"])
        self.assertIsNone(req.entities)
    def test_pdf_se_envia_como_documento_y_texto_junto(self):
        with tempfile.TemporaryDirectory() as tmp:
            p = Path(tmp) / "cv.pdf"; p.write_bytes(b"%PDF-1.4 version confirmada")
            r = self.runop(tipo="cv", archivo=str(p), archivo_nombre="CV_Ana.pdf", archivo_sha256=hashlib.sha256(p.read_bytes()).hexdigest())
        self.assertEqual(r["mensaje_id"], "99")
        req = self.c.calls[-1]
        self.assertIsInstance(req, functions.messages.SendMediaRequest)
        self.assertEqual(req.media.mime_type, "application/pdf")
        self.assertTrue(req.media.force_file)
        self.assertEqual(req.message, self.d["mensaje"])
        self.assertEqual(req.random_id, int(self.d["random_id"]))
    def test_pdf_alterado_y_texto_mayor_a_limite_no_envian(self):
        with tempfile.TemporaryDirectory() as tmp:
            p = Path(tmp) / "cv.pdf"; p.write_bytes(b"%PDF-1.4 alterado")
            with self.assertRaises(tg.Fallo): self.runop(tipo="cv", archivo=str(p), archivo_sha256="0"*64)
        with self.assertRaises(tg.Fallo): self.runop(tipo="cv", mensaje="😀"*513)
        self.assertEqual(self.c.calls, [])
    def test_login_codigo_y_dos_pasos_sin_mensaje(self):
        r = self.runop(accion="codigo")
        self.assertEqual(r["codigo_hash"], "HASH-FICTICIO")
        self.c.password_required = True
        self.assertEqual(self.runop(accion="verificar", codigo="12345", codigo_hash="HASH-FICTICIO"), {"estado": "clave"})
        r = self.runop(accion="clave", clave="SECRETO-FICTICIO")
        self.assertEqual(r["id"], "8476238020")
        self.assertFalse(any(isinstance(x, functions.messages.SendMessageRequest) for x in self.c.calls))
    def test_respuesta_sin_random_id_no_afirma_envio(self):
        self.c.result = SimpleNamespace(updates=[])
        with self.assertRaises(tg.Fallo) as e: self.runop()
        self.assertEqual(e.exception.codigo, "servicio")
    def test_errores_no_exponen_descripcion_y_respeta_espera(self):
        self.assertEqual(tg.clasificar(errors.FloodWaitError(request=None, capture=120)), ("limite", 120))
        self.assertEqual(tg.clasificar(errors.UserPrivacyRestrictedError(request=None)), ("privacidad", 0))
        result = asyncio.run(tg.ejecutar({"v": 1, "api_id": 0, "api_hash": "SECRETO"}))
        self.assertNotIn("SECRETO", str(result))
        self.assertFalse(result["ok"])

    def test_sdk_real_redirecciona_solicitud_de_codigo_al_dc_correcto(self):
        async def caso(retries):
            client = TelegramClient(StringSession(),12345,"a"*32,request_retries=retries,raise_last_call_error=True,receive_updates=False)
            client.is_user_authorized = AsyncMock(return_value=False)
            client._switch_dc = AsyncMock()
            class Sender:
                calls = 0
                def send(self, request, ordered=False):
                    self.calls += 1
                    f = asyncio.get_running_loop().create_future()
                    if self.calls == 1:
                        f.set_exception(errors.PhoneMigrateError(request=request,capture=2))
                    else:
                        f.set_result(types.auth.SentCode(type=types.auth.SentCodeTypeApp(length=5),phone_code_hash="HASH-DC-CORRECTO",timeout=60))
                    return f
            sender = Sender();client._sender = sender
            if retries == 0:
                with self.assertRaises(errors.PhoneMigrateError):
                    await client.send_code_request("+50688887777")
                self.assertEqual(sender.calls,1)
            else:
                r = await client.send_code_request("+50688887777")
                self.assertEqual(r.phone_code_hash,"HASH-DC-CORRECTO")
                self.assertEqual(sender.calls,2)
            client._switch_dc.assert_awaited_once_with(2)
        asyncio.run(caso(0));asyncio.run(caso(2))

    def test_opciones_reintentan_login_pero_no_envios_y_diagnostico_no_filtra_secretos(self):
        for action,retries in [("codigo",2),("enviar",0)]:
            def factory(session, api_id, api_hash, **kwargs):
                self.assertEqual(kwargs["request_retries"],retries)
                self.assertTrue(kwargs["raise_last_call_error"])
                raise ConnectionError("URL SESION-SECRETA api_hash PRIVADO")
            out = asyncio.run(tg.ejecutar({"v":1,"api_id":12345,"api_hash":"a"*32,"accion":action},factory=factory))
            self.assertEqual(out["error"],"conexion")
            self.assertEqual(out["diagnostico"],"CONEXION")
            self.assertNotIn("PRIVADO",str(out))
            self.assertNotIn("SESION-SECRETA",str(out))

if __name__ == "__main__": unittest.main()
