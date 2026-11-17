#!/usr/bin/env bash
set -Eeuo pipefail
micv_raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$micv_raiz"
micv_php="${MICV_PHP_BIN:-$(command -v php)}"
micv_destino="$micv_raiz/storage/app/video-voz"
micv_usuario="${MICV_VIDEO_USUARIO:-www-data}"
if [[ $(uname -m) != x86_64 ]]; then
    echo 'Este instalador probado requiere Linux x86_64. Para otras arquitecturas use Piper según la guía.' >&2
    exit 1
fi
if ! grep -qw avx2 /proc/cpuinfo; then
    echo 'Esta versión de Pocket TTS requiere una CPU con AVX2. Use la alternativa Piper de la guía.' >&2
    exit 1
fi
for micv_herramienta in curl tar sha256sum ffmpeg; do
    command -v "$micv_herramienta" >/dev/null || { echo "Falta $micv_herramienta. Instale las dependencias indicadas en LEAME-VIDEO-CINE.md." >&2; exit 1; }
done
[[ -f .env && -f vendor/autoload.php ]] || { echo 'Ejecute este instalador en el proyecto instalado, con .env y vendor existentes.' >&2; exit 1; }
micv_temporal="$(mktemp -d)"
trap 'rm -rf -- "$micv_temporal"' EXIT
chmod 755 "$micv_temporal"
mkdir -p "$micv_destino/bin" "$micv_destino/cache"
if [[ $EUID -eq 0 ]]; then
    id "$micv_usuario" >/dev/null
    chown -R "$micv_usuario:" "$micv_destino"
fi
micv_ejecutar() {
    if [[ $EUID -eq 0 ]]; then
        runuser -u "$micv_usuario" -- "$@"
    else
        "$@"
    fi
}
echo 'Descargando Pocket TTS y verificando su integridad…'
curl --fail --location --retry 2 'https://github.com/kyutai-labs/pocket-tts/releases/download/v4.0.1/pocket-tts-3.3.0-x86_64-unknown-linux-gnu.tar.gz' -o "$micv_temporal/pocket.tar.gz"
printf '%s  %s\n' '9abff0aa138a804e44052952d8c3bdaae247fb267ca8f4b83fe178897d1a0559' "$micv_temporal/pocket.tar.gz" | sha256sum --check --status
tar -xzf "$micv_temporal/pocket.tar.gz" -C "$micv_temporal" --no-same-owner
echo 'Preparando la voz española Lola. La primera vez se descargarán los modelos oficiales…'
micv_ejecutar env HF_HOME="$micv_destino/cache" "$micv_temporal/pocket-tts-3.3.0-x86_64-unknown-linux-gnu/pocket-tts" generate --language spanish --voice lola --text 'Tu experiencia merece una buena presentación. Vamos a crear tu currículum paso a paso.' --output-path "$micv_destino/prueba-voz.wav" --quiet
ffmpeg -hide_banner -loglevel error -i "$micv_destino/prueba-voz.wav" -f null -
install -m 755 "$micv_temporal/pocket-tts-3.3.0-x86_64-unknown-linux-gnu/pocket-tts" "$micv_destino/bin/pocket-tts"
install -m 644 "$micv_temporal/pocket-tts-3.3.0-x86_64-unknown-linux-gnu/LICENSE" "$micv_destino/LICENSE-Pocket-TTS"
[[ -e "$micv_destino/.env-anterior" ]] || { cp -p .env "$micv_destino/.env-anterior"; chmod 600 "$micv_destino/.env-anterior"; }
"$micv_php" resources/video-promocional/configurar-voz.php "$micv_raiz/.env" "$micv_destino/bin/pocket-tts"
micv_ejecutar "$micv_php" artisan optimize:clear
if [[ $EUID -eq 0 ]] && command -v systemctl >/dev/null && systemctl cat micv-video.service >/dev/null 2>&1; then
    systemctl restart micv-video
    systemctl is-active micv-video
else
    echo 'Reinicie su trabajador de video para que lea la nueva configuración.'
fi
echo 'Voz neuronal lista. Ya puede generar un video nuevo desde el panel.'
