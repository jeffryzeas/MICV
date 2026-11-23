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
 def test_private_commenter_in_a_public_live_is_normalized(self):
  event=S(content='Comentario público',user=S(unique_id='privado',nickname='Persona',private_account=True,secret=1),common=S(msg_id=7,create_time=1730000000))
  d=m.comentario(event,'123456');self.assertEqual(d['autor'],'privado');self.assertEqual(d['texto'],'Comentario público')
 def test_ndjson_one_line_and_unicode(self):
  out=io.StringIO()
  with redirect_stdout(out):m.enviar('comentario',texto='Línea 1\nLínea 2 😀')
  self.assertEqual(len(out.getvalue().splitlines()),1);self.assertEqual(json.loads(out.getvalue())['texto'],'Línea 1\nLínea 2 😀')
 def test_public_ids_avatar_and_original_text_digest_are_preserved(self):
  user=S(unique_id='persona',user_id=9999999999999999999,nickname='Nombre',avatar_thumb=S(url_list=['https://p16.tiktokcdn.com/foto.png']))
  event=S(user=user,content='Hola 😀',common=S(msg_id=9999999999999999998,create_time=1730000000),content_language='es')
  d=m.comentario(event,'123456');self.assertEqual(d['publico']['id_usuario'],'9999999999999999999');self.assertEqual(d['publico']['id_evento_fuente'],'tiktok');self.assertEqual(d['publico']['avatar'],'https://p16.tiktokcdn.com/foto.png');self.assertEqual(d['publico']['sha256_texto_original'],m.hashlib.sha256('Hola 😀'.encode()).hexdigest())
 def test_missing_event_id_and_clipped_text_are_explicit(self):
  d=m.comentario(S(user=None,common=None,content='a'*4100),'123456')
  self.assertEqual(len(d['texto']),4000);self.assertTrue(d['publico']['texto_recortado']);self.assertEqual(d['publico']['id_evento_fuente'],'generado_por_adaptador');self.assertEqual(d['publico']['fecha_evento_origen'],'reloj_capturador')
 def test_actual_sdk_701_proto_fields_for_public_user_id_and_avatar(self):
  from TikTokLive.events import CommentEvent
  from TikTokLive.proto import CommonMessageData,ImageModel
  from TikTokLive.proto.custom_proto import ExtendedUser
  user=ExtendedUser(id=7000000000000000001,display_id='usuario_real_proto',nickname='Nombre público',avatar_thumb=ImageModel(url_list=['https://p16.tiktokcdn.com/foto.png']))
  event=CommentEvent(user=user,common=CommonMessageData(msg_id=7000000000000000002,create_time=1730000000),content='Comentario de prueba',content_language='es')
  d=m.comentario(event,'7333333333333333333');self.assertEqual(d['autor'],'usuario_real_proto');self.assertEqual(d['publico']['id_usuario'],'7000000000000000001');self.assertEqual(d['id'],'7000000000000000002');self.assertEqual(d['publico']['avatar'],'https://p16.tiktokcdn.com/foto.png')
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
class PublicLiveTest(unittest.TestCase):
 def test_public_events_are_captured_even_if_a_profile_is_private(self):
  from TikTokLive.events import ConnectEvent,CommentEvent,LiveEndEvent
  class Client:
   def __init__(self,**kwargs):self.handlers={};self.room_id=123456;self.room_info={'owner':{'nickname':'Nombre público','private_account':True},'title':'LIVE público'}
   def on(self,event):
    def register(callback):self.handlers[event]=callback;return callback
    return register
   async def connect(client,**kwargs):
    self.assertTrue(kwargs['process_connect_events'])
    await client.handlers[ConnectEvent](S(room_id=123456))
    await client.handlers[CommentEvent](S(content='Primer comentario público',user=S(unique_id='persona_privada',nickname='Persona',private_account=True),common=S(msg_id=7,create_time=1730000000)))
    await client.handlers[LiveEndEvent](None)
   async def disconnect(self,**kwargs):pass
  out=io.StringIO()
  with patch('TikTokLive.TikTokLiveClient',Client),redirect_stdout(out):asyncio.run(m.ejecutar('demo'))
  rows=[json.loads(x)for x in out.getvalue().splitlines()]
  self.assertEqual(len([r for r in rows if r['tipo']=='inicio']),1);self.assertEqual([r['texto']for r in rows if r['tipo']=='comentario'],['Primer comentario público']);self.assertFalse(any(r.get('estado')=='restringido'for r in rows))
 def test_offline_html_is_cross_checked_without_disabling_access_checks(self):
  from TikTokLive.client.errors import UserOfflineError
  from unittest.mock import AsyncMock
  async def run():
   listo=asyncio.Event();calls=[]
   class Client:
    web=object()
    async def connect(self,**kwargs):
     calls.append(kwargs)
     if len(calls)==1:raise UserOfflineError()
     listo.set()
   data={'data':{'user':{'roomId':'123456','nickname':'Cuenta pública'}}}
   with patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value=data)),redirect_stdout(io.StringIO()):await m.conectar_con_comprobacion(Client(),listo,'demo')
   self.assertEqual(len(calls),2);self.assertEqual(calls[1]['room_id'],123456);self.assertTrue(calls[1]['fetch_live_check'])
  asyncio.run(run())
 def test_hls_only_accepts_public_tiktok_cdn_hosts(self):
  good='https://pull-hls-test.tiktokcdn.com/live/index.m3u8?token=example'
  self.assertEqual(m.video_publico({'stream_url':{'hls_pull_url':good}}),good)
  for url in ['http://pull.tiktokcdn.com/live.m3u8','https://127.0.0.1/a','https://pull.tiktokcdn.com.evil.test/a','https://user:pass@pull.tiktokcdn.com/a']:
   self.assertIsNone(m.video_publico({'stream_url':{'hls_pull_url':url}}))
 def test_public_profile_has_no_invented_region_or_private_data(self):
  p=m.perfil_publico({'nickname':'Nombre','signature':'Bio','private_account':True,'region':'NI','password':'SECRET'})
  self.assertEqual(p['region'],'NI');self.assertNotIn('SECRET',json.dumps(p));self.assertIsNone(m.perfil_publico({})['region'])
if __name__=='__main__':unittest.main()
