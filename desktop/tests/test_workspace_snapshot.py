import base64,copy,json,tempfile,time,unittest
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from micv_desktop.security import AccessError
from micv_desktop.storage import Store
from micv_desktop.workspace_snapshot import TABLES,verify_snapshot,stage_snapshot
class WorkspaceTests(unittest.TestCase):
 def setUp(self):
  self.key=Ed25519PrivateKey.generate();self.now=int(time.time());self.profile={'server_url':'https://example.test','public_key':base64.b64encode(self.key.public_key().public_bytes_raw()).decode()}
  self.claims={'device':'device','user_id':7,'tenant_id':4,'role':'cyber','valid_until':self.now+600}
  self.data={'format':'micv-workspace-v1','state':'staged','issuer':self.profile['server_url'],**{k:v for k,v in self.claims.items() if k!='valid_until'},'issued_at':self.now,'tables':{k:[] for k in TABLES},'encrypted_fields':{'correo_servicios':['clave_correo'],'jz_mensajes':['texto']},'files':[],'permissions':['clientes.ver']}
  self.data['tables']['users']=[{'id':7,'cyber_id':4}];self.data['tables']['clientes']=[{'id':10,'primer_nombre':'PRIVATE-CUSTOMER'}]
 def envelope(self,data):
  raw=json.dumps(data).encode();return {'payload':base64.b64encode(raw).decode(),'signature':base64.b64encode(self.key.sign(raw)).decode()}
 def verify(self,data):return verify_snapshot(self.envelope(data),self.profile,self.claims,now=self.now)
 def test_encrypted_stage_does_not_touch_active_database(self):
  with tempfile.TemporaryDirectory() as folder:
   store=Store(Path(folder));store.set_meta('profile',self.profile);active=Path(folder)/'database.sqlite';active.write_bytes(b'active')
   class API:
    def call(_,method,path):return self.envelope(self.data)
   self.assertEqual(stage_snapshot(store,API(),self.claims),2);self.assertEqual(active.read_bytes(),b'active');self.assertNotIn(b'PRIVATE-CUSTOMER',store.path.read_bytes())
 def test_invalid_signature_account_time_or_state_is_rejected(self):
  bad=self.envelope(self.data);bad['signature']=base64.b64encode(b'x'*64).decode()
  with self.assertRaises(AccessError):verify_snapshot(bad,self.profile,self.claims)
  for key,value in [('device','other'),('user_id',9),('tenant_id',5),('role','admin'),('issued_at',self.now-301),('state','ready')]:
   data=copy.deepcopy(self.data);data[key]=value
   with self.subTest(key=key),self.assertRaises(AccessError):self.verify(data)
 def test_rejects_credential_tables_foreign_rows_and_missing_parents(self):
  mutations=[lambda t:t.update(sessions=[]),lambda t:t['users'][0].update(password='hash'),lambda t:t['pos_productos'].append({'id':1,'cyber_id':99}),lambda t:t['cliente_estudios'].append({'id':1,'cliente_id':99}),lambda t:t['documentos_bloqueados'].append({'id':1}),lambda t:t['configuracion'].append({'id':1,'smtp_password':'secret'})]
  for mutation in mutations:
   data=copy.deepcopy(self.data);mutation(data['tables'])
   with self.assertRaises(AccessError):self.verify(data)
 def test_duplicate_rows_and_foreign_chat_are_rejected(self):
  data=copy.deepcopy(self.data);data['tables']['clientes']*=2
  with self.assertRaises(AccessError):self.verify(data)
  data=copy.deepcopy(self.data);data['tables']['jz_chats']=[{'id':1,'uno_id':2,'dos_id':3}]
  with self.assertRaises(AccessError):self.verify(data)
 def test_interrupted_download_keeps_previous_stage(self):
  with tempfile.TemporaryDirectory() as folder:
   store=Store(Path(folder));store.set_meta('profile',self.profile);store.set_meta('workspace_staged',{'previous':True})
   class API:
    def call(_,method,path):raise OSError('offline')
   with self.assertRaises(OSError):stage_snapshot(store,API(),self.claims)
   self.assertEqual(store.meta('workspace_staged'),{'previous':True})
 def test_business_snapshot_accepts_only_minimal_linked_portal_accounts(self):
  self.data['permissions'].append('clientes.permisos')
  tables=self.data['tables']
  tables['cliente_cyber']=[{'cliente_id':10,'cyber_id':4}]
  tables['clientes'][0]['user_id']=12
  tables['users'].append({'id':12,'rol':'cliente','edicion_cliente':'["cv"]'})
  self.verify(self.data)
  for mutate in (
   lambda data: data['permissions'].remove('clientes.permisos'),
   lambda data: data['tables']['cliente_cyber'].clear(),
   lambda data: data['tables']['users'][-1].update(chat_foto='fotos/private.png'),
  ):
   data=copy.deepcopy(self.data);mutate(data)
   with self.assertRaises(AccessError):self.verify(data)
 def test_customer_cannot_download_another_cv(self):
  self.claims.update(role='cliente',tenant_id=0);self.data.update(role='cliente',tenant_id=0);self.data['tables']['clientes'][0]['user_id']=7
  self.verify(self.data);self.data['tables']['clientes'][0]['user_id']=8
  with self.assertRaises(AccessError):self.verify(self.data)

 def add_file(self):
  import hashlib
  raw=b'PRIVATE-IMAGE';self.data['tables']['clientes'][0]['foto']='fotos/client.jpg'
  self.data['files']=[{'table':'clientes','key':10,'field':'foto','path':'fotos/client.jpg','size':len(raw),'sha256':hashlib.sha256(raw).hexdigest(),'missing':False}]
  return raw
 def test_assets_are_encrypted_and_successful_refresh_removes_previous_generation(self):
  raw=self.add_file()
  with tempfile.TemporaryDirectory() as folder:
   store=Store(Path(folder));store.set_meta('profile',self.profile)
   class API:
    def call(_,method,path):return self.envelope(self.data)
    def workspace_file(_,item):return raw
   stage_snapshot(store,API(),self.claims);old=store.meta('workspace_staged')['_local_generation']
   path=Path(folder)/'workspace-cache'/old/self.data['files'][0]['sha256']
   self.assertNotIn(raw,path.read_bytes())
   decoded=store.vault.unpack('workspace-file:'+self.data['files'][0]['sha256'],path.read_bytes());self.assertEqual(base64.b64decode(decoded),raw)
   stage_snapshot(store,API(),self.claims);self.assertFalse(path.exists())
 def test_invalid_file_preserves_old_stage_and_cleans_temporary_files(self):
  self.add_file()
  with tempfile.TemporaryDirectory() as folder:
   store=Store(Path(folder));store.set_meta('profile',self.profile);store.set_meta('workspace_staged',{'previous':True})
   class API:
    def call(_,method,path):return self.envelope(self.data)
    def workspace_file(_,item):return b'bad'
   with self.assertRaises(AccessError):stage_snapshot(store,API(),self.claims)
   self.assertEqual(store.meta('workspace_staged'),{'previous':True});self.assertEqual(list((Path(folder)/'workspace-cache').iterdir()),[])
 def test_traversal_unowned_and_missing_file_descriptors_are_rejected(self):
  self.add_file()
  for mutate in [lambda d:d['files'][0].update(path='../key'),lambda d:d['files'][0].update(key=99),lambda d:d.update(files=[])]:
   data=copy.deepcopy(self.data);mutate(data)
   with self.assertRaises(AccessError):self.verify(data)
