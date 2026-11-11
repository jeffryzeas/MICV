"""Backup and recover a closed local workspace without contacting the server."""
from contextlib import closing
import json
import os
from pathlib import Path
import re
import shutil
import sqlite3
import tempfile
import uuid
import zipfile

from cryptography.exceptions import InvalidTag

from .security import AccessError, Vault, verify_lease
from .workspace_access import active_workspace

CATALOG = 'micv.sqlite'
WORKSPACE_FILES = ('database.sqlite', 'workspace.json', 'workspace.secret', 'local.key')
PRIVATE_ASSET = re.compile(r'storage/app/private/[a-z]+/[A-Za-z0-9_-]+\.[A-Za-z0-9]+')
SEALED = 'perfil-local.enc'


def snapshot_sqlite(source, destination):
    if source.is_symlink() or not source.is_file():
        raise AccessError('Falta una base de datos del espacio local.')
    with closing(sqlite3.connect(source.as_uri() + '?mode=ro', uri=True)) as db:
        with closing(sqlite3.connect(destination)) as target:
            db.backup(target)


def workspace_members(workspace):
    for name in WORKSPACE_FILES:
        if name == 'database.sqlite':
            continue
        path = workspace / name
        if path.is_symlink() or not path.is_file():
            raise AccessError('Faltan archivos necesarios del espacio local.')
        yield path, name
    private = workspace / 'storage/app/private'
    if any(path.is_symlink() for path in (workspace/'storage', workspace/'storage/app', private)):
        raise AccessError('La carpeta de archivos del espacio local no es válida.')
    for path in private.rglob('*'):
        if path.is_symlink() or not path.resolve().is_relative_to(private):
            raise AccessError('El espacio local contiene un enlace en sus archivos.')
        if path.is_file():
            name = path.relative_to(workspace).as_posix()
            if not PRIVATE_ASSET.fullmatch(name):
                raise AccessError('El espacio local contiene un archivo con un nombre inválido.')
            yield path, name


def backup_workspace(store, destination):
    workspace = active_workspace(store).resolve()
    destination = Path(destination).resolve()
    if destination.is_relative_to(workspace) or destination in (store.path, store.vault.path):
        raise AccessError('Guarde el respaldo fuera del espacio de trabajo.')
    with tempfile.TemporaryDirectory(prefix='respaldo-', dir=store.folder) as temporary:
        stage = Path(temporary)
        snapshot_sqlite(store.path.resolve(), stage/CATALOG)
        snapshot_sqlite(workspace/'database.sqlite', stage/'database.sqlite')
        archive_path = stage/'contents.zip'
        with zipfile.ZipFile(archive_path, 'w', zipfile.ZIP_DEFLATED) as inner:
            inner.write(stage/CATALOG, CATALOG)
            inner.write(stage/'database.sqlite', 'database.sqlite')
            for path, name in workspace_members(workspace):
                inner.write(path, name)
            logo = store.folder/'report-logo.png'
            if logo.is_file() and not logo.is_symlink():
                inner.write(logo, 'report-logo.png')
        with tempfile.NamedTemporaryFile(dir=destination.parent, delete=False) as handle:
            output = Path(handle.name)
        try:
            with zipfile.ZipFile(output, 'w') as archive:
                archive.write(store.vault.path, 'local.key')
                archive.writestr('LEAME.txt', 'Restaure con Mi CV Escritorio desde la lista de cuentas. Use el mismo perfil de Windows.\n')
                with archive_path.open('rb') as source, archive.open(SEALED, 'w', force_zip64=True) as target:
                    store.vault.seal_backup(source, target)
            os.replace(output, destination)
        finally:
            output.unlink(missing_ok=True)


def restore_workspace(source, accounts, pin, *, existing_devices=()):
    from .storage import Store

    accounts = Path(accounts)
    accounts.mkdir(parents=True, exist_ok=True)
    destination = accounts/str(uuid.uuid4())
    try:
        with tempfile.TemporaryDirectory(prefix='recuperar-', dir=accounts) as temporary:
            stage = Path(temporary)
            account = stage/'profile'
            account.mkdir(mode=0o700)
            inner_path = stage/'contents.zip'
            with zipfile.ZipFile(source) as archive:
                if sorted(archive.namelist()) != sorted(['local.key', 'LEAME.txt', SEALED]):
                    raise AccessError('Seleccione un respaldo completo del espacio local.')
                if archive.getinfo('local.key').file_size > 65536:
                    raise AccessError('La clave del respaldo no es válida.')
                (account/'local.key').write_bytes(archive.read('local.key'))
                (account/'local.key').chmod(0o600)
                with archive.open(SEALED) as sealed, inner_path.open('wb') as target:
                    Vault(account).open_backup(sealed, target)
            with zipfile.ZipFile(inner_path) as inner:
                names = inner.namelist()
                required = {CATALOG, *WORKSPACE_FILES}
                if not required.issubset(names) or len(names) != len({name.casefold() for name in names}):
                    raise AccessError('El respaldo no contiene un espacio local completo.')
                allowed = required | {'report-logo.png'}
                for name in names:
                    if name not in allowed and not PRIVATE_ASSET.fullmatch(name):
                        raise AccessError('El respaldo contiene un nombre de archivo inválido.')
                for name in (CATALOG, 'report-logo.png'):
                    if name in names:
                        with inner.open(name) as data, (account/name).open('xb') as target:
                            shutil.copyfileobj(data, target)
                store = Store(account)
                store.unlock(pin)
                claims = verify_lease(store.meta('lease'), store.meta('profile'), store.meta('device_uuid'), valid=False)
                if claims['device'] in existing_devices:
                    raise AccessError('Ya existe este perfil en el equipo. Conserve la carpeta actual antes de recuperar una copia.')
                identifier = store.meta('workspace_active')
                if str(uuid.UUID(identifier)) != identifier:
                    raise AccessError('El respaldo no identifica el espacio local.')
                workspace = account/'workspaces'/identifier
                for name in names:
                    if name in (CATALOG, 'report-logo.png'):
                        continue
                    target = workspace/name
                    target.parent.mkdir(parents=True, exist_ok=True)
                    with inner.open(name) as data, target.open('xb') as output:
                        shutil.copyfileobj(data, output)
            manifest = json.loads((workspace/'workspace.json').read_text(encoding='utf-8'))
            if any(manifest.get(key) != claims.get(key) for key in ('user_id','tenant_id','device','role')):
                raise AccessError('La copia local no corresponde a esta cuenta.')
            Vault(workspace).unpack('laravel-app-key', (workspace/'workspace.secret').read_bytes())
            with closing(sqlite3.connect((workspace/'database.sqlite').as_uri()+'?mode=ro', uri=True)) as db:
                if db.execute('PRAGMA quick_check').fetchone() != ('ok',):
                    raise AccessError('La base local del respaldo está dañada.')
            store.set_meta('workspace_staged', {})
            os.replace(account, destination)
    except (InvalidTag, ValueError, TypeError, KeyError, OSError, zipfile.BadZipFile, sqlite3.DatabaseError) as error:
        raise AccessError('No se pudo verificar o recuperar este respaldo. Las cuentas existentes se conservaron.') from error
    return Store(destination)
