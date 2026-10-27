from __future__ import annotations
import socket, time
from .api import Api, RemoteError, OfflineError
from .security import AccessError, check_profile, pin_hash, verify_lease

DEFAULT_SERVER_URL = 'https://micv.sistemajz.com'

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
        return claims

    def run(self):
        profile = self.store.meta('profile')
        if not profile:
            raise AccessError('Primero active el programa con su cuenta y licencia.')
        api = self.factory(profile, self.store.meta('token'))
        try:
            claims = self._metadata(api.call('POST', 'renew'), profile)
            pending = [op for op in self.store.operations() if op['status'] == 'pending']
            for start in range(0, len(pending), 20):
                batch = [{key: op[key] for key in ('uuid', 'type', 'occurred_at', 'data')} for op in pending[start:start+20]]
                answer = api.call('POST', 'push', json={'operations': batch})
                expected = {op['uuid'] for op in batch}; seen = set()
                for result in answer['results']:
                    if result['uuid'] not in expected or result['uuid'] in seen:
                        raise RemoteError(502, 'El servidor devolvió confirmaciones inesperadas. Se conservaron los pendientes.')
                    seen.add(result['uuid']); self.store.ack(result)
                if seen != expected:
                    raise RemoteError(502, 'Faltan confirmaciones del servidor. Se conservan las operaciones sin confirmar.')
            claims = self._metadata(api.call('POST', 'renew'), profile)
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
