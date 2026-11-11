from __future__ import annotations
import httpx
import hashlib

class RemoteError(Exception):
    def __init__(self, status, message):
        self.status = status
        super().__init__(message)

class OfflineError(Exception):
    pass

def server_unavailable(error):
    return isinstance(error, OfflineError) or (
        isinstance(error, RemoteError) and error.status in (500, 502, 503, 504)
    )


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

    def workspace_file(self, item):
        params={key:item[key] for key in ('table','key','field','sha256')}
        expected=item['size']
        if type(expected) is not int or not 0<=expected<=50*1024*1024:
            raise RemoteError(413,'El archivo supera el tamaño permitido.')
        chunks=[];size=0;digest=hashlib.sha256()
        try:
            with self.client.stream('GET',self.url+'workspace/file',params=params) as response:
                if response.status_code!=200:
                    raise RemoteError(response.status_code,'No se pudo descargar el archivo autorizado. Actualice la copia local.')
                for chunk in response.iter_bytes(65536):
                    size+=len(chunk)
                    if size>expected:raise RemoteError(502,'El tamaño del archivo no coincide con la descarga firmada.')
                    digest.update(chunk);chunks.append(chunk)
        except httpx.TransportError as exc:
            raise OfflineError('Se interrumpió la descarga. La copia anterior se conserva.') from exc
        if size!=expected or digest.hexdigest()!=item['sha256']:
            raise RemoteError(502,'El archivo cambió o está incompleto. La copia anterior se conserva.')
        return b''.join(chunks)

    def close(self):
        self.client.close()
