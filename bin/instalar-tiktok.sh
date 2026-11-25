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
systemctl stop micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-tiktok-limpieza.timer micv-tiktok-limpieza.service 2>/dev/null || true
runuser -u "$micv_usuario" -- "$micv_php" artisan optimize:clear
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000001_create_tiktok_monitor_tables.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000002_add_tiktok_evidencia.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000003_add_tiktok_invitados.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000004_add_tiktok_deteccion.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000005_reprogramar_tiktok_acceso.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000006_add_tiktok_sala_publica.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_23_000007_add_tiktok_fuentes.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_25_000008_add_tiktok_caducidad.php
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_25_000009_create_tiktok_telegram.php
runuser -u "$micv_usuario" -- "$micv_php" artisan micv:tiktok-limpiar-informes
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
cat > /etc/systemd/system/micv-tiktok-telegram.service <<EOF
[Unit]
Description=Comentarios de TikTok a un bot Telegram - Mi CV
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan micv:tiktok-telegram
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=30
UMask=0077
NoNewPrivileges=true
[Install]
WantedBy=multi-user.target
EOF
cat > /etc/systemd/system/micv-tiktok-limpieza.service <<EOF
[Unit]
Description=Limpieza de informes temporales de TikTok - Mi CV
[Service]
Type=oneshot
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan micv:tiktok-limpiar-informes --quiet
TimeoutStartSec=60
Nice=10
UMask=0077
NoNewPrivileges=true
EOF
cat > /etc/systemd/system/micv-tiktok-limpieza.timer <<EOF
[Unit]
Description=Eliminar informes de TikTok caducados
[Timer]
OnBootSec=15s
OnUnitInactiveSec=15s
AccuracySec=1s
Unit=micv-tiktok-limpieza.service
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-tiktok-limpieza.timer
systemctl restart micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-tiktok-limpieza.timer
systemctl status micv-tiktok micv-tiktok-pdf micv-tiktok-evidencias micv-tiktok-telegram micv-tiktok-limpieza.timer --no-pager -l
echo 'Módulo instalado. Abra TikTok · Lives y comentarios como administrador.'
echo 'Registre un @usuario y active la captura pública. La primera detección puede tardar unos segundos.'
echo 'Diagnóstico: journalctl -u micv-tiktok -u micv-tiktok-pdf -u micv-tiktok-evidencias -u micv-tiktok-telegram -u micv-tiktok-limpieza -n 60 --no-pager'
