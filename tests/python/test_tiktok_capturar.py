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
 def test_owner_public_ids_preserve_large_integer_for_creator_matching(self):
  p=m.perfil_publico({'id':9999999999999999999,'display_id':'titular_publico','nickname':'Nombre'})
  self.assertEqual(p['id_usuario'],'9999999999999999999');self.assertEqual(p['usuario'],'titular_publico')
  self.assertEqual(m.perfil_publico({})['id_usuario'],'');self.assertEqual(m.perfil_publico(None),{})
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
 def test_live_access_problem_keeps_monitoring_and_does_not_classify_the_profile_as_private(self):
  from TikTokLive.client.errors import AgeRestrictedError
  d=m.clasificar_error(AgeRestrictedError());self.assertEqual(d['estado'],'acceso_pendiente');self.assertEqual(d['espera_segundos'],300);self.assertIn('no indica que el perfil sea privado',d['detalle']);self.assertIn('activado',d['detalle'])
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
 def test_unresolved_room_keeps_monitoring_without_claiming_the_user_is_not_a_guest(self):
  from TikTokLive.client.errors import UserNotFoundError
  from unittest.mock import AsyncMock
  import httpx
  class Client:
   def __init__(self,**kwargs):self.web=S(get=AsyncMock(return_value=httpx.Response(200,text='<html></html>',request=httpx.Request('GET','https://www.tiktok.com/@demo'))),close=AsyncMock())
   def on(self,event):return lambda callback:callback
   async def connect(self,**kwargs):raise UserNotFoundError('demo')
   async def disconnect(self,**kwargs):pass
  out=io.StringIO()
  with patch('TikTokLive.TikTokLiveClient',Client),patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(side_effect=UserNotFoundError('demo'))),redirect_stdout(out):asyncio.run(m.ejecutar('demo'))
  records=[json.loads(x)for x in out.getvalue().splitlines()]
  self.assertEqual([x for x in records if x['tipo']=='estado'][-1]['estado'],'no_determinado')
  self.assertTrue(any(x.get('codigo')=='sala_no_publicada'for x in records))

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
 def test_automatic_associated_room_captures_comments_and_secondary_hls_without_selecting_host(self):
  from TikTokLive.events import ConnectEvent,CommentEvent,LiveEndEvent
  from TikTokLive.client.errors import UserOfflineError
  from unittest.mock import AsyncMock
  import httpx
  user={'uniqueId':'demo','id':'7000000000000000001','nickname':'Perfil objetivo','roomId':'7777777777777777777','privateAccount':True}
  html='<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__" type="application/json">'+json.dumps({'__DEFAULT_SCOPE__':{'webapp.user-detail':{'userInfo':{'user':user}}}})+'</script>'
  async def get(**kwargs):
   if kwargs['url'].endswith('/api/live/detail/'):
    return httpx.Response(200,json={'LiveRoomInfo':{'status':2,'liveUrl':'https://pull.tiktokcdn.com/demo/live.m3u8'}},request=httpx.Request('GET',kwargs['url']))
   return httpx.Response(200,text=html,request=httpx.Request('GET',kwargs['url']))
  class Client:
   def __init__(self,**kwargs):
    self.handlers={};self.calls=0;self.room_id=7777777777777777777;self.room_info={'owner':{'display_id':'titular_sala','id':7000000000000000002,'nickname':'Titular'},'title':'Sala asociada'};self.web=S(get=AsyncMock(side_effect=get),close=AsyncMock())
   def on(self,event):
    def register(cb):self.handlers[event]=cb;return cb
    return register
   async def connect(client,**kwargs):
    client.calls+=1
    if client.calls==1:raise UserOfflineError()
    self.assertEqual(kwargs['room_id'],7777777777777777777);self.assertTrue(kwargs['fetch_live_check']);self.assertTrue(kwargs['fetch_room_info'])
    await client.handlers[ConnectEvent](S(room_id=client.room_id))
    await client.handlers[CommentEvent](S(content='Comentario público de una cuenta privada',user=S(display_id='comentarista',id=7000000000000000003,nickname='Persona',private_account=True),common=S(msg_id=8000000000000000001,create_time=1730000000)))
    await asyncio.sleep(.02)
    await client.handlers[LiveEndEvent](None)
   async def disconnect(self,**kwargs):pass
  out=io.StringIO()
  with patch('TikTokLive.TikTokLiveClient',Client),patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value={'data':{'user':{'uniqueId':'demo','roomId':'0'}}})),redirect_stdout(out):asyncio.run(m.ejecutar('demo'))
  rows=[json.loads(x)for x in out.getvalue().splitlines()];inicio=next(r for r in rows if r['tipo']=='inicio')
  self.assertEqual(inicio['deteccion']['fuente_sala'],'perfil_publico');self.assertEqual(inicio['perfil_objetivo']['usuario'],'demo');self.assertEqual(inicio['perfil']['usuario'],'titular_sala')
  self.assertEqual([r['texto']for r in rows if r['tipo']=='comentario'],['Comentario público de una cuenta privada']);self.assertEqual(next(r['video_hls']for r in rows if r['tipo']=='video'),'https://pull.tiktokcdn.com/demo/live.m3u8')
 def test_profile_association_fallback_uses_only_target_room_and_preserves_live_check(self):
  from TikTokLive.client.errors import UserOfflineError
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   listo=asyncio.Event();calls=[]
   user={'uniqueId':'demo','roomId':'7777777777777777777','id':'9999999999999999999'}
   html='<script type="application/json" id="__UNIVERSAL_DATA_FOR_REHYDRATION__">'+json.dumps({'__DEFAULT_SCOPE__':{'webapp.user-detail':{'userInfo':{'user':user}}}})+'</script>'
   class Client:
    web=S(get=AsyncMock(return_value=httpx.Response(200,text=html,request=httpx.Request('GET','https://www.tiktok.com/@demo'))))
    async def connect(self,**kwargs):
     calls.append(kwargs)
     if len(calls)==1:raise UserOfflineError()
     listo.set()
   client=Client()
   with patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value={'data':{'user':{'uniqueId':'demo','roomId':'0','status':4}}})),redirect_stdout(io.StringIO()):await m.conectar_con_comprobacion(client,listo,'demo')
   self.assertEqual(calls[1]['room_id'],7777777777777777777);self.assertTrue(calls[1]['fetch_live_check']);self.assertEqual(client._micv_fuente_sala,'perfil_publico');self.assertEqual(client._micv_perfil_objetivo['id_usuario'],'9999999999999999999')
  asyncio.run(run())
 def test_profile_ignores_other_users_and_preserves_exact_ids(self):
  other={'uniqueId':'otro','roomId':'7777777777777777777'}
  html='<script id="SIGI_STATE" type="application/json">'+json.dumps({'UserModule':{'users':{'otro':other}}})+'</script>'
  self.assertIsNone(m.usuario_de_perfil_html(html,'demo'))
  self.assertIsNone(m.sala_de_perfil(other,'demo'))
  for value in [True,1.2345678901234567e18,'0','abc','https://127.0.0.1','1234',None]:self.assertIsNone(m.sala_de_perfil({'roomId':value},'demo'))
  self.assertEqual(m.sala_de_perfil({'uniqueId':'DEMO','roomId':9999999999999999999},'demo'),'9999999999999999999')
 def test_public_api_and_profile_without_association_report_unknown(self):
  from TikTokLive.client.errors import UserOfflineError
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   class Client:
    web=S(get=AsyncMock(return_value=httpx.Response(200,text='<html></html>',request=httpx.Request('GET','https://www.tiktok.com/@demo'))))
    async def connect(self,**kwargs):raise UserOfflineError()
   with patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value={'data':{'user':{'uniqueId':'demo','roomId':'0','status':4}}})),redirect_stdout(io.StringIO()),self.assertRaises(m.SalaNoDeterminada):await m.conectar_con_comprobacion(Client(),asyncio.Event(),'demo')
  asyncio.run(run());d=m.clasificar_error(m.SalaNoDeterminada());self.assertEqual(d['estado'],'no_determinado');self.assertIn('invitado',d['detalle']);self.assertIn('activado',d['detalle'])
 def test_access_restrictions_are_not_retried_through_other_sources(self):
  from TikTokLive.client.errors import AgeRestrictedError
  from unittest.mock import AsyncMock
  async def run():
   client=S(connect=AsyncMock(side_effect=AgeRestrictedError()),web=S(get=AsyncMock()))
   with self.assertRaises(AgeRestrictedError):await m.conectar_con_comprobacion(client,asyncio.Event(),'demo')
   client.web.get.assert_not_awaited()
  asyncio.run(run())
 def test_rate_limit_during_discovery_does_not_trigger_another_profile_request(self):
  from TikTokLive.client.errors import UserOfflineError
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   client=S(connect=AsyncMock(side_effect=UserOfflineError()),web=S(get=AsyncMock()))
   error=httpx.HTTPStatusError('not logged',request=httpx.Request('GET','https://www.tiktok.com/'),response=httpx.Response(429))
   with patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(side_effect=error)),redirect_stdout(io.StringIO()),self.assertRaises(httpx.HTTPStatusError):await m.conectar_con_comprobacion(client,asyncio.Event(),'demo')
   client.web.get.assert_not_awaited()
  asyncio.run(run())
 def test_guest_hls_from_public_detail_is_validated_and_primary_hls_avoids_extra_request(self):
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   url='https://pull.tiktokcdn.com/live/test.m3u8'
   web=S(get=AsyncMock(return_value=httpx.Response(200,json={'LiveRoomInfo':{'status':2,'liveUrl':url}},request=httpx.Request('GET','https://www.tiktok.com/api/live/detail/'))))
   client=S(web=web)
   self.assertEqual(await m.senal_publica(client,{},7777777777777777777),url)
   self.assertEqual(web.get.call_args.kwargs['extra_params']['roomID'],'7777777777777777777')
   web.get.reset_mock();self.assertEqual(await m.senal_publica(client,{'stream_url':{'hls_pull_url':url}},7777777777777777777),url);web.get.assert_not_awaited()
   for d in [{'status':4,'liveUrl':url},{'status':2,'liveUrl':'https://127.0.0.1/test.m3u8'},{'status':2,'liveUrl':url,'prompts':[]}]:
    web.get.return_value=httpx.Response(200,json={'LiveRoomInfo':d},request=httpx.Request('GET','https://www.tiktok.com/api/live/detail/'));self.assertIsNone(await m.senal_publica(client,{},7777777777777777777))
  asyncio.run(run())
 def test_empty_room_metadata_does_not_permanently_classify_a_public_room_as_restricted(self):
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   for data in [{'data':{}},{'data':{'status':2}},{'status_code':500,'data':{}},{'data':[]}]:
    web=S(params={'room_id':'7777777777777777777'},get=AsyncMock(return_value=httpx.Response(200,json=data,request=httpx.Request('GET','https://webcast.tiktok.com/room/info/'))))
    with self.assertRaises(m.InformacionSalaNoDisponible):await m.InfoSalaPublica(web)()
  asyncio.run(run());d=m.clasificar_error(m.InformacionSalaNoDisponible());self.assertEqual(d['estado'],'error');self.assertEqual(d['espera_segundos'],300);self.assertEqual(d['codigo'],'metadatos_no_disponibles')
 def test_explicit_room_prompts_keep_access_restrictions_and_never_fetch_another_source(self):
  from TikTokLive.client.errors import AgeRestrictedError
  from unittest.mock import AsyncMock
  import httpx
  async def run():
   web=S(params={'room_id':'7777777777777777777'},get=AsyncMock(return_value=httpx.Response(200,json={'status_code':4003110,'data':{'prompts':'Login required'}},request=httpx.Request('GET','https://webcast.tiktok.com/room/info/'))))
   with self.assertRaises(AgeRestrictedError):await m.InfoSalaPublica(web)()
   self.assertEqual(web.get.await_count,1)
  asyncio.run(run())
 def test_verified_room_metadata_uses_the_same_public_sdk_endpoint(self):
  from unittest.mock import AsyncMock
  import httpx
  from TikTokLive.client.web.web_settings import WebDefaults
  async def run():
   info={'status':2,'title':'Sala pública','owner':{'display_id':'titular'}}
   web=S(params={'room_id':'7777777777777777777'},get=AsyncMock(return_value=httpx.Response(200,json={'status_code':0,'data':info},request=httpx.Request('GET','https://webcast.tiktok.com/room/info/'))))
   self.assertEqual(await m.InfoSalaPublica(web)(),info);self.assertEqual(web.get.call_args.kwargs['url'],WebDefaults.tiktok_webcast_url+'/room/info/');self.assertEqual(web.get.call_args.kwargs['extra_params']['room_id'],'7777777777777777777')
  asyncio.run(run())
 def test_chat_only_preserves_target_profile_and_records_public_source_without_video_requests(self):
  from TikTokLive.events import ConnectEvent,CommentEvent,LiveEndEvent
  from unittest.mock import AsyncMock
  class Client:
   instance=None
   def __init__(self,**kwargs):
    Client.instance=self;self.handlers={};self.room_id=7777777777777777777;self.room_info=None;self.web=S(get=AsyncMock(side_effect=AssertionError('No video or room metadata request')),close=AsyncMock());self.unique_id=kwargs['unique_id']
   def on(self,event):
    def register(cb):self.handlers[event]=cb;return cb
    return register
   async def connect(client,**kwargs):
    self.assertEqual(client.unique_id,'sala_publica');self.assertFalse(kwargs['fetch_room_info']);self.assertTrue(kwargs.get('fetch_live_check',True))
    await client.handlers[ConnectEvent](S(room_id=client.room_id));await client.handlers[CommentEvent](S(content='Comentario público de perfil privado',user=S(display_id='persona',nickname='Persona',private_account=True),common=S(msg_id=9000000000000000001,create_time=1730000000)));await asyncio.sleep(.02);await client.handlers[LiveEndEvent](None)
   async def disconnect(self,**kwargs):pass
  out=io.StringIO();perfil={'data':{'user':{'uniqueId':'objetivo','nickname':'Perfil objetivo'}}}
  with patch('TikTokLive.TikTokLiveClient',Client),patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value=perfil)),redirect_stdout(out):asyncio.run(m.ejecutar('objetivo','sala_publica'))
  rows=[json.loads(x)for x in out.getvalue().splitlines()];inicio=next(r for r in rows if r['tipo']=='inicio');chat=next(r for r in rows if r['tipo']=='comentario')
  self.assertEqual(inicio['deteccion']['usuario_sala'],'sala_publica');self.assertEqual(inicio['deteccion']['cuenta_consultada'],'objetivo');self.assertTrue(inicio['deteccion']['solo_comentarios']);self.assertIsNone(inicio['video_hls']);self.assertEqual(chat['publico']['usuario_sala'],'sala_publica');self.assertEqual(chat['texto'],'Comentario público de perfil privado');self.assertTrue(any(r.get('perfil',{}).get('nombre')=='Perfil objetivo'for r in rows));Client.instance.web.get.assert_not_awaited()
 def test_chat_only_does_not_create_a_live_when_public_chat_rejects_the_connection(self):
  from unittest.mock import AsyncMock
  import httpx
  class Client:
   def __init__(self,**kwargs):self.web=S(close=AsyncMock())
   def on(self,event):return lambda cb:cb
   async def connect(self,**kwargs):raise httpx.HTTPStatusError('not logged',request=httpx.Request('GET','https://example.test/'),response=httpx.Response(403))
   async def disconnect(self,**kwargs):pass
  out=io.StringIO()
  with patch('TikTokLive.TikTokLiveClient',Client),redirect_stdout(out):asyncio.run(m.ejecutar('objetivo','sala_publica'))
  rows=[json.loads(x)for x in out.getvalue().splitlines()];self.assertFalse(any(r['tipo'] in('inicio','comentario')for r in rows));self.assertTrue(any(r.get('codigo')=='solicitud_rechazada'for r in rows))
 def test_source_profile_is_not_published_over_target_during_chat_room_discovery(self):
  from unittest.mock import AsyncMock
  from TikTokLive.client.errors import UserOfflineError
  async def run():
   listo=asyncio.Event();calls=[]
   class Client:
    web=object()
    async def connect(self,**kwargs):
     calls.append(kwargs)
     if len(calls)==1:raise UserOfflineError()
     listo.set()
   out=io.StringIO()
   with patch('TikTokLive.client.web.routes.fetch_room_id_api.FetchRoomIdAPIRoute.fetch_user_room_data',new=AsyncMock(return_value={'data':{'user':{'uniqueId':'sala_publica','roomId':'7777777777777777777','nickname':'Titular'}}})),redirect_stdout(out):await m.conectar_con_comprobacion(Client(),listo,'sala_publica',solo_comentarios=True,publicar_perfil=False)
   self.assertFalse(any(json.loads(x)['tipo']=='perfil'for x in out.getvalue().splitlines()));self.assertFalse(calls[-1]['fetch_room_info']);self.assertTrue(calls[-1]['fetch_live_check'])
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
