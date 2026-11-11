"""Authenticated staging; never replaces a live Laravel database or local outbox."""
import base64,json,time,re,uuid,shutil,tempfile,os
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from .security import AccessError
TABLES=frozenset('cybers users configuracion paises municipios_nicaragua clientes cliente_cyber cliente_estudios cliente_experiencias cliente_idiomas cliente_habilidades cliente_referencias tarjetas correo_servicios autoservicio_solicitudes pos_config pos_categorias pos_productos pos_proveedores pos_compras pos_compra_items pos_movimientos pos_cajas pos_caja_movimientos pos_ventas pos_venta_items pos_venta_pagos contable_movimientos jz_chats jz_mensajes documentos_bloqueados'.split())
TENANT=frozenset('cliente_cyber correo_servicios autoservicio_solicitudes pos_config pos_categorias pos_productos pos_proveedores pos_compras pos_movimientos pos_cajas pos_ventas contable_movimientos'.split())
CHILDREN={'cliente_cyber':('clientes','cliente_id'),'tarjetas':('clientes','cliente_id'),'correo_servicios':('clientes','cliente_id'),'pos_compra_items':('pos_compras','compra_id'),'pos_caja_movimientos':('pos_cajas','caja_id'),'pos_venta_items':('pos_ventas','venta_id'),'pos_venta_pagos':('pos_ventas','venta_id'),'jz_mensajes':('jz_chats','chat_id'),**{'cliente_'+s:('clientes','cliente_id') for s in ('estudios','experiencias','idiomas','habilidades','referencias')}}

