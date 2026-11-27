#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
micv_php="${MICV_PHP_BIN:-$(command -v php)}"
micv_usuario="${MICV_WEB_USUARIO:-www-data}"
[[ $EUID -eq 0 ]] || { echo 'Ejecute: sudo bash bin/instalar-postulaciones.sh' >&2; exit 1; }
[[ "$micv_raiz" != *[[:space:]]* && "$micv_php" != *[[:space:]]* ]] || { echo 'La ruta del proyecto y de PHP no debe contener espacios.' >&2; exit 1; }
[[ -f "$micv_raiz/.env" && -f "$micv_raiz/vendor/autoload.php" ]] || { echo 'Falta .env o vendor. Instale el proyecto base antes de esta actualización.' >&2; exit 1; }
id "$micv_usuario" >/dev/null
command -v systemctl >/dev/null
command -v runuser >/dev/null
cd "$micv_raiz"
mkdir -p storage/app/private/postulaciones
chown "$micv_usuario:$micv_usuario" storage/app/private/postulaciones
chmod 700 storage/app/private/postulaciones
runuser -u "$micv_usuario" -- "$micv_php" artisan optimize:clear
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_22_000001_create_postulaciones_tables.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_26_000012_add_retencion_cv_postulaciones.php
runuser -u "$micv_usuario" -- "$micv_php" artisan micv:cv-limpiar-temporales
cat > /etc/systemd/system/micv-postulaciones.service <<EOF
[Unit]
Description=Envios de CV a empresas - Mi CV
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan queue:work postulaciones --queue=postulaciones --sleep=2 --tries=1 --timeout=90 --max-time=3600 -v
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=110
UMask=0077

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable micv-postulaciones
systemctl restart micv-postulaciones
systemctl status micv-postulaciones --no-pager -l
echo 'Instalación terminada. Abra CV a empresas → Gmail por cuenta, guarde las 16 letras y compruebe la conexión.'
echo 'Para revisar el trabajador: journalctl -u micv-postulaciones -n 50 --no-pager'
