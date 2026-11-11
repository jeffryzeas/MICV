"""Account-scoped summaries for the desktop diagnostics window."""
from datetime import datetime
import re
import time

from .security import AccessError
from .workspace_access import active_workspace


EVENTS = {
    'action': 'Falló una operación del escritorio.',
    'backup': 'Falló la creación del respaldo.',
    'prepare': 'Falló la preparación de los datos locales.',
    'local_start': 'No se pudo abrir el espacio local.',
    'sync': 'La sincronización no terminó.',
    'server': 'El servidor no respondió. El trabajo local puede continuar.',
    'session': 'No se pudo renovar la sesión conectada.',
    'access': 'El espacio de trabajo se cerró por un problema de acceso o del motor.',
    'page': 'No se pudo cargar una pantalla.',
    'download': 'Se interrumpió una descarga.',
    'print': 'No se pudo completar la impresión.',
}


def record_event(store, code):
    if code not in EVENTS:
        return
    try:
        with store.connection() as db:
            db.execute('PRAGMA busy_timeout=250')
            db.execute('BEGIN IMMEDIATE')
            row = db.execute("SELECT value FROM meta WHERE name='diagnostics'").fetchone()
            items = store.vault.unpack('meta:diagnostics', row['value']) if row else []
            now = int(time.time())
            if items and items[-1]['code'] == code and now - items[-1]['time'] < 120:
                return
            store._set_meta(db, 'diagnostics', (items + [{'code': code, 'time': now}])[-50:])
    except Exception:
        # A diagnostic write must never hide the operation's original error.
        return


def local_log_counts(store):
    try:
        logs = active_workspace(store) / 'storage/logs'
    except AccessError:
        return {}
    if logs.resolve() != logs.absolute() or not logs.is_dir():
        return {}
    patterns = {
        'Laravel local': ('laravel.log', r'^\[[^\]]+\]\s+\w+\.(?:ERROR|CRITICAL|ALERT|EMERGENCY):'),
        'Motor PHP': ('engine.log', r'^\[[^\]]+\]\s+(?:PHP (?:Fatal|Parse) error:|Fatal error:|Uncaught)'),
    }
    result = {}
    for label, (filename, pattern) in patterns.items():
        path = logs / filename
        if path.is_symlink() or not path.is_file():
            continue
        try:
            with path.open('rb') as source:
                source.seek(max(0, path.stat().st_size - 128 * 1024))
                data = source.read(128 * 1024).decode('utf-8', errors='replace')
            result[label] = len(re.findall(pattern, data, re.MULTILINE))
        except OSError:
            continue
    return result


def report(store):
    from . import __version__

    sections = [f'Mi CV Escritorio {__version__}', '', 'Actividad reciente del escritorio']
    events = store.meta('diagnostics', [])
    if not events:
        sections.append('Sin incidencias registradas.')
    for event in reversed(events):
        stamp = datetime.fromtimestamp(event['time']).strftime('%d/%m/%Y %H:%M')
        sections.append(f'{stamp} · {EVENTS[event["code"]]}')
    sections += ['', 'Errores al final de los registros locales']
    counts = local_log_counts(store)
    sections.extend([f'{label} · {count} ' + ('error' if count == 1 else 'errores') for label, count in counts.items()] or ['Sin registros disponibles.'])
    sections += ['', 'Sincronización']
    with store.connection() as db:
        totals = dict(db.execute("SELECT status,COUNT(*) FROM outbox WHERE status != 'accepted' GROUP BY status").fetchall())
        conflicts = db.execute("SELECT uuid,value FROM outbox WHERE status='conflict' ORDER BY created DESC LIMIT 25").fetchall()
    sections.append(f'{totals.get("pending", 0)} pendientes · {totals.get("conflict", 0)} por revisar')
    for row in conflicts:
        item = store.vault.unpack('op:' + row['uuid'], row['value'])
        sections.append(f'{item["type"]} · {row["uuid"][:8]}')
    if conflicts:
        sections.append('Abra Sincronización para revisar los detalles.')
    return '\n'.join(sections)
