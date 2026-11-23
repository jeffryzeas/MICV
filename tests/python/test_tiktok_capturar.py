import importlib.util
from pathlib import Path
import io
import asyncio
from unittest.mock import patch
import json
import unittest
from contextlib import redirect_stdout
from types import SimpleNamespace as S
spec=importlib.util.spec_from_file_location('capturar',Path(__file__).resolve().parents[2] / 'resources/tiktok/capturar.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class AdapterTest(unittest.TestCase):
 def test_event_public_normalized(self):
  event=S(content='Hola <script>texto</script> 😀',user=S(unique_id='autor',nickname='Nombre'),common=S(msg_id=9999999999999999999,create_time=1730000000))
  d=m.comentario(event,1234567890123456789);self.assertEqual(d['sala'],'1234567890123456789');self.assertEqual(d['id'],'9999999999999999999');self.assertEqual(d['ocurrido_ms'],1730000000000);self.assertIn('😀',d['texto'])
 def test_missing_user_is_not_invented(self):
  d=m.comentario(S(content='hola',user=None,common=None),'123456');self.assertEqual(d['autor'],'sin_identificador');self.assertTrue(d['id'])
 def test_private_restrictions_are_respected(self):
  for v in (True,1,'1','private','true'):self.assertTrue(m.restringido({'private_account':v}))
  for v in (False,0,'0','false',None):self.assertFalse(m.restringido({'private_account':v}))
 def test_ndjson_one_line_and_unicode(self):
  out=io.StringIO()
  with redirect_stdout(out):m.enviar('comentario',texto='Línea 1\nLínea 2 😀')
  self.assertEqual(len(out.getvalue().splitlines()),1);self.assertEqual(json.loads(out.getvalue())['texto'],'Línea 1\nLínea 2 😀')
class ConnectionTest(unittest.TestCase):
 def test_offline_is_waiting_and_not_restricted(self):
  from TikTokLive.client.errors import UserOfflineError
  d=m.clasificar_error(UserOfflineError())
  self.assertEqual(d['estado'],'sin_live');self.assertIn('activado',d['detalle'])
 def test_user_not_found_does_not_pause_monitoring(self):
  from TikTokLive.client.errors import UserNotFoundError
  d=m.clasificar_error(UserNotFoundError('demo'))
  self.assertEqual(d['estado'],'error');self.assertEqual(d['codigo'],'cuenta_no_disponible')
 def test_confirmed_restriction_stays_restricted(self):
  from TikTokLive.client.errors import AgeRestrictedError
  self.assertEqual(m.clasificar_error(AgeRestrictedError())['estado'],'restringido')
 def test_blocked_request_is_not_misrepresented_as_a_private_profile(self):
  import httpx
  e=httpx.HTTPStatusError('remote secret',request=httpx.Request('GET','https://example.test/?token=SECRET'),response=httpx.Response(403))
  d=m.clasificar_error(e);self.assertEqual(d['estado'],'error');self.assertEqual(d['codigo'],'solicitud_rechazada');self.assertEqual(d['http'],403)
 def test_signing_limit_respects_retry_after(self):
  import httpx
  from TikTokLive.client.errors import SignatureRateLimitError
  r=httpx.Response(429,headers={'RateLimit-Remaining':'300','Retry-After':'1800'})
  e=SignatureRateLimitError(None,'retry %s',response=r)
  d=m.clasificar_error(e);self.assertEqual(d['estado'],'limitado');self.assertEqual(d['espera_segundos'],1800)
 def test_rate_reset_and_excessive_retry_are_bounded(self):
  import httpx
  r=httpx.Response(429,headers={'RateLimit-Reset':str(int(m.time.time())+3600)})
  self.assertGreaterEqual(m.espera_indicada(r),3599)
  self.assertEqual(m.espera_indicada(httpx.Response(429,headers={'Retry-After':'999999999'})),86400)
 def test_network_and_timeout_are_different(self):
  import httpx
  self.assertEqual(m.clasificar_error(httpx.ConnectError('DNS secret'))['codigo'],'conexion_red')
  self.assertEqual(m.clasificar_error(httpx.ReadTimeout('timeout secret'))['codigo'],'tiempo_agotado')
 def test_diagnostic_never_contains_raw_exception_or_remote_headers(self):
  import httpx
  r=httpx.Response(503,headers={'Set-Cookie':'SECRET-cookie'})
  e=httpx.HTTPStatusError('SECRET-exception',request=httpx.Request('GET','https://example.test/?secret=SECRET-url'),response=r)
  out=io.StringIO()
  with redirect_stdout(out):m.informar_error(e)
  lines=[json.loads(x)for x in out.getvalue().splitlines()]
  self.assertEqual([x['tipo']for x in lines],['diagnostico','estado']);self.assertEqual(lines[0]['http'],503);self.assertNotIn('SECRET',out.getvalue())
 def test_unknown_exception_is_reported_without_secrets(self):
  d=m.clasificar_error(RuntimeError('private_token=SECRET'))
  self.assertEqual(d['estado'],'error');self.assertEqual(d['clase'],'RuntimeError');self.assertNotIn('SECRET',json.dumps(d))
 def test_connect_deadline_stops_an_attempt_without_an_established_socket(self):
  class Client:
   async def connect(self,**kwargs):await asyncio.sleep(10)
  async def run():
   with self.assertRaises(asyncio.TimeoutError):await m.conectar_con_limite(Client(),asyncio.Event(),.02)
  asyncio.run(run())
 def test_connected_live_outlasts_the_connect_deadline(self):
  async def run():
   listo=asyncio.Event()
   class Client:
    async def connect(self,**kwargs):listo.set();await asyncio.sleep(.05);return 'finished'
   await m.conectar_con_limite(Client(),listo,.01)
  asyncio.run(run())
 def test_sdk_connection_failure_emits_retry_state_instead_of_restricting(self):
  from TikTokLive.client.errors import UserNotFoundError
  class Client:
   def __init__(self,**kwargs):pass
   def on(self,event):return lambda callback:callback
   async def connect(self,**kwargs):raise UserNotFoundError('demo')
   async def disconnect(self,**kwargs):pass
  out=io.StringIO()
  with patch('TikTokLive.TikTokLiveClient',Client),redirect_stdout(out):asyncio.run(m.ejecutar('demo'))
  records=[json.loads(x)for x in out.getvalue().splitlines()]
  self.assertEqual([x for x in records if x['tipo']=='estado'][-1]['estado'],'error')
  self.assertTrue(any(x.get('codigo')=='cuenta_no_disponible'for x in records))
if __name__=='__main__':unittest.main()
