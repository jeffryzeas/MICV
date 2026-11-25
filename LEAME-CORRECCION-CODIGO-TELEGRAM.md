# Corrección de la solicitud del código de Telegram

Se encontró y reprodujo un defecto del adaptador: `request_retries=0` impide continuar una solicitud de autenticación después de que Telegram devuelve PHONE_MIGRATE/NETWORK_MIGRATE. Telethon cambia el centro de datos, agota el intento y antes emitía un ValueError genérico sin llegar a solicitar el código en el servidor correcto. Esto puede explicar el aviso reportado, pero no confirma por sí solo la causa del VPS.

La autenticación y consultas disponen ahora de dos reintentos de SDK y `raise_last_call_error=True` conserva el tipo del último error. El envío de texto/PDF mantiene cero reintentos de solicitud para no duplicar una operación incierta. No se fuerza la entrega por SMS.

## Aplicar el parche completo

Extraiga `Mi-CV-Correccion-Codigo-Telegram.zip` dentro de `/var/www/micv/` conservando subcarpetas. Solo contiene los archivos de esta corrección y sus pruebas. Conserve su .env y base de datos. Después:

```bash
cd /var/www/micv
sudo -u www-data php artisan optimize:clear
sudo systemctl restart micv-telegram-cv
```

Espere al menos un minuto desde el último intento, recargue el panel y solicite el código nuevamente. Revise Telegram en sus dispositivos; Telegram decide el canal. No hay migraciones nuevas y no debe volver a introducir credenciales que ya estaban guardadas.

El nuevo diagnóstico diferencia falta de ejecutable Python, dependencia Telethon, proc_open deshabilitado, tiempo agotado, respuesta inválida, fallo de conexión y errores temporales del proveedor. El panel muestra un identificador seguro y los rechazos del adaptador registran solo categorías fijas, sin stdin, número, sesión, api_hash, códigos o contraseñas. Nunca se registra la descripción original de una excepción o salida del proceso.

## Corrección mínima sin reinstalar

El script `sudo bash bin/corregir-codigo-telegram.sh` aplica solamente la corrección de reintentos sobre el archivo anterior; es idempotente, comprueba la sintaxis y conserva una copia `.bak` del código anterior. No incluye por sí solo las mejoras del diagnóstico PHP: esas requieren extraer el parche completo.

## Si el error continúa

Copie el código de diagnóstico mostrado por el panel. No comparta api_hash, código de acceso, contraseña ni sesión. Python/Telethon disponibles no prueban que el VPS tenga acceso a los centros de datos de Telegram. Puede comprobar solo conectividad TCP, sin credenciales y sin solicitar códigos, con:

```bash
cd /var/www/micv
sudo -u www-data storage/app/telegram-cv-venv/bin/python - <<'PY'
import socket
for host in ['149.154.167.51', '149.154.175.53', '149.154.175.100']:
    try:
        with socket.create_connection((host, 443), timeout=5):
            print(host, 'TCP 443 accesible')
    except OSError:
        print(host, 'TCP 443 no accesible')
PY
```

Una conexión TCP exitosa no confirma autenticación ni entrega del código. No cambie credenciales ni repita solicitudes rápidamente mientras Telegram indique una espera.

## Verificación

Pruebas del flujo real de `Telethon.send_code_request` con transporte simulado: reproduce el fallo anterior, cambia al centro de datos indicado y confirma la segunda solicitud con la corrección. Pruebas del adaptador en Python 3.12 y 3.14; no se usaron credenciales reales ni se solicitaron códigos a ningún número. Las pruebas PHP comprueban categorías de diagnóstico, eliminación de secretos y conservación del estado incierto ante fallos de conexión durante envíos.

El parche está preparado y probado localmente; su aplicación y resultado final en el VPS todavía requieren comprobarse.
