"""Account-bound launch of an already imported workspace."""
import json
import os
import sqlite3
from contextlib import closing
import subprocess
import time
import uuid
from pathlib import Path

from .local_engine import LocalEngine
from .security import AccessError
from .service import Service


def authorize_workspace(store):
    if store.meta('blocked', False):
        raise AccessError('El servidor desactivó el acceso de esta cuenta.')
    now = time.time()
    if now + 300 < store.meta('last_clock', 0):
        raise AccessError('Corrija la fecha del equipo antes de abrir la cuenta.')
    claims = Service(store).claims(valid=True)
    store.set_meta('last_clock', max(now, store.meta('last_clock', 0)))
    return claims


def active_workspace(store):
    identifier = store.meta('workspace_active')
    try:
        if str(uuid.UUID(identifier)) != identifier:
            raise ValueError()
    except (ValueError, TypeError, AttributeError) as exc:
        raise AccessError('Prepare los datos locales de esta cuenta primero.') from exc
    root = store.folder / 'workspaces'
    workspace = root / identifier
    if root.is_symlink() or workspace.is_symlink() or not workspace.is_dir():
        raise AccessError('El espacio local preparado no está disponible.')
    return workspace


def workspace_engine(store, bundle):
    authorize_workspace(store)
    return LocalEngine(Path(bundle), active_workspace(store), lambda: authorize_workspace(store))


def open_workspace(store, bundle):
    engine = workspace_engine(store, bundle)
    engine.start()
    return engine


def ensure_no_pos_drafts(store):
    if not store.meta('workspace_active'):
        return
    db = active_workspace(store) / 'database.sqlite'
    if not db.is_file() or db.is_symlink():
        raise AccessError('No se pudo revisar el espacio local antes de actualizarlo.')
    try:
        with closing(sqlite3.connect(db.as_uri() + '?mode=ro', uri=True)) as conn:
            table = conn.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name='pos_pendientes'").fetchone()
            has_drafts = table and conn.execute('SELECT 1 FROM pos_pendientes WHERE venta_id IS NULL LIMIT 1').fetchone()
    except sqlite3.Error as exc:
        raise AccessError('No se pudieron revisar los pendientes del POS. Se conservó la copia local.') from exc
    if has_drafts:
        raise AccessError('Hay ventas en espera o cotizaciones en el espacio local. Retómelas o descártelas desde Pendientes antes de reemplazar la copia.')


def import_workspace_edits(store, bundle):
    if not store.meta('workspace_active'):
        return
    engine = workspace_engine(store, bundle)
    app, env = engine.configuration()
    env['MICV_READ_LOCAL_EDITS'] = '1'
    php = engine.bundle / 'php' / ('php.exe' if os.name == 'nt' else 'php')
    try:
        for command in ('desktop:pending-cv','desktop:pending-business'):
            cursor = 0
            while True:
                result = subprocess.run(
                    [str(php), 'artisan', command, '--after='+str(cursor)],
                    cwd=app, env=env, capture_output=True, timeout=30, check=True,
                    creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0),
                )
                page = json.loads(result.stdout)
                store.import_operations(page['operations'])
                receipts = []
                for op in page['operations']:
                    revision = store.meta('cv_receipt:' + op['uuid'])
                    if revision:
                        receipts.append({'uuid':op['uuid'],
                                         'client_id':op['data'].get('local_client_id',op['data'].get('id')),
                                         'revision':revision,'created':op['type']=='client.full.create'})
                if receipts:
                    subprocess.run(
                        [str(php), 'artisan', 'desktop:confirm-cv'], cwd=app, env=env,
                        input=json.dumps(receipts).encode(), capture_output=True, timeout=30, check=True,
                        creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0),
                    )
                following = page['next']
                if following is None:
                    break
                if type(following) is not int or following <= cursor:
                    raise ValueError('La cola local devolvió una página inválida.')
                cursor = following
    except (OSError, subprocess.SubprocessError, ValueError, KeyError, TypeError) as exc:
        raise AccessError('No se pudieron leer los cambios locales. Sus datos se conservaron.') from exc

