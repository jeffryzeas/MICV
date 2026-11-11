"""Full CV dossier download with account binding and atomic cache replacement."""
import base64,json,time
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from .security import AccessError

def verify_page(envelope,profile,claims,cursor,now=None):
    now=time.time() if now is None else now
    try:
        if claims['valid_until']<=now:raise ValueError()
        encoded=envelope['payload']
        if not isinstance(encoded,str) or len(encoded)>45*1024*1024:raise ValueError()
        payload=base64.b64decode(encoded,validate=True)
        if len(payload)>32*1024*1024:raise ValueError()
        Ed25519PublicKey.from_public_bytes(base64.b64decode(profile['public_key'],validate=True)).verify(base64.b64decode(envelope['signature'],validate=True),payload)
        page=json.loads(payload)
        if page['format']!='micv-dossiers-v1' or page['issuer']!=profile['server_url'] or page['after']!=cursor:raise ValueError()
        for name in ('device','user_id','tenant_id','role'):
            if page[name]!=claims[name]:raise ValueError()
        if not isinstance(page['issued_at'],int) or abs(page['issued_at']-now)>300:raise ValueError()
        rows=page['rows']
        if not isinstance(rows,list) or len(rows)>25:raise ValueError()
        previous=cursor
        for row in rows:
            key=row['id']
            if type(key) is not int or key<=previous:raise ValueError()
            if not isinstance(row['revision'],str) or len(row['revision'])!=64 or any(c not in '0123456789abcdef' for c in row['revision']):raise ValueError()
            dossier=row['data']
            if not isinstance(dossier['client'],dict) or dossier['client']['id']!=key:raise ValueError()
            for section in ('estudios','experiencias','idiomas','habilidades','referencias'):
                if not isinstance(dossier[section],list) or any(not isinstance(item,dict) or item.get('cliente_id')!=key for item in dossier[section]):raise ValueError()
            if claims['role']=='cliente' and dossier['client'].get('user_id')!=claims['user_id']:raise ValueError()
            previous=key
        following=page['next']
        if following is not None and (type(following) is not int or not rows or following!=previous):raise ValueError()
        return page
    except Exception as error:
        raise AccessError('No se pudo verificar el CV descargado. Se conservó la copia anterior.') from error

def download_dossiers(store,api,claims):
    rows=[];cursor=0
    for _ in range(4000):
        page=verify_page(api.call('GET','dossiers',params={'after':cursor}),store.meta('profile'),claims,cursor)
        rows.extend(page['rows'])
        if page['next'] is None:
            store.replace('dossiers',rows);return len(rows)
        cursor=page['next']
    raise AccessError('La descarga necesita dividirse. Se conservó la copia anterior.')
