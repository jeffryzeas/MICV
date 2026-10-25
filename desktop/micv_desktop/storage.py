from __future__ import annotations
from contextlib import contextmanager, closing
from pathlib import Path
import json, sqlite3, time, uuid, zipfile, tempfile
from .security import Vault, AccessError, pin_valid

class Store:
    def __init__(self, folder: Path):
        self.folder = Path(folder)
        self.vault = Vault(self.folder)
        self.path = self.folder / 'micv.sqlite'
        with self.connection() as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.executescript('''CREATE TABLE IF NOT EXISTS meta(name TEXT PRIMARY KEY,value BLOB NOT NULL);
                CREATE TABLE IF NOT EXISTS cache(entity TEXT NOT NULL,id TEXT NOT NULL,value BLOB NOT NULL,PRIMARY KEY(entity,id));
                CREATE TABLE IF NOT EXISTS outbox(uuid TEXT PRIMARY KEY,status TEXT NOT NULL,value BLOB NOT NULL,error BLOB,remote_id INTEGER,created REAL NOT NULL,ack_at REAL);''')
        if not self.meta('device_uuid'):
            self.set_meta('device_uuid', str(uuid.uuid4()))

    @contextmanager
    def connection(self):
        db = sqlite3.connect(self.path, timeout=15)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA secure_delete=ON')
        try:
            yield db
            db.commit()
        except Exception:
            db.rollback()
            raise
        finally:
            db.close()

    def meta(self, name, default=None):
        with self.connection() as db:
            row = db.execute('SELECT value FROM meta WHERE name=?', (name,)).fetchone()
        return self.vault.unpack('meta:' + name, row['value']) if row else default

    def _set_meta(self, db, name, value):
        db.execute('INSERT OR REPLACE INTO meta VALUES(?,?)', (name, self.vault.pack('meta:' + name, value)))

    def set_meta(self, name, value):
        with self.connection() as db:
            self._set_meta(db, name, value)

    def activate(self, profile, response, pin):
        with self.connection() as db:
            for key, value in {'profile': profile, 'token': response['token'], 'lease': response['lease'], 'business': response['business'], 'catalogs': response['catalogs'], 'cash': response['cash'], 'pin': pin, 'blocked': False, 'last_clock': response['server_time']}.items():
                self._set_meta(db, key, value)

    def unlock(self, pin):
        next_time = self.meta('pin_wait', 0)
        if time.time() < next_time:
            raise AccessError('Espere un minuto antes de volver a intentar el acceso local.')
        if not pin_valid(pin, self.meta('pin', {})):
            failures = self.meta('pin_failures', 0) + 1
            self.set_meta('pin_failures', failures)
            if failures >= 5:
                self.set_meta('pin_wait', time.time() + 60)
            raise AccessError('PIN local incorrecto.')
        self.set_meta('pin_failures', 0)
        self.set_meta('pin_wait', 0)

    def rows(self, entity):
        with self.connection() as db:
            rows = db.execute('SELECT id,value FROM cache WHERE entity=? ORDER BY id', (entity,)).fetchall()
        return [self.vault.unpack(f'cache:{entity}:{r["id"]}', r['value']) for r in rows]

    def replace(self, entity, rows):
        with self.connection() as db:
            db.execute('DELETE FROM cache WHERE entity=?', (entity,))
            for row in rows:
                key = str(row['id'])
                db.execute('INSERT INTO cache VALUES(?,?,?)', (entity, key, self.vault.pack(f'cache:{entity}:{key}', row)))
            self._set_meta(db, 'cache_time:' + entity, time.time())

    def queue(self, kind, data, *, operation_uuid=None):
        identifier = operation_uuid or str(uuid.uuid4())
        from datetime import datetime, timezone
        op = {'uuid': identifier, 'type': kind, 'occurred_at': datetime.now(timezone.utc).isoformat(), 'data': data}
        with self.connection() as db:
            db.execute('INSERT INTO outbox VALUES(?,?,?,?,?,?,?)', (identifier, 'pending', self.vault.pack('op:' + identifier, op), None, None, time.time(), None))
        return op

    def operations(self, *, include_accepted=False):
        with self.connection() as db:
            rows = db.execute('SELECT * FROM outbox ' + ('' if include_accepted else "WHERE status != 'accepted' ") + 'ORDER BY created,uuid').fetchall()
        out = []
        for row in rows:
            value = self.vault.unpack('op:' + row['uuid'], row['value'])
            value.update(status=row['status'], remote_id=row['remote_id'], ack_at=row['ack_at'], error=self.vault.unpack('error:' + row['uuid'], row['error']) if row['error'] else '')
            out.append(value)
        return out

    def ack(self, result):
        if result.get('status') not in ('accepted', 'conflict'):
            raise ValueError('Respuesta de sincronización inválida.')
        with self.connection() as db:
            if result['status'] == 'accepted':
                db.execute("UPDATE outbox SET status='accepted',remote_id=?,error=NULL,ack_at=? WHERE uuid=?", (int(result['entity_id']), time.time(), result['uuid']))
            else:
                db.execute("UPDATE outbox SET status='conflict',error=? WHERE uuid=?", (self.vault.pack('error:' + result['uuid'], result.get('message', 'Revise la operación.')), result['uuid']))

    def retry(self, identifier):
        with self.connection() as db:
            db.execute("UPDATE outbox SET status='pending',error=NULL WHERE uuid=? AND status='conflict'", (identifier,))

    def backup(self, destination: Path):
        # SQLite.backup incluye una instantánea consistente incluso usando WAL.
        with tempfile.TemporaryDirectory() as tmp:
            snap = Path(tmp) / 'micv.sqlite'
            # El contexto de sqlite3 solo confirma/revierte transacciones; no cierra
            # la conexion. Windows exige cerrar ambos archivos antes de borrarlos.
            with closing(sqlite3.connect(self.path)) as source, closing(sqlite3.connect(snap)) as target:
                source.backup(target)
            with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
                archive.write(snap, 'micv.sqlite')
                archive.write(self.vault.path, 'local.key')
                archive.writestr('LEAME.txt', 'Copia cifrada local. Restaurar con el mismo perfil de Windows. No reemplazar una instalación con pendientes sin respaldarla primero.\n')
