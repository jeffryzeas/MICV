import asyncio
import datetime
import importlib.util
from pathlib import Path
import sys
from types import SimpleNamespace
import unittest
from unittest.mock import AsyncMock
from telethon import errors, types
from telethon.tl.custom.qrlogin import QRLogin

ROOT=Path(__file__).resolve().parents[2]/'resources/telegram-cv'
sys.path.insert(0,str(ROOT))
spec=importlib.util.spec_from_file_location('micv_qr',ROOT/'qr.py')
qr=importlib.util.module_from_spec(spec);spec.loader.exec_module(qr)

class FakeClient:
    def __init__(self,modo='ok'):
        self.modo=modo;self.session=SimpleNamespace(save=lambda:'S'*100);self.listener=False;self.count=0;self.authorized=True;self.logout=0
    async def connect(self):pass
    async def disconnect(self):pass
    async def is_user_authorized(self):return self.authorized
    async def log_out(self):self.logout+=1;self.authorized=False
    async def get_me(self):return types.User(id=8476238020,phone='50688887777',first_name='Sistema')
    async def qr_login(self):
        self.count+=1;numero=self.count;client=self
        class Code:
            url='tg://login?token='+('A' if numero==1 else 'B')*43
            expires=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(seconds=40)
            async def wait(self,timeout):
                client.listener=True
                await asyncio.sleep(.001)
                if client.modo=='clave':raise errors.SessionPasswordNeededError(request=None)
                if client.modo=='renovar' and numero==1:raise asyncio.TimeoutError()
                if client.modo=='cancelar':await asyncio.Future()
                return await client.get_me()
        return Code()

class QrTests(unittest.TestCase):
    def escenario(self,modo='ok',confirmar=True,expired=False):
        async def correr():
            cliente=FakeClient(modo);lector=asyncio.StreamReader();eventos=[]
            def emit(e):
                eventos.append(e)
                if e['tipo']=='qr':
                    self.assertTrue(cliente.listener,'El listener debe estar listo antes de exponer el QR.')
                    if modo=='cancelar':lector.feed_data(b'{"accion":"cancelar"}\n')
                if e['tipo'] in ('conectada','clave'):
                    if confirmar:lector.feed_data(b'{"accion":"aceptar"}\n')
                    else:lector.feed_eof()
            def factory(*args,**kwargs):
                self.assertTrue(kwargs['receive_updates']);self.assertEqual(kwargs['request_retries'],2);return cliente
            vence=(datetime.datetime.now(datetime.timezone.utc).timestamp()+(-1 if expired else 120))*1000
            await qr.vincular({'v':1,'api_id':12345,'api_hash':'a'*32,'expira_ms':vence},lector,emit,factory)
            return cliente,eventos
        return asyncio.run(correr())
    def test_qr_listener_antes_de_publicar_y_sesion_aceptada(self):
        c,events=self.escenario();self.assertEqual([e['tipo'] for e in events],['qr','conectada']);self.assertEqual(events[-1]['telefono'],'+50688887777');self.assertEqual(c.logout,0)
    def test_puede_renovar_un_token_caducado_durante_el_mismo_flujo(self):
        c,events=self.escenario('renovar');tokens=[e['url'] for e in events if e['tipo']=='qr'];self.assertEqual(len(tokens),2);self.assertNotEqual(*tokens);self.assertEqual(events[-1]['tipo'],'conectada')
    def test_password_despues_de_qr_no_pide_codigo(self):
        c,events=self.escenario('clave');self.assertEqual(events[-1]['tipo'],'clave');self.assertEqual(events[-1]['sesion'],'S'*100);self.assertEqual(c.logout,0)
    def test_cancelacion_cierra_sesion_que_se_haya_aprobado_en_transito(self):
        c,events=self.escenario('cancelar');self.assertEqual(events[-1]['error'],'qr_cancelado');self.assertEqual(c.logout,1)
    def test_sin_ack_de_laravel_se_revoca_y_no_deja_sesion_huerfana(self):
        c,events=self.escenario(confirmar=False);self.assertEqual(c.logout,1);self.assertEqual(events[-1]['error'],'qr_cancelado')
    def test_vinculacion_caducada_no_publica_token(self):
        c,events=self.escenario(expired=True);self.assertEqual(len(events),1);self.assertEqual(events[0]['error'],'qr_caducado');self.assertEqual(c.count,0)
    def test_sdk_qr_real_importa_token_en_otro_centro_de_datos(self):
        async def correr():
            c=SimpleNamespace(api_id=12345,api_hash='a'*32)
            handlers=[]
            c.add_event_handler=lambda h,e:handlers.append(h)
            c.remove_event_handler=lambda h:None
            c._switch_dc=AsyncMock();c._on_login=AsyncMock()
            class Client:
                api_id=c.api_id;api_hash=c.api_hash
                add_event_handler=staticmethod(c.add_event_handler);remove_event_handler=staticmethod(c.remove_event_handler)
                _switch_dc=c._switch_dc;_on_login=c._on_login
                n=0
                async def __call__(self,req):
                    self.n+=1
                    if self.n==1:return types.auth.LoginTokenMigrateTo(dc_id=4,token=b'token-otra-dc')
                    return types.auth.LoginTokenSuccess(authorization=types.auth.Authorization(user=types.User(id=8476238020,first_name='Sistema')))
            client=Client();obj=QRLogin(client,[]);obj._resp=types.auth.LoginToken(expires=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(seconds=10),token=b'token-local')
            task=asyncio.create_task(obj.wait());await asyncio.sleep(0);await handlers[0](types.UpdateLoginToken());me=await task
            self.assertEqual(me.id,8476238020);c._switch_dc.assert_awaited_once_with(4);c._on_login.assert_awaited_once()
        asyncio.run(correr())

if __name__=='__main__':unittest.main()
