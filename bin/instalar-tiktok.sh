#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
micv_php="${MICV_PHP_BIN:-$(command -v php)}"
micv_usuario="${MICV_WEB_USUARIO:-www-data}"
[[ $EUID -eq 0 ]] || { echo 'Ejecute: sudo bash bin/instalar-tiktok.sh' >&2; exit 1; }
[[ "$micv_raiz" != *[[:space:]]* && "$micv_php" != *[[:space:]]* ]] || { echo 'Use rutas sin espacios.' >&2; exit 1; }
[[ -f "$micv_raiz/.env" && -f "$micv_raiz/vendor/autoload.php" ]] || { echo 'Falta el proyecto base instalado (.env y vendor).' >&2; exit 1; }
id "$micv_usuario" >/dev/null
cd "$micv_raiz"
command -v systemctl >/dev/null
command -v runuser >/dev/null
if ! command -v python3 >/dev/null; then apt-get update; apt-get install -y python3 python3-venv; fi
micv_venv="$micv_raiz/storage/app/tiktok-venv"
if ! python3 -m venv "$micv_venv"; then apt-get update; apt-get install -y python3-venv; python3 -m venv "$micv_venv"; fi
"$micv_venv/bin/python" -m pip install --disable-pip-version-check --no-cache-dir -r resources/tiktok/requirements.txt
"$micv_venv/bin/python" -c 'from TikTokLive import TikTokLiveClient; from TikTokLive.events import CommentEvent, ConnectEvent, LiveEndEvent; print("TikTokLive listo")'
mkdir -p storage/app/private/tiktok
chown -R "$micv_usuario:$micv_usuario" storage/app/private/tiktok
chmod 700 storage/app/private/tiktok
systemctl stop micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias 2>/dev/null || true
runuser -u "$micv_usuario" -- "$micv_php" artisan optimize:clear
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000001_create_tiktok_monitor_tables.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000002_add_tiktok_evidencia.php
cat > /etc/systemd/system/micv-tiktok.service <<EOF
[Unit]
Description=Captura de comentarios publicos de TikTok - Mi CV
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan micv:tiktok-monitor --duracion=0
Restart=always
RestartSec=10
KillMode=mixed
TimeoutStopSec=45
UMask=0077
Environment=PYTHONDONTWRITEBYTECODE=1
NoNewPrivileges=true
[Install]
WantedBy=multi-user.target
EOF
cat > /etc/systemd/system/micv-tiktok-pdf.service <<EOF
[Unit]
Description=Informes PDF de TikTok - Mi CV
After=network.target
[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan queue:work tiktok-exportaciones --queue=tiktok-exportaciones --sleep=2 --tries=1 --timeout=570 --max-time=3600
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=600
UMask=0077
[Install]
WantedBy=multi-user.target
EOF
cat > /etc/systemd/system/micv-tiktok-evidencias.service <<EOF
[Unit]
Description=Conservacion de fotos publicas de TikTok - Mi CV
After=network.target
[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan queue:work tiktok-evidencias --queue=tiktok-evidencias --sleep=2 --tries=1 --timeout=45 --max-time=3600
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=60
UMask=0077
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias
systemctl restart micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias
systemctl status micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias --no-pager -l
echo 'Módulo instalado. Abra TikTok · Lives y comentarios como administrador.'
echo 'Registre un @usuario y active la captura pública. La primera detección puede tardar unos segundos.'
echo 'Diagnóstico: journalctl -u micv-tiktok -u micv-tiktok-pdf -u micv-tiktok-evidencias -n 60 --no-pager'
