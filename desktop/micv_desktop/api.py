from __future__ import annotations
import httpx

class RemoteError(Exception):
    def __init__(self, status, message):
        self.status = status
        super().__init__(message)

class OfflineError(Exception):
    pass

class Api:
    def __init__(self, profile, token=None, transport=None):
        self.url = profile['server_url'] + '/api/desktop/v1/'
        self.client = httpx.Client(timeout=httpx.Timeout(20, connect=5), verify=True, follow_redirects=False, trust_env=False, transport=transport,
            headers={'Accept': 'application/json', 'Authorization': 'Bearer ' + token if token else '', 'User-Agent': 'MiCV-Desktop/1.0'})

    def call(self, method, path, **kwargs):
        try:
            response = self.client.request(method, self.url + path, **kwargs)
        except httpx.TransportError as exc:
            raise OfflineError('Sin conexión. Sus operaciones siguen guardadas localmente.') from exc
        if response.status_code != 200:
            try:
                value = response.json()
                message = ' '.join(str(item) for values in value.get('errors', {}).values() for item in values) or value.get('message')
            except (ValueError, AttributeError):
                message = None
            raise RemoteError(response.status_code, message or 'El servidor no pudo atender la solicitud. No se borraron sus pendientes.')
        try:
            return response.json()
        except ValueError as exc:
            raise RemoteError(502, 'La respuesta del servidor no es válida.') from exc

    def close(self):
        self.client.close()
