#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
micv_php="${MICV_PHP_BIN:-$(command -v php)}"
micv_usuario="${MICV_WEB_USUARIO:-www-data}"
[[ $EUID -eq 0 ]] || { echo 'Ejecute: sudo bash bin/instalar-telegram-cv.sh' >&2; exit 1; }
[[ "$micv_raiz" != *[[:space:]]* && "$micv_php" != *[[:space:]]* ]] || { echo 'Use rutas sin espacios.' >&2; exit 1; }
[[ -f "$micv_raiz/.env" && -f "$micv_raiz/vendor/autoload.php" ]] || { echo 'Instale primero el proyecto base con .env y vendor.' >&2; exit 1; }
id "$micv_usuario" >/dev/null
command -v systemctl >/dev/null
command -v runuser >/dev/null
cd "$micv_raiz"
if ! command -v python3 >/dev/null; then apt-get update; apt-get install -y python3 python3-venv; fi
micv_venv="$micv_raiz/storage/app/telegram-cv-venv"
if ! python3 -m venv "$micv_venv"; then apt-get update; apt-get install -y python3-venv; python3 -m venv "$micv_venv"; fi
"$micv_venv/bin/python" -m pip install --disable-pip-version-check --no-cache-dir -r resources/telegram-cv/requirements.txt
"$micv_venv/bin/python" -c 'from telethon import TelegramClient; from telethon.sessions import StringSession; print("Cliente de Telegram disponible")'
mkdir -p storage/app/private/telegram-cv
chown -R "$micv_usuario:$micv_usuario" storage/app/private/telegram-cv
chmod 700 storage/app/private/telegram-cv
systemctl stop micv-telegram-cv 2>/dev/null || true
runuser -u "$micv_usuario" -- "$micv_php" artisan optimize:clear
runuser -u "$micv_usuario" -- "$micv_php" artisan migrate --force --path=database/migrations/2026_11_25_000010_create_telegram_cv.php
cat > /etc/systemd/system/micv-telegram-cv.service <<EOF
[Unit]
Description=Mensajes y CV desde cuenta personal de Telegram - Mi CV
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=$micv_usuario
WorkingDirectory=$micv_raiz
ExecStart=$micv_php $micv_raiz/artisan queue:work telegram-cv --queue=telegram-cv --sleep=2 --tries=1 --timeout=270
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=300
UMask=0077
NoNewPrivileges=true
Environment=PYTHONDONTWRITEBYTECODE=1
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now micv-telegram-cv
systemctl restart micv-telegram-cv
systemctl status micv-telegram-cv --no-pager -l
echo 'Abra CV por mi Telegram → Mi número de Telegram como administrador.'
echo 'Guarde api_id/api_hash allí y complete el código de Telegram. No los envíe por chat.'
echo 'Diagnóstico: journalctl -u micv-telegram-cv -n 60 --no-pager'
