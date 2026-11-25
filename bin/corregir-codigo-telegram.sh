#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$micv_raiz"
python3 - <<'PY'
from pathlib import Path
import ast
import shutil
p = Path('resources/telegram-cv/cliente.py')
s = p.read_text()
viejo = 'request_retries=0, auto_reconnect=False'
nuevo = 'request_retries=(0 if d.get("accion") == "enviar" else 2), raise_last_call_error=True, auto_reconnect=False'
if viejo in s:
    corregido = s.replace(viejo, nuevo, 1)
    ast.parse(corregido)
    respaldo = p.with_name('cliente.py.antes-codigo.bak')
    if not respaldo.exists():
        shutil.copy2(p, respaldo)
    p.write_text(corregido)
    print('Corrección aplicada y copia del archivo anterior conservada.')
elif nuevo in s:
    print('La corrección del código de Telegram ya está aplicada.')
else:
    raise SystemExit('La versión del archivo es distinta. No se modificó; instale el parche completo.')
print('Espere al menos un minuto desde el último intento y solicite el código en el panel.')
PY
