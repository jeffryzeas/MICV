from contextlib import closing
import base64
import hashlib
import json
import os
from pathlib import Path
import sqlite3
import tempfile
import time
import unittest

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

from micv_desktop.local_engine import LocalEngine
from micv_desktop.security import AccessError, pin_hash
from micv_desktop.storage import Store
from micv_desktop.workspace_import import provision_workspace
from micv_desktop.workspace_snapshot import stage_snapshot, TABLES


def fixture_png():
    import struct, zlib
    def chunk(tag, data):
        return struct.pack('>I', len(data)) + tag + data + struct.pack('>I', zlib.crc32(tag+data))
    return (b'\x89PNG\r\n\x1a\n'
            + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00'))
            + chunk(b'IEND', b''))


@unittest.skipUnless(os.environ.get('MICV_TEST_BUNDLE'), 'Requires a local PHP/Laravel bundle')
class WorkspaceImportTests(unittest.TestCase):
    def test_import_checks_references_and_preserves_previous_workspace(self):
        bundle = Path(os.environ['MICV_TEST_BUNDLE'])
        with tempfile.TemporaryDirectory() as directory:
            store = Store(Path(directory))
            key = Ed25519PrivateKey.generate()
            issuer = 'https://example.test'
            profile = {'server_url': issuer, 'public_key': base64.b64encode(key.public_key().public_bytes_raw()).decode()}
            now = int(time.time())
            claims = dict(version=1, issuer=issuer, device=store.meta('device_uuid'), user_id=7, tenant_id=0, role='cliente',
                          issued_at=now, valid_until=now+600, scopes=['clientes.ver'])

            def sign(value):
                raw = json.dumps(value).encode()
                return {'payload': base64.b64encode(raw).decode(), 'signature': base64.b64encode(key.sign(raw)).decode()}

            store.set_meta('profile', profile)
            store.set_meta('lease', sign(claims))
            store.set_meta('pin', pin_hash('123456'))
            tables = {table: [] for table in TABLES}
            tables['users'] = [{'id': 7, 'usuario': 'cliente', 'name': 'Cliente', 'email': 'cliente@example.test', 'rol': 'cliente', 'activo': 1}]
            tables['users'][0].update(email_verified_at='2026-01-01 00:00:00', terminos_version='1.0', edicion_cliente='["cv","foto"]')
            tables['clientes'] = [{'id': 2, 'user_id': 7, 'pais': 'CR', 'tipo_documento': 'otro', 'numero_documento': 'LOCAL-TEST',
                                  'primer_nombre': 'Ana', 'primer_apellido': 'Prueba'}]
            tables['jz_chats'] = [{'id': 1, 'uno_id': 7, 'dos_id': 9}]
            tables['jz_mensajes'] = [{'id': 1, 'chat_id': 1, 'user_id': 9, 'clave': 'key', 'texto': 'mensaje privado', 'archivo': 'chat/fixture.pdf', 'created_at': '2026-01-01 00:00:00'}]
            raw_file = b'fixture'
            digest = hashlib.sha256(raw_file).hexdigest()
            snapshot = dict(claims, format='micv-workspace-v1', state='staged', tables=tables, permissions=[],
                            encrypted_fields={'correo_servicios': ['clave_correo'], 'jz_mensajes': ['texto']},
                            files=[dict(table='jz_mensajes', key=1, field='archivo', path='chat/fixture.pdf',
                                        missing=False, size=len(raw_file), sha256=digest)])

            class FixtureApi:
                def call(self, method, path):
                    return sign(snapshot)

                def workspace_file(self, item):
                    return raw_file

            stage_snapshot(store, FixtureApi(), claims)
            space = provision_workspace(store, bundle)
            self.assertNotIn('app_key', json.loads((space / 'workspace.json').read_text()))
            app, env = LocalEngine(bundle, space, lambda: claims).configuration()
            self.assertTrue(env['APP_KEY'].startswith('base64:'))
            self.assertEqual((space / 'storage/app/private/chat/fixture.pdf').read_bytes(), raw_file)
            with closing(sqlite3.connect(space / 'database.sqlite')) as db, db:
                self.assertEqual(db.execute('PRAGMA foreign_key_check').fetchall(), [])
                self.assertEqual(db.execute('SELECT activo FROM users WHERE id=9').fetchone(), (0,))
                self.assertNotIn('mensaje privado', db.execute('SELECT texto FROM jz_mensajes').fetchone()[0])

            # Download the complete row that the server fingerprints, including database defaults.
            server_space = Path(directory) / 'server'
            space.rename(server_space)
            with closing(sqlite3.connect(server_space / 'database.sqlite')) as db, db:
                db.row_factory = sqlite3.Row
                tables['clientes'][0] = dict(db.execute('SELECT * FROM clientes WHERE id=2').fetchone())
                bearer = 'b' * 64
                db.execute(
                    "INSERT INTO desktop_devices (user_id,uuid,nombre,token_hash,access_role,created_at) VALUES (?,?,?,?,?,?)",
                    (7, claims['device'], 'Fixture', hashlib.sha256(bearer.encode()).hexdigest(), 'cliente', '2025-01-01 00:00:00'),
                )
            stage_snapshot(store, FixtureApi(), claims)
            space = provision_workspace(store, bundle)
            import httpx
            store.set_meta('workspace_active', space.name)
            from micv_desktop.workspace_access import open_workspace
            engine = open_workspace(store, bundle)
            try:
                with httpx.Client(trust_env=False, follow_redirects=False) as client:
                    denied = client.get(engine.origin + '/inicio')
                    self.assertEqual(denied.status_code, 403)
                    response = client.get(engine.origin + '/inicio', headers={'X-MiCV-Local': engine.token})
                    self.assertIn(response.status_code, (200, 302))
                    self.assertNotIn('/login', response.headers.get('location', ''))
                    import re
                    headers = {'X-MiCV-Local': engine.token}
                    os.environ.setdefault('QT_QPA_PLATFORM', 'offscreen')
                    from PySide6.QtWidgets import QApplication
                    from micv_desktop.letter_editor import LetterEditor
                    from unittest.mock import patch
                    app = QApplication.instance() or QApplication([])
                    editor = LetterEditor(engine, store, kind='solicitud_empleo')
                    try:
                        editor.fields['lugar'].setText('La Dalia')
                        editor.load_template()
                        self.assertIn('La Dalia', editor.editor.toPlainText())
                        editor.editor.append('Párrafo redactado dentro de Mi CV.')
                        with patch('micv_desktop.letter_editor.QInputDialog.getText', return_value=('Carta de prueba', True)):
                            editor.save_draft()
                        self.assertIn('Párrafo redactado', next(iter(store.meta('letter_drafts').values()))['html'])
                        pdf = Path(directory)/'carta.pdf'
                        with patch('micv_desktop.letter_editor.QFileDialog.getSaveFileName', return_value=(str(pdf), 'PDF')):
                            editor.export_pdf()
                        self.assertTrue(pdf.read_bytes().startswith(b'%PDF-'))
                    finally:
                        editor.http.close()
                        editor.editor.document().setModified(False)
                        editor.close()
                    page = client.get(engine.origin+'/mensajes', headers=headers)
                    self.assertEqual(page.status_code,200,page.text)
                    self.assertIn('class="chat-rail"',page.text)
                    self.assertIn('data-chat-rail="no-leidos"',page.text)
                    if os.environ.get('MICV_CHAT_QA'):
                        Path(os.environ['MICV_CHAT_QA']).write_text(page.text,encoding='utf-8')
                    form = client.get(engine.origin + '/mi-cv/datos', headers=headers)
                    self.assertEqual(form.status_code, 200, form.headers.get('location'))
                    csrf = re.search(r'name="_token" value="([^"]+)"', form.text).group(1)
                    history = client.get(engine.origin+'/mensajes/1/historial', headers=headers)
                    self.assertEqual(history.status_code,200,history.text)
                    self.assertEqual(history.json()['mensajes'][0]['texto'],'mensaje privado')
                    chat_headers = headers | {'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
                    read = client.post(engine.origin+'/mensajes/1/leer',json={'hasta':1},headers=chat_headers)
                    self.assertEqual(read.status_code,200,read.text)
                    forbidden = client.post(engine.origin+'/mensajes/1/enviar',json={'texto':'No enviar'},headers=chat_headers)
                    self.assertEqual(forbidden.status_code,403,forbidden.text)
                    payload = {'_token': csrf, 'estudios': [{'nivel':'curso','titulo':'Contabilidad local'}]}
                    change = client.put(engine.origin + '/mi-cv/datos', json=payload, headers=headers)
                    self.assertEqual(change.status_code, 302)
                    from micv_desktop.workspace_access import import_workspace_edits
                    import_workspace_edits(store, bundle)
                    import_workspace_edits(store, bundle)
                    pending = store.operations()
                    self.assertEqual(len(pending), 1)
                    self.assertEqual(pending[0]['data']['client']['estudios'][0]['titulo'], 'Contabilidad local')
                    with closing(sqlite3.connect(space / 'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT titulo FROM cliente_estudios').fetchone(), ('Contabilidad local',))

                    server = LocalEngine(bundle, server_space, lambda: claims)
                    configuration = server.configuration
                    def test_server_configuration():
                        app, env = configuration()
                        env['DESKTOP_REQUIRE_HTTPS'] = 'false'
                        return app, env
                    server.configuration = test_server_configuration
                    server.start()
                    try:
                        remote_headers = {'X-MiCV-Local': server.token, 'Authorization': 'Bearer '+bearer}
                        def send(operation):
                            operation = {key:operation[key] for key in ('uuid','type','occurred_at','data')}
                            result = client.post(server.origin+'/api/desktop/v1/push',
                                                 json={'operations':[operation]}, headers=remote_headers)
                            self.assertEqual(result.status_code, 200, result.text)
                            receipt = result.json()['results'][0]
                            self.assertEqual(receipt['status'], 'accepted', receipt)
                            return receipt
                        receipt = send(pending[0])
                        self.assertEqual(send(pending[0]), receipt)
                        store.ack(receipt)
                        import_workspace_edits(store, bundle)
                        self.assertEqual(store.operations(), [])
                        server.stop()
                        with self.assertRaises(httpx.ConnectError):
                            client.get(server.origin+'/_desktop/health',headers=remote_headers)
                        # The local session stays reachable and is restartable without the server.
                        self.assertEqual(client.get(engine.origin+'/mi-cv/datos',headers=headers).status_code,200)
                        engine.stop()
                        engine = open_workspace(store, bundle)
                        headers = {'X-MiCV-Local':engine.token}
                        form = client.get(engine.origin+'/mi-cv/datos',headers=headers)
                        self.assertEqual(form.status_code,200,form.text)
                        csrf = re.search(r'name="_token" value="([^"]+)"', form.text).group(1)
                        payload['_token'] = csrf

                        # The PHP process can accept a renewed lease without restarting.
                        local_origin = engine.origin
                        engine.publish_expiry(int(time.time())-1)
                        self.assertEqual(client.get(local_origin+'/_desktop/health',headers=headers).status_code,403)
                        engine.refresh_authorization()
                        self.assertEqual(engine.origin,local_origin)
                        self.assertEqual(client.get(local_origin+'/mi-cv/datos',headers=headers).status_code,200)
                        signed = engine.validity_file.read_bytes()
                        engine.validity_file.write_bytes(b'999999999999:'+b'0'*64)
                        self.assertEqual(client.get(local_origin+'/_desktop/health',headers=headers).status_code,403)
                        engine.validity_file.write_bytes(signed)
                        payload['estudios'][0]['titulo'] = 'Contabilidad revisada'
                        if os.environ.get('MICV_TEST_UPLOAD'):
                            picture = fixture_png()
                            change = client.post(engine.origin+'/mi-cv/datos',
                                                 data={'_method':'PUT','_token':csrf,'estudios[0][nivel]':'curso',
                                                       'estudios[0][titulo]':'Contabilidad revisada'},
                                                 files={'foto':('fixture.png', picture, 'image/png')}, headers=headers)
                        else:
                            change = client.put(engine.origin+'/mi-cv/datos', json=payload, headers=headers)
                        self.assertEqual(change.status_code, 302, change.text)
                        self.assertEqual(store.operations(), [])
                        engine.stop()
                        from micv_desktop.profile_backup import restore_workspace, SEALED
                        import zipfile
                        backup = Path(directory)/'offline.zip'
                        from micv_desktop.chat_drafts import ChatDrafts
                        ChatDrafts(store).save(None, 'Contacto de prueba', 'Texto durante el apagón')
                        store.backup(backup)
                        with zipfile.ZipFile(backup) as archive:
                            self.assertNotIn('database.sqlite', archive.namelist())
                            self.assertNotIn(b'Contabilidad revisada', archive.read(SEALED))
                        accounts = Path(directory)/'recovery'
                        with self.assertRaises(AccessError):
                            restore_workspace(backup, accounts, 'wrong-pin')
                        self.assertEqual(list(accounts.iterdir()), [])
                        tampered = Path(directory)/'tampered.zip'
                        with zipfile.ZipFile(backup) as source, zipfile.ZipFile(tampered, 'w') as target:
                            for name in source.namelist():
                                data = source.read(name)
                                if name == SEALED:
                                    data = data[:-1] + bytes([data[-1] ^ 1])
                                target.writestr(name, data)
                        with self.assertRaises(AccessError):
                            restore_workspace(tampered, accounts, '123456')
                        self.assertEqual(list(accounts.iterdir()), [])
                        recovered = restore_workspace(backup, accounts, '123456')
                        self.assertIn('Párrafo redactado', next(iter(recovered.meta('letter_drafts').values()))['html'])
                        self.assertEqual(ChatDrafts(recovered).rows()[0]['text'], 'Texto durante el apagón')
                        restored = recovered.folder/'workspaces'/space.name
                        self.assertEqual((restored/'storage/app/private/chat/fixture.pdf').read_bytes(), raw_file)
                        running = open_workspace(recovered,bundle)
                        try:
                            form = client.get(running.origin+'/mi-cv/datos',headers={'X-MiCV-Local':running.token})
                            self.assertEqual(form.status_code,200,form.text)
                            self.assertIn('Contabilidad revisada',form.text)
                        finally:
                            running.stop()
                        import_workspace_edits(recovered,bundle)
                        [restored_op] = recovered.operations()
                        self.assertEqual(restored_op['data']['revision'], receipt['revision'])
                        if os.environ.get('MICV_TEST_UPLOAD'):
                            self.assertEqual(base64.b64decode(restored_op['data']['photo']['content']), picture)
                        import_workspace_edits(store, bundle)
                        [original_op] = store.operations()
                        self.assertEqual(restored_op['uuid'], original_op['uuid'])
                        server.start()
                        remote_receipt = send(restored_op)
                        recovered.ack(remote_receipt)
                        import_workspace_edits(recovered,bundle)
                        self.assertEqual(recovered.operations(), [])
                        store.ack(remote_receipt)
                        import_workspace_edits(store, bundle)
                        with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                            self.assertEqual(db.execute('SELECT titulo FROM cliente_estudios').fetchall(), [('Contabilidad revisada',)])
                            self.assertEqual(db.execute('SELECT COUNT(*) FROM desktop_receipts').fetchone(), (2,))
                            if os.environ.get('MICV_TEST_UPLOAD'):
                                path = db.execute('SELECT foto FROM clientes WHERE id=2').fetchone()[0]
                                self.assertTrue((server_space/'storage/app/private'/path).is_file())
                    finally:
                        server.stop()


            finally:
                engine.stop()

            before = (space / 'database.sqlite').read_bytes()
            staged = store.meta('workspace_staged')
            staged['tables']['users'][0]['unknown_column'] = 1
            store.set_meta('workspace_staged', staged)
            with self.assertRaises(AccessError):
                provision_workspace(store, bundle)
            self.assertEqual(list((store.folder / 'workspaces').iterdir()), [space])
            self.assertEqual((space / 'database.sqlite').read_bytes(), before)

    def test_business_operations_sync_in_their_original_order(self):
        import re
        import uuid
        import httpx
        from micv_desktop.workspace_access import open_workspace, import_workspace_edits

        bundle = Path(os.environ['MICV_TEST_BUNDLE'])
        with tempfile.TemporaryDirectory() as directory:
            store = Store(Path(directory))
            key = Ed25519PrivateKey.generate()
            issuer = 'https://example.test'
            store.set_meta('profile', {'server_url': issuer, 'public_key': base64.b64encode(key.public_key().public_bytes_raw()).decode()})
            permissions = ['clientes.ver','clientes.editar','clientes.permisos','autoservicio','fotos','tarjetas','contabilidad.ver','contabilidad.registrar','contabilidad.anular','pos.vender','pos.caja','pos.reportes',
                           'pos.caja.movimientos','pos.caja.cerrar','pos.ventas','pos.anular',
                           'pos.compras','pos.compras.editar','pos.compras.anular','pos.productos','pos.productos.editar','pos.proveedores','pos.ajustar','pos.config','marca','personal']
            now = int(time.time())
            claims = dict(version=1, issuer=issuer, device=store.meta('device_uuid'), user_id=7, tenant_id=4, role='cyber',
                          issued_at=now, valid_until=now+600, scopes=permissions, permissions=permissions)

            def sign(value):
                raw = json.dumps(value).encode()
                return {'payload': base64.b64encode(raw).decode(), 'signature': base64.b64encode(key.sign(raw)).decode()}

            store.set_meta('lease', sign(claims))
            tables = {table: [] for table in TABLES}
            tables['cybers'] = [{'id':4,'codigo':'LOCAL','nombre':'Local','activo':1,'pais':'NI','contabilidad_pos':1}]
            tables['users'] = [{'id':7,'cyber_id':4,'usuario':'operador','name':'Operador','email':'local@example.test',
                               'rol':'cyber','activo':1,'terminos_version':'1.0','permisos':json.dumps(permissions)}]
            tables['users'].append({'id':8,'cyber_id':4,'encargado_id':7,'usuario':'CAJERO',
                                    'name':'Cajero local','email':'cajero@example.test','rol':'cyber',
                                    'activo':1,'permisos':'["documentos"]'})
            from datetime import datetime, timezone
            date = datetime.now(timezone.utc).date().isoformat()
            tables['autoservicio_solicitudes'] = [
                {'id':i,'cyber_id':4,'fecha':date,'turno':i,'nombre':'Ana Prueba',
                 'datos':json.dumps({'primer_nombre':'Ana','primer_apellido':'Prueba'}),
                 'estado':'pendiente','created_at':date+' 12:00:00'}
                for i in (1, 2, 3)
            ]
            tables['pos_productos'] = [{'id':3,'cyber_id':4,'nombre':'Papel','precio':10,'controla_stock':1,'stock':8,'stock_minimo':12,'exento':1}]
            snapshot = dict(claims, format='micv-workspace-v1', state='staged', tables=tables, files=[],
                            encrypted_fields={'correo_servicios':['clave_correo'],'jz_mensajes':['texto']})

            class FixtureApi:
                def call(self, method, path):
                    return sign(snapshot)

            stage_snapshot(store, FixtureApi(), claims)
            server_space = provision_workspace(store, bundle)
            local = provision_workspace(store, bundle)
            with closing(sqlite3.connect(local/'database.sqlite')) as db, db:
                db.execute("DROP TABLE pos_pendientes")
                db.execute("DELETE FROM migrations WHERE migration = '2026_11_11_000002_create_pos_pendientes'")
            store.set_meta('workspace_active', local.name)
            bearer = 'c' * 64
            with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                db.execute("INSERT INTO pos_productos (id,cyber_id,nombre,precio) VALUES (4,4,'Producto de la web',20)")
                db.execute("INSERT INTO pos_categorias (id,cyber_id,nombre) VALUES (1,4,'Categoría web')")
                db.execute("INSERT INTO pos_proveedores (id,cyber_id,nombre) VALUES (1,4,'Proveedor web')")

                db.execute("INSERT INTO clientes (id,pais,tipo_documento,numero_documento,primer_nombre,primer_apellido) "
                           "VALUES (1,'CR','cedula_cr','203450678','Cliente web','Prueba')")
                db.execute("INSERT INTO desktop_licenses (id,cyber_id,nombre,key_hash,expires_at,modulos,created_at) VALUES (1,4,'Fixture','fixture','2099-01-01','[\"contabilidad\",\"pos\",\"clientes\",\"fotos\",\"tarjetas\",\"autoservicio\"]','2025-01-01')")
                db.execute("INSERT INTO desktop_devices (license_id,user_id,uuid,nombre,token_hash,access_role,created_at) VALUES (1,7,?,?,?,?,?)",
                           (claims['device'],'Fixture',hashlib.sha256(bearer.encode()).hexdigest(),'cyber','2025-01-01'))
            engine = open_workspace(store, bundle)
            server = LocalEngine(bundle, server_space, lambda: claims)
            configuration = server.configuration
            def test_server_configuration():
                app, env = configuration()
                env['DESKTOP_REQUIRE_HTTPS'] = 'false'
                return app, env
            server.configuration = test_server_configuration
            try:
                with httpx.Client(trust_env=False, follow_redirects=False) as client:
                    headers = {'X-MiCV-Local':engine.token}
                    form = client.get(engine.origin+'/contabilidad/nuevo', headers=headers)
                    self.assertEqual(form.status_code, 200, form.headers.get('location'))
                    csrf = re.search(r'name="_token" value="([^"]+)"',form.text).group(1)
                    headers['X-CSRF-TOKEN'] = csrf

                    def post(path, data, status=302):
                        result = client.post(engine.origin+path,json=data,headers=headers)
                        self.assertEqual(result.status_code,status,result.text)
                        return result
                    post('/contabilidad', {'clave_operacion':str(uuid.uuid4()),'fecha':'2026-11-10','tipo':'gasto',
                                           'categoria':'Papel','concepto':'Compra local','monto':'25.00'})
                    post('/contabilidad/1/anular', {'motivo':'Registro de prueba anulado'})
                    post('/pos/categorias', {'nombre':'Categoría local'})
                    post('/pos/categorias', {'id':1,'nombre':'Papelería local','color':'#112233'})
                    post('/pos/proveedores', {'nombre':'Proveedor local'})
                    post('/pos/proveedores', {'id':1,'nombre':'Proveedor local editado'})
                    product = {'tipo':'producto','nombre':'Cartulina local','codigo':'LOCAL-NEW','precio':'10',
                               'impuesto':'exento','controla_stock':True,'stock_inicial':'5','categoria_id':1,'proveedor_id':1}
                    if os.environ.get('MICV_TEST_UPLOAD'):
                        fields = dict(product,controla_stock='1')
                        created = client.post(engine.origin+'/pos/productos',data=fields,
                                              files={'foto':('fixture.png',fixture_png(),'image/png')},headers=headers)
                        self.assertEqual(created.status_code,302,created.text)
                    else:
                        post('/pos/productos', product)
                    edited = client.put(engine.origin+'/pos/productos/4',json=dict(product,precio='15'),headers=headers)
                    self.assertEqual(edited.status_code,302,edited.text)
                    post('/pos/caja/abrir', {'monto_inicial':100})
                    post('/pos/caja/movimiento', {'tipo':'ingreso','monto':20,'motivo':'Cambio'})
                    replenishment = client.get(engine.origin+'/pos/compras/nueva?reponer=3', headers=headers)
                    self.assertEqual(replenishment.status_code, 200, replenishment.text)
                    self.assertIn('"cantidad":4', replenishment.text)
                    dossier = {'pais':'CR','tipo_documento':'cedula_cr','numero_documento':'102340567',
                               'primer_nombre':'Ana','primer_apellido':'Prueba','fecha_nacimiento':'1998-05-15',
                               'municipio_nacimiento':'San José','departamento_nacimiento':'San José',
                               'nacionalidad':'Costarricense','pais_residencia':'CR','activo':True,
                               'estudios':[{'nivel':'curso','titulo':'Contabilidad'}]}
                    panel = client.get(engine.origin+'/autoservicio',headers=headers)
                    self.assertEqual(panel.status_code,200,panel.text)
                    self.assertIn('Turnos guardados en este equipo',panel.text)
                    self.assertNotIn('ACTIVAR QR',panel.text)
                    queue = client.get(engine.origin+'/autoservicio/lista',headers=headers).json()
                    self.assertEqual(queue['pendientes'],3)
                    form = client.get(engine.origin+'/clientes/nuevo?autoservicio=1',headers=headers)
                    self.assertEqual(form.status_code,200,form.text)
                    self.assertIn('name="autoservicio_id" value="1"',form.text)
                    post('/clientes', dict(dossier, autoservicio_id=1))
                    post('/clientes', dict(dossier, autoservicio_id=1), 409)
                    post('/clientes', dict(dossier, primer_nombre='Duplicado'), 409)
                    items = [{'producto_id':4,'cantidad':1,'precio':15}]
                    pending_id = str(uuid.uuid4())
                    post('/pos/pendientes', {'id':pending_id, 'nombre':'Cotización de cartulina', 'tipo':'cotizacion', 'items':items}, 200)
                    pending_page = client.get(engine.origin+'/pos/pendientes', headers=headers)
                    self.assertEqual(pending_page.status_code, 200, pending_page.text)
                    self.assertIn('Cotización de cartulina', pending_page.text)
                    self.assertIn('NIO', pending_page.text)
                    resume = client.get(engine.origin+'/pos?pendiente='+pending_id, headers=headers)
                    self.assertEqual(resume.status_code, 200, resume.text)
                    self.assertIn(pending_id, resume.text)
                    from micv_desktop.workspace_access import ensure_no_pos_drafts
                    from micv_desktop.security import AccessError
                    with self.assertRaisesRegex(AccessError, 'Hay ventas en espera'):
                        ensure_no_pos_drafts(store)
                    if os.environ.get('MICV_TEST_POS_PDF'):
                        pdf = client.get(engine.origin+'/pos/pendientes/'+pending_id+'/pdf', headers=headers)
                        self.assertEqual(pdf.status_code, 200, pdf.text[:1200])
                        self.assertTrue(pdf.content.startswith(b'%PDF-'))
                    sale = post('/pos/ventas', {'clave_operacion':str(uuid.uuid4()),'items':items, 'pendiente_id':pending_id,
                                                 'cliente_id':1,'pagos':[{'metodo':'efectivo','monto':15}]},200).json()
                    ensure_no_pos_drafts(store)
                    reports = client.get(engine.origin+'/pos/reportes?periodo=hoy', headers=headers)
                    self.assertEqual(reports.status_code, 200, reports.text)
                    self.assertIn('Horas con más ventas', reports.text)
                    self.assertIn('1 venta', reports.text)
                    if os.environ.get('MICV_TEST_POS_PDF'):
                        from io import BytesIO
                        from zipfile import ZipFile
                        excel = client.get(engine.origin+'/pos/reportes/exportar?periodo=hoy&formato=excel', headers=headers)
                        self.assertEqual(excel.status_code, 200, excel.text[:1200])
                        with ZipFile(BytesIO(excel.content)) as workbook:
                            self.assertIn(b'Ventas por hora', workbook.read('xl/workbook.xml'))
                    post('/pos/ventas/'+str(sale['venta']['id'])+'/anular', {'motivo':'Venta de prueba'})
                    post('/pos/caja/cerrar', {'efectivo_contado':120,'notas_cierre':'Arqueo'})
                    post('/pos/productos/3/ajuste', {'modo':'fijar','cantidad':10,'motivo':'Conteo físico'})
                    post('/pos/compras', {'fecha':'2026-11-10','numero_factura':'F-01','proveedor_id':1,
                                          'items':[{'producto_id':4,'cantidad':2,'costo':4}]})
                    post('/pos/compras/1/anular', {})
                    removed = client.delete(engine.origin+'/pos/productos/4',headers=headers)
                    self.assertEqual(removed.status_code,302,removed.text)
                    for endpoint in ('categorias','proveedores'):
                        deleted = client.delete(engine.origin+'/pos/'+endpoint+'/1',headers=headers)
                        self.assertEqual(deleted.status_code,302,deleted.text)



                    import_workspace_edits(store, bundle)
                    pending = store.operations()
                    self.assertEqual([op['type'] for op in pending],
                                     ['ledger.create','ledger.void','category.create','category.update','supplier.create','supplier.update',
                                      'product.create','product.update','cash.open','cash.move','client.full.create','sale.create','sale.void',
                                      'cash.close','stock.adjust','purchase.create','purchase.void','product.delete','category.delete','supplier.delete'])
                    server.start()
                    wire = [{name:op[name] for name in ('uuid','type','occurred_at','data')} for op in pending]
                    remote_headers = {'X-MiCV-Local':server.token,'Authorization':'Bearer '+bearer}
                    response = client.post(server.origin+'/api/desktop/v1/push',json={'operations':wire},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    receipts = response.json()['results']
                    self.assertTrue(all(row['status']=='accepted' for row in receipts), receipts)
                    for receipt in receipts:
                        store.ack(receipt)
                    import_workspace_edits(store,bundle)
                    self.assertEqual(store.operations(),[])
                    edited = client.put(engine.origin+'/clientes/1',json=dict(dossier, primer_nombre='Ana María'),headers=headers)
                    self.assertEqual(edited.status_code,302,edited.text)
                    import_workspace_edits(store,bundle)
                    [edit] = store.operations()
                    self.assertEqual(edit['type'],'client.full.update')
                    wire_edit = {name:edit[name] for name in ('uuid','type','occurred_at','data')}
                    response = client.post(server.origin+'/api/desktop/v1/push',json={'operations':[wire_edit]},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    [receipt] = response.json()['results']
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    self.assertEqual(receipt['entity_id'],2)
                    store.ack(receipt)
                    import_workspace_edits(store,bundle)
                    self.assertEqual(store.operations(),[])

                    image = 'data:image/png;base64,' + base64.b64encode(fixture_png()).decode()
                    post('/fotos/cv/1', {'imagen':image}, 200)
                    for card in (None, 'Ana María · Contadora', 'Ana María · Consultora'):
                        if card:
                            result = client.post(engine.origin+'/tarjeta/1',json={'diseno':'consultoria','nombre':card},
                                                 headers=headers|{'Accept':'application/json'})
                            self.assertEqual(result.status_code,200,result.text)
                        import_workspace_edits(store, bundle)
                        [edit] = store.operations()
                        wire_edit = {name:edit[name] for name in ('uuid','type','occurred_at','data')}
                        response = client.post(server.origin+'/api/desktop/v1/push',
                                               json={'operations':[wire_edit]},headers=remote_headers)
                        self.assertEqual(response.status_code,200,response.text)
                        [receipt] = response.json()['results']
                        self.assertEqual(receipt['status'],'accepted',receipt)
                        self.assertEqual(receipt['entity_id'],2)
                        store.ack(receipt)
                        import_workspace_edits(store,bundle)
                        self.assertEqual(store.operations(),[])

                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        photo = db.execute('SELECT foto FROM clientes WHERE id=2').fetchone()[0]
                        self.assertEqual((server_space/'storage/app/private'/photo).read_bytes(),fixture_png())
                        card = db.execute('SELECT datos FROM tarjetas WHERE cliente_id=2').fetchone()[0]
                        self.assertEqual(json.loads(card)['nombre'],'Ana María · Consultora')
                        self.assertEqual(db.execute('SELECT estado,efectivo_contado FROM pos_cajas').fetchall(), [('cerrada',120)])
                        self.assertEqual(db.execute('SELECT stock FROM pos_productos').fetchone(), (10,))
                        self.assertEqual(db.execute('SELECT estado,cliente_id FROM autoservicio_solicitudes WHERE id=1').fetchone(),
                                         ('atendida',2))
                        self.assertEqual(db.execute('SELECT estado,cliente_id FROM pos_ventas').fetchall(), [('anulada',2)])
                        self.assertEqual(db.execute('SELECT id,primer_nombre FROM clientes ORDER BY id').fetchall(),
                                         [(1,'Cliente web'),(2,'Ana María')])
                        self.assertEqual(db.execute('SELECT estado FROM pos_compras').fetchall(), [('anulada',)])
                        self.assertEqual(db.execute("SELECT id,precio,stock,activo FROM pos_productos WHERE codigo='LOCAL-NEW'").fetchone(), (5,15,5,0))
                        self.assertEqual(db.execute('SELECT nombre FROM pos_productos WHERE id=4').fetchone(), ('Producto de la web',))
                        self.assertEqual(db.execute('SELECT producto_id FROM pos_venta_items').fetchone(), (5,))
                        self.assertEqual(db.execute('SELECT producto_id FROM pos_compra_items').fetchone(), (5,))
                        self.assertEqual(db.execute('SELECT proveedor_id FROM pos_compras').fetchone(), (2,))
                        self.assertEqual(db.execute('SELECT nombre,activo FROM pos_proveedores WHERE id=2').fetchone(), ('Proveedor local editado',0))
                        self.assertEqual(db.execute('SELECT id,nombre FROM pos_categorias').fetchall(), [(1,'Categoría web')])
                        self.assertEqual(db.execute('SELECT categoria_id FROM pos_productos WHERE id=5').fetchone(), (None,))
                        if os.environ.get('MICV_TEST_UPLOAD'):
                            photo = db.execute('SELECT foto FROM pos_productos WHERE id=5').fetchone()[0]
                            self.assertTrue((server_space/'storage/app/private'/photo).is_file())



                        self.assertEqual(db.execute("SELECT estado FROM contable_movimientos WHERE origen='manual'").fetchall(), [('anulado',)])
                        db.execute('UPDATE pos_productos SET stock=11 WHERE id=3')
                    config = {'pais':'NI','moneda':'NIO','simbolo':'C$','decimales':2,'locale':'es-NI',
                              'impuesto_nombre':'IVA','impuesto_tasa':15,'precios_con_impuesto':True,
                              'nombre_comercial':'Local actualizado','ancho_tiquete':58,'prefijo':'JZ-',
                              'siguiente_numero':2,'metodos':{'efectivo':{'nombre':'Efectivo','activo':True}}}
                    settings_url = engine.origin+'/pos/configuracion'
                    invalid = client.put(settings_url,json=dict(config,moneda='USD'),
                                         headers=headers|{'Accept':'application/json'})
                    self.assertEqual(invalid.status_code,422,invalid.text)
                    updated = client.put(settings_url,json=config,headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)
                    import_workspace_edits(store,bundle)
                    [edit] = store.operations()
                    self.assertEqual(edit['type'],'pos.config.update')
                    wire_config = {name:edit[name] for name in ('uuid','type','occurred_at','data')}
                    response = client.post(server.origin+'/api/desktop/v1/push',
                                           json={'operations':[wire_config]},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    [receipt] = response.json()['results']
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    store.ack(receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT nombre_comercial,prefijo,ancho_tiquete,siguiente_numero FROM pos_config').fetchone(),
                                         ('Local actualizado','JZ-',58,2))
                        db.execute("UPDATE autoservicio_solicitudes SET estado='atendida', cliente_id=1 WHERE id=3")
                    for turno in (2, 3):
                        post(f'/autoservicio/{turno}/descartar', {}, 200)
                    import_workspace_edits(store, bundle)
                    discard = [{key:op[key] for key in ('uuid','type','occurred_at','data')} for op in store.operations()]
                    self.assertEqual([op['type'] for op in discard],['autoservicio.discard']*2)
                    response = client.post(server.origin+'/api/desktop/v1/push',json={'operations':discard},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    receipts = response.json()['results']
                    self.assertEqual([r['status'] for r in receipts],['accepted','conflict'])
                    for receipt in receipts:
                        store.ack(receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT estado,cliente_id FROM autoservicio_solicitudes ORDER BY id').fetchall(),
                                         [('atendida',2),('descartada',None),('atendida',1)])
                    marca = {'color_primario':'#284975','color_secundario':'#DCB846',
                             'correo':'negocio@example.test','telefono':'8888-8888','direccion':'La Dalia'}
                    url_marca = engine.origin+'/mi-marca'
                    if os.environ.get('MICV_TEST_UPLOAD'):
                        updated = client.post(url_marca,data=marca|{'_method':'PUT'},
                                              files={'logo':('logo.png',fixture_png(),'image/png')},headers=headers)
                    else:
                        updated = client.put(url_marca,json=marca,headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)

                    def sync_brand():
                        import_workspace_edits(store,bundle)
                        [op] = [row for row in store.operations() if row['type']=='brand.update']
                        wire = {name:op[name] for name in ('uuid','type','occurred_at','data')}
                        response = client.post(server.origin+'/api/desktop/v1/push',
                                               json={'operations':[wire]},headers=remote_headers)
                        self.assertEqual(response.status_code,200,response.text)
                        [receipt] = response.json()['results']
                        store.ack(receipt)
                        return receipt

                    receipt = sync_brand()
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    self.assertEqual(receipt['entity_id'],4)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        color, email, logo = db.execute('SELECT color_primario,correo,logo FROM cybers WHERE id=4').fetchone()
                        self.assertEqual((color,email),('#284975','negocio@example.test'))
                        if os.environ.get('MICV_TEST_UPLOAD'):
                            remote_logo = server_space/'storage/app/private'/logo
                            self.assertTrue(remote_logo.is_file())
                    updated = client.put(url_marca,json=marca|{'quitar_logo':True,'telefono':'7777-7777'},headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)
                    receipt = sync_brand()
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    if os.environ.get('MICV_TEST_UPLOAD'):
                        self.assertFalse(remote_logo.exists())
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT telefono,logo FROM cybers WHERE id=4').fetchone(),('7777-7777',None))
                        db.execute("UPDATE cybers SET color_primario='#0000FF' WHERE id=4")
                    updated = client.put(url_marca,json=marca|{'color_primario':'#FF0000'},headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)
                    receipt = sync_brand()
                    self.assertEqual(receipt['status'],'conflict',receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT color_primario FROM cybers WHERE id=4').fetchone(),('#0000FF',))
                    for workspace, client_id in ((local, 1), (server_space, 2)):
                        with closing(sqlite3.connect(workspace/'database.sqlite')) as db, db:
                            db.execute('INSERT INTO users (id,usuario,name,email,password,rol,activo,edicion_cliente) '
                                       'VALUES (?,?,?,?,?,?,?,?)',
                                       (12,'portal','Ana','portal@example.test','fixture','cliente',1,'["foto"]'))
                            db.execute('UPDATE clientes SET user_id=12 WHERE id=?',(client_id,))
                    permissions_page = client.get(engine.origin+'/clientes/1/acceso',headers=headers)
                    self.assertEqual(permissions_page.status_code,200,permissions_page.text)
                    self.assertIn('Guardar permisos de edición',permissions_page.text)
                    changes = client.put(engine.origin+'/clientes/1/acceso/permisos',json={'edicion_cliente':['cv']},headers=headers)
                    self.assertEqual(changes.status_code,302,changes.text)
                    import_workspace_edits(store,bundle)
                    [op] = [row for row in store.operations() if row['type']=='client.permissions.update']
                    wire = {name:op[name] for name in ('uuid','type','occurred_at','data')}
                    response = client.post(server.origin+'/api/desktop/v1/push',
                                           json={'operations':[wire]},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    [receipt] = response.json()['results']
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    store.ack(receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(json.loads(db.execute('SELECT edicion_cliente FROM users WHERE id=12').fetchone()[0]),['cv'])
                    team_url = engine.origin+'/equipo/8'
                    team_form = client.get(team_url+'/editar',headers=headers)
                    self.assertEqual(team_form.status_code,200,team_form.text)
                    self.assertIn('Guardar usuario',team_form.text)
                    self.assertNotIn('name="password"',team_form.text)
                    cashier = {'name':'Cajero actualizado','activo':False,'permisos':['pos.vender']}
                    invalid = client.put(team_url,json=cashier|{'permisos':['personal']},
                                         headers=headers|{'Accept':'application/json'})
                    self.assertEqual(invalid.status_code,403,invalid.text)
                    updated = client.put(team_url,json=cashier|{'email':'otro@example.test'},headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)

                    def sync_cashier():
                        import_workspace_edits(store,bundle)
                        [op] = [row for row in store.operations() if row['type']=='team.update']
                        wire = {name:op[name] for name in ('uuid','type','occurred_at','data')}
                        response = client.post(server.origin+'/api/desktop/v1/push',
                                               json={'operations':[wire]},headers=remote_headers)
                        self.assertEqual(response.status_code,200,response.text)
                        [receipt] = response.json()['results']
                        store.ack(receipt)
                        return receipt

                    receipt = sync_cashier()
                    self.assertEqual(receipt['status'],'accepted',receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        name, active, email, grants = db.execute(
                            'SELECT name,activo,email,permisos FROM users WHERE id=8').fetchone()
                        self.assertEqual((name,active,email),('Cajero actualizado',0,'cajero@example.test'))
                        self.assertEqual(set(json.loads(grants)),{'pos.vender','pos.caja','pos.ventas','documentos'})
                        db.execute("UPDATE users SET name='Cambio web' WHERE id=8")
                    updated = client.put(team_url,json=cashier|{'name':'Segundo cambio'},headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)
                    receipt = sync_cashier()
                    self.assertEqual(receipt['status'],'conflict',receipt)
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT name FROM users WHERE id=8').fetchone(),('Cambio web',))
                    post('/pos/productos/3/ajuste', {'modo':'fijar','cantidad':15,'motivo':'Nuevo conteo físico'})
                    import_workspace_edits(store,bundle)
                    new = next(op for op in store.operations() if op['type']=='stock.adjust')
                    wire = {name:new[name] for name in ('uuid','type','occurred_at','data')}
                    conflict = client.post(server.origin+'/api/desktop/v1/push',json={'operations':[wire]},headers=remote_headers)
                    self.assertEqual(conflict.status_code,200)
                    self.assertEqual(conflict.json()['results'][0]['status'],'conflict')
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT stock FROM pos_productos WHERE id=3').fetchone(), (11,))
                        db.execute("UPDATE pos_config SET prefijo='WEB-'")
                    updated = client.put(settings_url,json=dict(config,prefijo='PC-'),headers=headers)
                    self.assertEqual(updated.status_code,302,updated.text)
                    import_workspace_edits(store,bundle)
                    edit = next(op for op in store.operations() if op['type']=='pos.config.update')
                    wire_config = {name:edit[name] for name in ('uuid','type','occurred_at','data')}
                    response = client.post(server.origin+'/api/desktop/v1/push',
                                           json={'operations':[wire_config]},headers=remote_headers)
                    self.assertEqual(response.status_code,200,response.text)
                    self.assertEqual(response.json()['results'][0]['status'],'conflict')
                    with closing(sqlite3.connect(server_space/'database.sqlite')) as db, db:
                        self.assertEqual(db.execute('SELECT prefijo FROM pos_config').fetchone(),('WEB-',))


            finally:
                server.stop()
                engine.stop()
