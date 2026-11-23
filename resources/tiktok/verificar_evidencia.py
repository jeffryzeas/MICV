#!/usr/bin/env python3
"""Verifica un paquete extraído. No requiere red ni dependencias externas."""
import hashlib
import json
from pathlib import Path
import sys

def verificar(raiz):
    manifest = json.loads((raiz / 'manifiesto.json').read_text(encoding='utf-8'))
    for nombre, info in manifest['archivos'].items():
        path = (raiz / nombre).resolve()
        if not path.is_relative_to(raiz) or not path.is_file():
            raise ValueError('Falta un archivo o su ruta no es válida: ' + nombre)
        digest = hashlib.sha256()
        with path.open('rb') as f:
            for block in iter(lambda: f.read(1048576), b''):
                digest.update(block)
        if digest.hexdigest() != info['sha256'] or path.stat().st_size != info['bytes']:
            raise ValueError('Integridad no coincidente: ' + nombre)
    total, heredados = 0, 0
    with (raiz / 'datos.ndjson').open(encoding='utf-8') as f:
        for linea in f:
            dato = json.loads(linea); total += 1
            if dato.get('hash_captura'):
                canonical = json.dumps(dato['registro'], ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode('utf-8')
                if hashlib.sha256(canonical).hexdigest() != dato['hash_captura']:
                    raise ValueError('El hash de captura no coincide en el registro ' + str(dato['id']))
            else:
                heredados += 1
    if total != manifest['resumen']['comentarios']:
        raise ValueError('La cantidad de registros no coincide.')
    print('Integridad de archivos verificada. Registros:', total, '| Sin hash original de captura:', heredados)
    print('Esta verificación no certifica identidad, exhaustividad ni fecha mediante una autoridad externa.')

if __name__ == '__main__':
    try:
        verificar(Path(sys.argv[1] if len(sys.argv)>1 else '.').resolve())
    except Exception as exc:
        print('VERIFICACIÓN FALLIDA:', exc, file=sys.stderr)
        sys.exit(1)
