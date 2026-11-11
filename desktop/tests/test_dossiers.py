import base64,json,tempfile,time,unittest
from pathlib import Path
from unittest.mock import Mock
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives.serialization import Encoding,PublicFormat
from micv_desktop.storage import Store
from micv_desktop.security import AccessError
from micv_desktop.dossiers import verify_page,download_dossiers
class DossierTests(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.store=Store(Path(self.tmp.name));self.key=Ed25519PrivateKey.generate()
  self.profile={'server_url':'https://example.test','public_key':base64.b64encode(self.key.public_key().public_bytes(Encoding.Raw,PublicFormat.Raw)).decode()};self.store.set_meta('profile',self.profile)
  self.claims={'device':'device','user_id':1,'tenant_id':2,'role':'cliente','valid_until':int(time.time())+600}
  self.row={'id':7,'revision':'a'*64,'data':{'client':{'id':7,'user_id':1,'primer_nombre':'Privado'},'estudios':[{'id':8,'cliente_id':7,'titulo':'Curso'}],'experiencias':[],'idiomas':[],'habilidades':[],'referencias':[]}}
  self.page={**self.claims,'format':'micv-dossiers-v1','issuer':self.profile['server_url'],'issued_at':int(time.time()),'after':0,'next':None,'rows':[self.row]}
 def signed(self):
  data=json.dumps(self.page).encode();return {'payload':base64.b64encode(data).decode(),'signature':base64.b64encode(self.key.sign(data)).decode()}
 def test_signed_download_replaces_cache_only_after_success(self):
  api=Mock();api.call.return_value=self.signed();self.assertEqual(download_dossiers(self.store,api,self.claims),1)
  self.assertEqual(self.store.rows('dossiers')[0]['data']['estudios'][0]['titulo'],'Curso')
  self.assertNotIn(b'Privado',self.store.path.read_bytes())
 def test_tampered_or_wrong_account_page_does_not_replace_previous_data(self):
  self.store.replace('dossiers',[self.row]);expected=self.store.rows('dossiers')
  for key in ('device','user_id','tenant_id','role','issuer'):
   original=self.page[key];self.page[key]='other';api=Mock();api.call.return_value=self.signed()
   with self.assertRaises(AccessError):download_dossiers(self.store,api,self.claims)
   self.assertEqual(self.store.rows('dossiers'),expected);self.page[key]=original
  bad=self.signed();bad['payload']=base64.b64encode(b'{}').decode()
  with self.assertRaises(AccessError):verify_page(bad,self.profile,self.claims,0)
 def test_other_clients_child_records_and_repeated_cursor_are_rejected(self):
  self.row['data']['estudios'][0]['cliente_id']=99
  with self.assertRaises(AccessError):verify_page(self.signed(),self.profile,self.claims,0)
  self.row['data']['estudios'][0]['cliente_id']=7;self.page['next']=0
  with self.assertRaises(AccessError):verify_page(self.signed(),self.profile,self.claims,0)
 def test_interrupted_pagination_keeps_complete_previous_cache(self):
  self.store.replace('dossiers',[self.row]);self.page['next']=7;api=Mock();api.call.side_effect=[self.signed(),RuntimeError('Offline')]
  with self.assertRaises(RuntimeError):download_dossiers(self.store,api,self.claims)
  self.assertEqual(self.store.rows('dossiers'),[self.row])
 def test_expired_lease_and_old_reply_are_rejected(self):
  with self.assertRaises(AccessError):verify_page(self.signed(),self.profile,dict(self.claims,valid_until=0),0)
  self.page['issued_at']=0
  with self.assertRaises(AccessError):verify_page(self.signed(),self.profile,self.claims,0)