def verify_snapshot(envelope,profile,claims,now=None):
    now=time.time() if now is None else now
    try:
        if claims['valid_until']<=now:raise ValueError('expired')
        encoded=envelope['payload']
        if not isinstance(encoded,str) or len(encoded)>45*1024*1024:raise ValueError('size')
        raw=base64.b64decode(encoded,validate=True)
        if len(raw)>32*1024*1024:raise ValueError('size')
        Ed25519PublicKey.from_public_bytes(base64.b64decode(profile['public_key'],validate=True)).verify(base64.b64decode(envelope['signature'],validate=True),raw)
        data=json.loads(raw)
        if data['format']!='micv-workspace-v1' or data['state']!='staged' or data['issuer']!=profile['server_url']:raise ValueError('format')
        for key in ('device','user_id','tenant_id','role'):
            if data[key]!=claims[key]:raise ValueError('account')
        if type(data['issued_at']) is not int or abs(data['issued_at']-now)>300:raise ValueError('age')
        if data['encrypted_fields']!={'correo_servicios':['clave_correo'],'jz_mensajes':['texto']}:raise ValueError('encryption')
        if not isinstance(data['permissions'],list) or any(not isinstance(p,str) for p in data['permissions']):raise ValueError('permissions')
        tables=data['tables'];ids={};count=0
        if not isinstance(tables,dict) or set(tables)!=TABLES:raise ValueError('tables')
        for name,rows in tables.items():
            if not isinstance(rows,list) or len(rows)>20000:raise ValueError('rows')
            ids[name]=set();count+=len(rows)
            for row in rows:
                if not isinstance(row,dict) or any(not isinstance(k,str) or not(v is None or type(v) in (str,int,float,bool)) for k,v in row.items()):raise ValueError('row')
                if name=='cliente_cyber':key=(row['cliente_id'],row['cyber_id'])
                elif name=='paises':
                    key=row['codigo']
                    if not isinstance(key,str) or len(key)!=2 or not key.isascii() or not key.isalpha() or not key.isupper():raise ValueError('country')
                else:
                    key=row['id']
                    if type(key) is not int or key<1:raise ValueError('id')
                if key in ids[name]:raise ValueError('duplicate')
                ids[name].add(key)
                if name=='users' and {'password','remember_token','token_hash'} & row.keys():raise ValueError('credentials')
                if name=='configuracion' and ({'smtp_password','recaptcha_secret_key','smtp_usuario','smtp_host'} & row.keys() or row.get('smtp_activo') or row.get('recaptcha_activo')):raise ValueError('secrets')
                if claims['role']=='cyber':
                    if name in TENANT and row.get('cyber_id')!=claims['tenant_id']:raise ValueError('tenant')
                    if name=='cybers' and row['id']!=claims['tenant_id']:raise ValueError('tenant')
                if claims['role']=='cliente':
                    if name=='clientes' and row.get('user_id')!=claims['user_id']:raise ValueError('customer')
                    if name=='users' and row['id']!=claims['user_id']:raise ValueError('customer')
                if name=='jz_chats' and claims['user_id'] not in (row['uno_id'],row['dos_id']):raise ValueError('chat')
        if count>100000 or claims['user_id'] not in ids['users']:raise ValueError('account missing')
        if claims['role'] == 'cyber':
            client_ids = {row['cliente_id'] for row in tables['cliente_cyber']}
            linked_users = {row.get('user_id') for row in tables['clientes'] if row['id'] in client_ids}
            portal_fields = {'id','usuario','name','email','rol','activo','edicion_cliente'}
            for user in tables['users']:
                if user.get('rol') != 'cliente' and user.get('cyber_id') == claims['tenant_id']:
                    continue
                if ('clientes.permisos' not in data['permissions'] or user['id'] not in linked_users
                        or user['id'] == claims['user_id'] or user.get('rol') != 'cliente'
                        or set(user) - portal_fields):
                    raise ValueError('tenant user')

        if claims['role']!='admin' and tables['documentos_bloqueados']:raise ValueError('admin')
        if claims['role']=='cliente' and any(tables[t] for t in TABLES if t.startswith('pos_') or t in ('correo_servicios','autoservicio_solicitudes','contable_movimientos')):raise ValueError('business')
        for table,(parent,fk) in CHILDREN.items():
            if any(row[fk] not in ids[parent] for row in tables[table]):raise ValueError('parent')
        files=data['files'];seen=set();total_bytes=0
        if not isinstance(files,list) or len(files)>100000:raise ValueError('files')
        allowed={'cybers':['logo'],'users':['chat_foto'],'configuracion':['logo','escudo'],'paises':['escudo'],'clientes':['foto'],'tarjetas':['logo'],'pos_productos':['foto'],'jz_mensajes':['archivo','miniatura']}
        lookup={name:{row.get('codigo') if name=='paises' else row.get('id'):row for row in rows} for name,rows in tables.items()}
        for f in files:
            if f['field'] not in allowed.get(f['table'],[]):raise ValueError('file field')
            row=lookup[f['table']].get(f['key'])
            if row is None or row.get(f['field'])!=f['path']:raise ValueError('file owner')
            ref=(f['table'],f['key'],f['field'])
            if ref in seen:raise ValueError('duplicate file')
            seen.add(ref)
            if not re.fullmatch(r'(fotos|productos|logos|marca|escudos|tarjetas|chat)/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|pdf|webm|ogg|m4a|mp3|wav|docx|xlsx|pptx)',f['path']):raise ValueError('file path')
            if type(f['missing']) is not bool or type(f['size']) is not int or not 0<=f['size']<=50*1024*1024:raise ValueError('file size')
            if f['missing']:
                if f['size']!=0 or f['sha256'] is not None:raise ValueError('missing file')
            elif not isinstance(f['sha256'],str) or not re.fullmatch('[a-f0-9]{64}',f['sha256']):raise ValueError('hash')
            total_bytes+=f['size']
        for table,fields in allowed.items():
            for row in tables[table]:
                for field in fields:
                    if row.get(field) and (table,row.get('codigo') if table=='paises' else row['id'],field) not in seen:raise ValueError('missing descriptor')
        if total_bytes>256*1024*1024:raise ValueError('file budget')
        return data
    except Exception as exc:
        raise AccessError('No se pudo verificar la descarga. Sus datos locales se conservaron.') from exc

def stage_snapshot(store,api,claims):
    snapshot=verify_snapshot(api.call('GET','workspace'),store.meta('profile'),claims)
    root=store.folder/'workspace-cache';root.mkdir(exist_ok=True,mode=0o700)
    if root.is_symlink():raise AccessError('La carpeta local no es válida.')
    generation=str(uuid.uuid4());target=root/generation
    previous=store.meta('workspace_staged',{}).get('_local_generation')
    with tempfile.TemporaryDirectory(prefix='pending-',dir=root) as temporary:
        for item in snapshot['files']:
            if item['missing']:continue
            path=Path(temporary)/item['sha256']
            if path.exists():continue
            raw=api.workspace_file(item)
            import hashlib
            if len(raw)!=item['size'] or hashlib.sha256(raw).hexdigest()!=item['sha256']:
                raise AccessError('El archivo descargado no coincide con la firma.')
            path.write_bytes(store.vault.pack('workspace-file:'+item['sha256'],base64.b64encode(raw).decode()))
        os.replace(temporary,target)
        snapshot['_local_generation']=generation
        try:store.set_meta('workspace_staged',snapshot)
        except Exception:
            shutil.rmtree(target);raise
    if isinstance(previous,str) and re.fullmatch('[a-f0-9-]{36}',previous) and previous!=generation:
        old=root/previous
        if old.is_dir() and not old.is_symlink():shutil.rmtree(old,ignore_errors=True)
    return sum(len(rows) for rows in snapshot['tables'].values())
