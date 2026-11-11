from __future__ import annotations
import json, socket, time
from .api import Api, RemoteError, OfflineError
from .security import AccessError, check_profile, pin_hash, verify_lease

DEFAULT_SERVER_URL = 'https://micv.sistemajz.com'

def push_batches(operations):
    batch = []
    size = 32
    for operation in operations:
        item = {key:operation[key] for key in ('uuid', 'type', 'occurred_at', 'data')}
        encoded_size = len(json.dumps(item, ensure_ascii=True).replace('/', '\\/')) + 1
        if encoded_size > 1000000:
            raise AccessError('Una operación excede el tamaño admitido por el servidor.')
        if batch and (len(batch) == 20 or size + encoded_size > 1000000):
            yield batch
            batch = []
            size = 32
        batch.append(item)
        size += encoded_size
    if batch:
        yield batch


class Sync:
    def __init__(self, store, api_factory=Api, allow_local=False):
        self.store = store
        self.factory = api_factory
        self.allow_local = allow_local

    def _validate(self, response, profile):
        claims = verify_lease(response['lease'], profile, self.store.meta('device_uuid'), now=response['server_time'])
        if abs(time.time() - response['server_time']) > 300:
            raise AccessError('La hora del equipo difiere del servidor. Corrija fecha y hora antes de activar o sincronizar.')
        old = self.store.meta('lease')
        if old:
            previous = verify_lease(old, self.store.meta('profile'), self.store.meta('device_uuid'), valid=False)
            if claims['tenant_id'] != previous['tenant_id'] or claims['user_id'] != previous['user_id']:
                raise AccessError('Esta instalación pertenece a otro negocio o usuario. Sus datos no se mezclaron.')
        if claims['tenant_id'] != response['business']['id']:
            raise AccessError('La respuesta no corresponde al negocio autorizado.')
        return claims

    def activate(self, profile, user, password, key, pin, business_id=None):
        profile = check_profile(profile, allow_local=self.allow_local)
        previous = self.store.meta('profile')
        if previous and previous != profile:
            raise AccessError('No cambie el servidor o firma de una instalación con datos. Respalde la instalación y consulte al administrador.')
        saved_pin = pin_hash(pin)
        api = self.factory(profile)
        try:
            result = api.call('POST', 'activate', json={'usuario': user, 'password': password, 'license_key': key.strip(), 'device_uuid': self.store.meta('device_uuid'), 'device_name': socket.gethostname()[:100], 'business_id': business_id})
            self._validate(result, profile)
            self.store.activate(profile, result, saved_pin)
        finally:
            api.close()

    def access_direct(self, user, password):
        origin=(self.store.meta('profile') or {}).get('server_url',DEFAULT_SERVER_URL)
        api=self.factory({'server_url':origin})
        try:
            account=api.call('POST','access',json={'usuario':user.strip(),'password':password})
            if account.get('role') not in ('admin','cliente','cyber') or not isinstance(account.get('user_id'),int):
                raise AccessError('El servidor devolvió una cuenta no válida.')
            return account
        finally:
            api.close()

    def activate_direct(self, user, password, key, pin, business_id=None):
        previous = self.store.meta('profile')
        origin = previous['server_url'] if previous else DEFAULT_SERVER_URL
        api = self.factory({'server_url': origin})
        try:
            profile = check_profile(api.call('GET', 'connection'), allow_local=self.allow_local)
        except RemoteError as exc:
            if exc.status == 404:
                raise AccessError('Actualice los archivos de Laravel: falta la conexión automática de escritorio en el servidor.') from exc
            raise
        except OfflineError as exc:
            raise OfflineError('No se pudo conectar al servidor. Compruebe su conexión y la fecha del equipo; después vuelva a intentar.') from exc
        finally:
            api.close()
        if profile['server_url'] != origin:
            raise AccessError('La configuración del servidor no coincide con su dirección. El administrador debe revisar DESKTOP_SERVER_URL en Laravel.')
        if previous and previous != profile:
            raise AccessError('La firma del servidor cambió. Se conservaron sus datos; consulte al administrador antes de continuar.')
        return self.activate(profile, user, password, key, pin, business_id)

    def _metadata(self, response, profile):
        claims = self._validate(response, profile)
        for key in ('lease', 'business', 'cash', 'catalogs'):
            self.store.set_meta(key, response[key])
        self.store.set_meta('last_clock', max(time.time(), response['server_time'], self.store.meta('last_clock', 0)))
        self.store.set_meta('blocked', False)
        self.store.set_meta('capabilities', response.get('capabilities', {}))
        return claims

    def prepare_workspace(self):
        """Prepare an encrypted snapshot without applying it to the active workspace."""
        from .workspace_snapshot import stage_snapshot
        profile = self.store.meta('profile')
        if not profile:
            raise AccessError('Primero inicie sesión en este equipo.')
        api = self.factory(profile, self.store.meta('token'))
        try:
            claims = self._metadata(api.call('POST', 'renew'), profile)
            if self.store.meta('capabilities', {}).get('workspace_snapshot') != 1:
                raise AccessError('El servidor necesita la actualización del espacio local.')
            return stage_snapshot(self.store, api, claims)
        except RemoteError as exc:
            if exc.status in (401, 403):
                self.store.set_meta('blocked', True)
            raise
        finally:
            api.close()

    def provision_workspace(self, bundle):
        """Build a separate local workspace after renewing and downloading access."""
        from .workspace_import import provision_workspace
        from .workspace_access import ensure_no_pos_drafts, import_workspace_edits
        ensure_no_pos_drafts(self.store)
        self.prepare_workspace()
        import_workspace_edits(self.store, bundle)
        if self.store.operations():
            raise AccessError('Sincronice los cambios pendientes y resuelva sus conflictos antes de actualizar la copia.')
        return provision_workspace(self.store, bundle)

    def run(self):
        profile = self.store.meta('profile')
        if not profile:
            raise AccessError('Primero active el programa con su cuenta y licencia.')
        api = self.factory(profile, self.store.meta('token'))
        try:
            claims = self._metadata(api.call('POST', 'renew'), profile)
            from pathlib import Path
            from .workspace_access import import_workspace_edits
            import_workspace_edits(self.store, Path(__file__).resolve().parent.parent/'runtime-bundle')
            pending = [op for op in self.store.operations() if op['status'] == 'pending']
            for batch in push_batches(pending):
                answer = api.call('POST', 'push', json={'operations': batch})
                expected = {op['uuid'] for op in batch}; seen = set()
                for result in answer['results']:
                    if result['uuid'] not in expected or result['uuid'] in seen:
                        raise RemoteError(502, 'El servidor devolvió confirmaciones inesperadas. Se conservaron los pendientes.')
                    seen.add(result['uuid']); self.store.ack(result)
                if seen != expected:
                    raise RemoteError(502, 'Faltan confirmaciones del servidor. Se conservan las operaciones sin confirmar.')
            import_workspace_edits(self.store, Path(__file__).resolve().parent.parent/'runtime-bundle')
            claims = self._metadata(api.call('POST', 'renew'), profile)
            if 'clientes.ver' in claims['scopes'] and self.store.meta('capabilities', {}).get('dossiers') == 1:
                from .dossiers import download_dossiers
                download_dossiers(self.store, api, claims)
            else:
                self.store.replace('dossiers', [])
            for entity, permission in [('clients', 'clientes.ver'), ('products', 'pos.vender'), ('ledger', 'contabilidad.ver')]:
                if permission not in claims['scopes']:
                    self.store.replace(entity, []); continue
                rows = []; cursor = 0
                while True:
                    response = api.call('GET', 'catalog', params={'entity': entity, 'after': cursor})
                    rows.extend(response['rows'])
                    next_cursor = response.get('next')
                    if next_cursor is None:
                        break
                    if int(next_cursor) <= cursor:
                        raise RemoteError(502, 'No se pudo completar el catálogo. Se conserva la copia anterior.')
                    cursor = int(next_cursor)
                self.store.replace(entity, rows)
            self.store.set_meta('last_sync', time.time())
            conflicts = sum(o['status'] == 'conflict' for o in self.store.operations())
            return 'Sincronización completa.' + (f' Hay {conflicts} operación(es) que requieren revisión.' if conflicts else '')
        except RemoteError as exc:
            if exc.status in (401, 403):
                self.store.set_meta('blocked', True)
            raise
        finally:
            api.close()
