from __future__ import annotations
import base64, json, tempfile, time, unittest, uuid, sqlite3
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.hazmat.primitives import serialization
from cryptography.exceptions import InvalidTag
from micv_desktop.storage import Store
from micv_desktop.service import Service
from micv_desktop.security import AccessError, verify_lease, pin_hash
from micv_desktop.sync import Sync
from micv_desktop.api import OfflineError

class DesktopTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.store=Store(Path(self.tmp.name));self.private=Ed25519PrivateKey.generate()
        pub=self.private.public_key().public_bytes(serialization.Encoding.Raw,serialization.PublicFormat.Raw)
        self.profile={'version':1,'server_url':'https://micv.example.test','public_key':base64.b64encode(pub).decode()}
        self.claims={'version':1,'issuer':self.profile['server_url'],'device':self.store.meta('device_uuid'),'license_id':1,'tenant_id':1,'user_id':1,'issued_at':int(time.time()),'valid_until':int(time.time())+86400,'scopes':['clientes.ver','clientes.editar','pos.vender','pos.caja','pos.caja.cerrar','contabilidad.ver','contabilidad.registrar','documentos'],'nombre':'Prueba','negocio':'Negocio'}
        self.business={'id':1,'nombre':'Negocio','pais':'CR','moneda':'CRC','simbolo':'₡','decimales':2,'impuesto_tasa':'0','precios_con_impuesto':True,'contabilidad_pos':True,'metodos':[{'clave':'efectivo','nombre':'Efectivo'},{'clave':'tarjeta','nombre':'Tarjeta'}]}
        self.response={'token':'secret-token-test','lease':self.sign(self.claims),'business':self.business,'catalogs':{'countries':{'CR':{'nombre':'Costa Rica','documentos':{'dimex':'DIMEX'}},'NI':{'nombre':'Nicaragua','documentos':{'cedula':'Cédula'}}},'nationalities':[{'c':'NI','m':'Nicaragüense','f':'Nicaragüense','p':'Nicaragua'}]},'cash':{'id':1,'monto_inicial':'0','esperado':'0'},'server_time':int(time.time())}
        self.store.activate(self.profile,self.response,pin_hash('123456'));self.service=Service(self.store)
        self.store.replace('products',[{'id':1,'data':{'nombre':'Producto','precio':'10.00','stock':'5','controla_stock':True,'impuesto_tasa':None,'exento':False}}])
    def sign(self,claims):
        raw=json.dumps(claims).encode();return {'payload':base64.b64encode(raw).decode(),'signature':base64.b64encode(self.private.sign(raw)).decode()}
    def test_signed_lease_cannot_be_extended_or_used_on_another_device(self):
        envelope=self.sign(dict(self.claims,valid_until=self.claims['valid_until']+1));envelope['signature']=self.response['lease']['signature']
        with self.assertRaises(AccessError):verify_lease(envelope,self.profile,self.claims['device'])
        with self.assertRaises(AccessError):verify_lease(self.response['lease'],self.profile,str(uuid.uuid4()))
    def test_expiry_clock_rollback_and_revocation_preserve_local_data(self):
        self.store.queue('ledger.create',{'concepto':'Pendiente real'})
        self.store.set_meta('lease',self.sign(dict(self.claims,issued_at=int(time.time())-100,valid_until=int(time.time())-1)))
        with self.assertRaises(AccessError):self.service.guard('contabilidad.registrar')
        self.store.set_meta('lease',self.response['lease']);self.store.set_meta('last_clock',time.time()+3600)
        with self.assertRaises(AccessError):self.service.guard('pos.vender')
        self.store.set_meta('last_clock',time.time());self.store.set_meta('blocked',True)
        with self.assertRaises(AccessError):self.service.guard('pos.vender')
        self.assertEqual(len(self.store.operations()),1)
    def test_encryption_pin_and_backup(self):
        op=self.store.queue('client.create',{'client':{'correo':'privado@example.test'}})
        self.assertNotIn(b'privado@example.test',self.store.path.read_bytes())
        self.store.unlock('123456')
        with self.assertRaises(AccessError):self.store.unlock('wrong-pin')
        output=Path(self.tmp.name)/'backup.zip';self.store.backup(output)
        import zipfile
        with zipfile.ZipFile(output) as z:self.assertEqual(set(z.namelist()),{'micv.sqlite','local.key','LEAME.txt'})
        self.assertEqual(Store(Path(self.tmp.name)).operations()[0]['uuid'],op['uuid'])
    def test_pending_sale_reserves_stock_and_linked_ledger(self):
        self.service.sale([{'producto_id':1,'cantidad':'2'}],[{'metodo':'efectivo','monto':'20'}])
        self.assertEqual(self.service.effective('products')[0]['data']['stock'],'3.00')
        self.assertEqual(self.service.effective('ledger')[0]['data']['monto'],'20.00')
        with self.assertRaises(AccessError):self.service.sale([{'producto_id':1,'cantidad':'4'}],[{'metodo':'efectivo','monto':'40'}])
        with self.assertRaises(AccessError):self.service.sale([{'producto_id':1,'cantidad':'1'}],[{'metodo':'tarjeta','monto':'11'}])
    def test_ack_before_pull_keeps_stock_reservation(self):
        op=self.service.sale([{'producto_id':1,'cantidad':'1'}],[{'metodo':'efectivo','monto':'10'}]);self.store.ack({'uuid':op['uuid'],'status':'accepted','entity_id':8})
        self.assertEqual(self.service.effective('products')[0]['data']['stock'],'4.00')
        self.store.replace('products',[{'id':1,'data':{'nombre':'Producto','precio':'10','stock':'4','controla_stock':True}}])
        self.assertEqual(self.service.effective('products')[0]['data']['stock'],'4')
    def test_offline_reconnect_and_lost_ack_retry_are_idempotent(self):
        op=self.service.ledger({'tipo':'ingreso','categoria':'Servicios','concepto':'Ingreso local','monto':'25.50','fecha':'2026-11-03'})
        server={'offline':True,'lost':True,'receipts':set(),'effects':0}
        store=self.store;response=self.response
        class FakeApi:
            def __init__(self,*args):pass
            def close(self):pass
            def call(self,method,path,**kwargs):
                if server['offline']:raise OfflineError('offline')
                if path=='renew':return response
                if path=='catalog':return {'rows':[],'next':None}
                results=[]
                for item in kwargs['json']['operations']:
                    if item['uuid'] not in server['receipts']:server['effects']+=1;server['receipts'].add(item['uuid'])
                    results.append({'uuid':item['uuid'],'status':'accepted','entity_id':1})
                if server['lost']:server['lost']=False;raise OfflineError('lost acknowledgment')
                return {'results':results}
        sync=Sync(store,FakeApi)
        with self.assertRaises(OfflineError):sync.run()
        server['offline']=False
        with self.assertRaises(OfflineError):sync.run()
        self.assertEqual(store.operations()[0]['status'],'pending');sync.run()
        self.assertEqual(server['effects'],1);self.assertEqual(store.operations(),[])
    def test_conflict_is_retained_and_can_be_retried_without_changing_payload(self):
        op=self.store.queue('client.update',{'id':1,'revision':'old','client':{'primer_nombre':'Nombre'}})
        self.store.ack({'uuid':op['uuid'],'status':'conflict','message':'Cambió en servidor'})
        self.assertEqual(self.store.operations()[0]['error'],'Cambió en servidor');self.store.retry(op['uuid'])
        self.assertEqual(self.store.operations()[0]['data'],op['data']);self.assertEqual(self.store.operations()[0]['uuid'],op['uuid'])
    def test_ciphertext_cannot_be_swapped_between_clients(self):
        self.store.replace('clients',[{'id':1,'data':{'primer_nombre':'Uno'}},{'id':2,'data':{'primer_nombre':'Dos'}}])
        with self.store.connection() as db:
            value=db.execute("SELECT value FROM cache WHERE id='1'").fetchone()['value'];db.execute("UPDATE cache SET value=? WHERE id='2'",(value,))
        with self.assertRaises(InvalidTag):self.store.rows('clients')
    def test_nationality_and_residence_are_independent_from_document(self):
        data={'pais':'CR','tipo_documento':'dimex','numero_documento':'12345678901','primer_nombre':'Persona','primer_apellido':'Prueba','nacionalidad':'Nicaragüense','pais_residencia':'CR','autorizacion_laboral':'En trámite'}
        op=self.service.client(data);self.assertEqual(op['data']['client']['nacionalidad'],'Nicaragüense');self.assertEqual(op['data']['client']['pais'],'CR')

if __name__=='__main__':unittest.main()
