# Mi CV · Corrección de Telegram 429 para Git

Versión de la corrección: **2026.11.25-telegram-429**. Se aplica sobre el módulo **Telegram del sistema con QR** ya entregado. Son archivos de código fuente para incorporar a su repositorio; no necesita ejecutar el parche Python en cada despliegue.

## Qué corrige

Las rutas que usaban límites numéricos de Laravel sin prefijo compartían el contador del mismo usuario. Las consultas automáticas del QR podían consumir intentos de preparar/confirmar CV y de iniciar sesión. Cada acción de Telegram utiliza ahora su propio prefijo y contador por usuario.

| Acción | Límite |
| --- | --- |
| Generar QR | 5 cada 5 minutos |
| Consultar QR | 60 por minuto |
| Solicitar código | 3 cada 10 minutos |
| Verificar acceso | 5 cada 5 minutos |
| Desconectar | 5 cada 5 minutos |
| Preparar CV | 10 por minuto |
| Confirmar envío | 10 por minuto |

Los contadores nuevos son independientes de los bloqueos antiguos. La autenticación, permisos, confirmación e integridad del CV permanecen. Los límites de Telegram como proveedor se siguen respetando. No cambian la base de datos, las credenciales ni la configuración de la cuenta conectada.

## Incorporar a Git

Extraiga el ZIP dentro de su copia local del repositorio, conservando subcarpetas. Contiene únicamente:

- `routes/telegram-cv.php`: rutas corregidas.
- `tests/Feature/TelegramLimitesTest.php`: pruebas de regresión.
- `LEAME-TELEGRAM-429-GIT.md`: esta guía.

Revise y publique desde esa copia:

```bash
git diff -- routes/telegram-cv.php
git add -- routes/telegram-cv.php tests/Feature/TelegramLimitesTest.php LEAME-TELEGRAM-429-GIT.md
git diff --cached --stat
git commit -m "Corrige contadores independientes de Telegram"
git push
```

`git push` utiliza el remoto y la rama que tenga configurados su repositorio. Esta entrega no configura un remoto ni publica un commit por usted. No incluya .env, sesiones, storage ni credenciales.

También se entrega `Mi-CV-Telegram-429-Git.patch` como alternativa al ZIP. Si su repositorio conserva las rutas de la versión QR anterior, puede usar `git apply --check /ruta/al/parche.patch` y después `git apply /ruta/al/parche.patch`. Si ya aplicó la misma corrección, utilice los archivos del ZIP y revise el diff; no aplique ambas alternativas.

## Actualizar el VPS desde Git

Una vez incorporados y publicados los archivos en la rama que despliega:

```bash
cd /var/www/micv
git status --short
git pull --ff-only
sudo -u www-data php artisan route:clear
```

Si ya ejecutó el parche manual en el VPS, puede haber modificaciones locales en `routes/telegram-cv.php`. Antes del pull revise ese diff. Para conservar una copia de la modificación de un archivo que Git ya rastrea, puede guardarla en un stash:

```bash
git ls-files --error-unmatch routes/telegram-cv.php
git diff -- routes/telegram-cv.php
git stash push -m "Respaldo de correccion Telegram 429" -- routes/telegram-cv.php
git pull --ff-only
sudo -u www-data php artisan route:clear
```

El stash conserva la modificación local y el código corregido llega desde el repositorio. No haga `stash pop` automáticamente, pues volvería a aplicar el mismo cambio. Si el diff contiene personalizaciones adicionales, revíselas antes de decidir qué recuperar. Si Git indica que el archivo no está rastreado o que hay otros cambios, no fuerce el pull: conserve esos archivos y resuelva su incorporación al repositorio. Un cambio de código no puede garantizar que una rama con modificaciones o commits divergentes se actualice sin conflictos.

No es necesario reinstalar Python/Node, ejecutar migraciones, borrar toda la caché o reiniciar los servicios de Telegram para esta corrección de rutas. Recargue el navegador después de actualizar.

## Comprobación

**3 pruebas, 71 aserciones**: 45 consultas consecutivas de QR no consumen preparación, confirmación o acceso; la preparación conserva su límite propio; un contador antiguo agotado no bloquea los nuevos. No se enviaron mensajes ni se modificó una cuenta real de Telegram. El parche se comprobó con `git apply --check` y se compararon los archivos resultantes contra los del ZIP.
