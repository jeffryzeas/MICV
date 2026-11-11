"""Token-bound web session inside the native shell; no credentials in URLs."""
import re
import time
from .security import AccessError, check_profile
from .service import Service

class ConnectedEngine:
    connected = True

    def __init__(self, store):
        self.store = store
        self.origin = check_profile(store.meta('profile', {}))['server_url']
        self.token = store.meta('token', '')
        if not isinstance(self.token, str) or not re.fullmatch(r'[A-Za-z0-9]{64}', self.token):
            raise AccessError('Vuelva a iniciar sesión para conectar este equipo.')
        self.configuration()

    def configuration(self):
        if self.store.meta('blocked', False):
            raise AccessError('El servidor desactivó este acceso.')
        if time.time() + 300 < self.store.meta('last_clock', 0):
            raise AccessError('Corrija la fecha del equipo antes de conectar.')
        return Service(self.store).claims(valid=True)

    def request_headers(self):
        return {b'Authorization': ('Bearer ' + self.token).encode('ascii'), b'X-MiCV-Desktop': b'1'}

    def refresh(self):
        from .api import Api, RemoteError
        from .sync import Sync
        profile=self.store.meta('profile')
        api=Api(profile,self.token)
        try:
            response=api.call('POST','renew')
            Sync(self.store)._metadata(response,profile)
        except RemoteError as exc:
            if exc.status in (401,403):self.store.set_meta('blocked',True)
            raise
        finally:api.close()

    def stop(self):
        pass
