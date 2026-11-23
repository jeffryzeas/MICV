#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
[[ $EUID -eq 0 ]] || { echo 'Ejecute: sudo bash bin/actualizar-correo-comentarios.sh' >&2; exit 1; }
bash "$micv_raiz/bin/instalar-postulaciones.sh"
bash "$micv_raiz/bin/instalar-tiktok.sh"
echo 'Actualización terminada. Prepare un correo nuevo y genere un informe nuevo para ver los cambios.'
echo 'TikTok: registre solo el @usuario y active la captura. Las salas públicas se buscan automáticamente.'
