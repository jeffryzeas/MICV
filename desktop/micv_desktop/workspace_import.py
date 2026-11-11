"""Materialize an encrypted stage into a separate Laravel SQLite workspace."""
import base64
import hashlib
import json
import os
import re
import secrets
import subprocess
import tempfile
import time
import uuid
from pathlib import Path, PurePosixPath

from .local_engine import LocalEngine
from .security import AccessError, Vault, verify_lease


def provision_workspace(store, bundle):
    if store.meta('blocked', False):
        raise AccessError('El acceso del equipo está bloqueado.')
    claims = verify_lease(store.meta('lease'), store.meta('profile'), store.meta('device_uuid'))
    snapshot = store.meta('workspace_staged', {})
    for field in ('user_id', 'tenant_id', 'device', 'role'):
        if snapshot.get(field) != claims[field]:
            raise AccessError('La copia preparada no corresponde a la cuenta activa.')
    if not claims['issued_at'] - 300 <= snapshot.get('issued_at', 0) <= time.time() + 300:
        raise AccessError('Actualice la copia con los permisos vigentes.')
    try:
        generation = str(uuid.UUID(snapshot['_local_generation']))
    except (KeyError, ValueError, TypeError) as exc:
        raise AccessError('Falta la descarga preparada.') from exc

    bundle = Path(bundle).resolve()
    php = bundle / 'php' / ('php.exe' if os.name == 'nt' else 'php')
    if not php.is_file():
        raise AccessError('Falta PHP en el paquete local.')
    parent = store.folder / 'workspaces'
    parent.mkdir(mode=0o700, exist_ok=True)
    if parent.is_symlink():
        raise AccessError('La carpeta del espacio local no es válida.')
    destination = parent / str(uuid.uuid4())
    with tempfile.TemporaryDirectory(prefix='import-', dir=parent) as temp:
        candidate = Path(temp)
        database = candidate / 'database.sqlite'
        database.touch(mode=0o600, exist_ok=False)
        key = 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode()
        engine = LocalEngine(bundle, candidate, lambda: claims)
        app, env = engine.environment(key, claims)
        env['MICV_IMPORT_WORKSPACE'] = '1'
        try:
            subprocess.run(
                [str(php), 'artisan', 'desktop:import-workspace'],
                cwd=app, env=env, input=json.dumps(snapshot, ensure_ascii=False, separators=(',', ':')).encode(),
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                timeout=180, check=True,
                creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0),
            )
        except (OSError, subprocess.SubprocessError) as exc:
            raise AccessError('No se pudo importar la copia a SQLite. Se conservó el espacio anterior.') from exc
        restore_files(store, snapshot, generation, candidate / 'storage/app/private')
        (candidate / 'workspace.secret').write_bytes(Vault(candidate).pack('laravel-app-key', key))
        manifest = {field: claims[field] for field in ('user_id', 'tenant_id', 'device', 'role')}
        manifest.update(version=1, state='ready', timezone='America/Managua', permissions=snapshot['permissions'])
        (candidate / 'workspace.json').write_text(json.dumps(manifest), encoding='utf-8')
        os.replace(candidate, destination)
    return destination


def restore_files(store, snapshot, generation, private):
    root = store.folder / 'workspace-cache' / generation
    for item in snapshot['files']:
        if item['missing']:
            continue
        relative = PurePosixPath(item['path'])
        digest = item['sha256']
        if not re.fullmatch(r'[a-z]+/[A-Za-z0-9_-]+\.[a-z0-9]+', item['path']) or not re.fullmatch('[a-f0-9]{64}', digest):
            raise AccessError('La ruta del archivo local no es válida.')
        raw = base64.b64decode(store.vault.unpack('workspace-file:' + digest, (root / digest).read_bytes()), validate=True)
        if len(raw) != item['size'] or hashlib.sha256(raw).hexdigest() != digest:
            raise AccessError('El archivo preparado no coincide con la descarga.')
        target = private.joinpath(*relative.parts)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(raw)
