# Corrección: nacionalidad, residencia y campos laborales

## Regla aplicada

Se compara el país de la **nacionalidad declarada** con el **país de residencia actual**. El país emisor del documento no decide la visibilidad.

- Nicaragüense + residencia en Nicaragua: se ocultan Disponibilidad y Cualidades. La autorización laboral sigue la misma regla existente.
- Nicaragüense + residencia en Costa Rica: se muestran los campos. Disponibilidad y Cualidades continúan siendo opcionales.
- Los campos responden al cambio de nacionalidad o residencia sin guardar ni recargar el formulario.
- Se reconocen nacionalidades del catálogo en masculino y femenino, nombres de países, mayúsculas y variantes sin tildes.
- Si todavía no se declaró la nacionalidad o la residencia, los campos complementarios permanecen ocultos.

La regla se aplica a la ficha administrativa, al portal del cliente y al autoservicio. En el CV Carta Amarillo y Azul y en Word tampoco se muestran los valores de estos campos cuando coinciden nacionalidad y residencia.

## Ejemplos de escritura

Los ejemplos son ayudas y no se guardan automáticamente:

- Situación / autorización laboral: «Cuento con permiso de trabajo vigente». También se orienta a declarar otras situaciones, como en trámite o sin autorización.
- Disponibilidad: «Cuento con permiso de trabajo vigente y disponibilidad inmediata». El ejemplo completo aparece debajo del campo para que pueda leerse desde el celular.
- Cualidades: «Responsable, puntual, trabajo en equipo».

## Datos existentes y permisos

Disponibilidad y Cualidades anteriores se conservan en la base de datos. Al coincidir los países, los campos quedan ocultos y deshabilitados en el formulario, se omiten del envío y no se imprimen en los CV indicados. Si un cliente envía esos campos de forma manual mientras deben estar ocultos, no se aplican al guardado.

Se conserva el comportamiento previo de autorización laboral: se limpia al guardar una nacionalidad y residencia coincidentes. No se cambia la nacionalidad por el país del documento. La visibilidad tampoco habilita controles que el administrador haya bloqueado mediante permisos.

No se requieren migraciones ni modificaciones manuales a la base de datos.

## Aplicar en el VPS

Este ZIP contiene directamente las carpetas `app/`, `public/`, `resources/` y `tests/`. No tiene un ZIP dentro ni una carpeta adicional `mi-cv`.

1. Guarde un respaldo de los archivos que va a sustituir.
2. Suba `Correccion-Campos-Laborales.zip` al VPS.
3. Extraiga su contenido dentro de `/var/www/micv`, conservando su `.env`, su base de datos y su almacenamiento. Por ejemplo, si subió el ZIP a `/root`:

   ```bash
   unzip -t /root/Correccion-Campos-Laborales.zip
   unzip -o /root/Correccion-Campos-Laborales.zip -d /var/www/micv
   cd /var/www/micv
   sudo -u www-data php artisan optimize:clear
   ```

4. Recargue la página y pruebe ambos casos. El JavaScript incluye versión por fecha de archivo para actualizar la caché del navegador.

No es necesario instalar nuevas dependencias de Node o Composer ni modificar el servicio del video.

## Verificación

Se completaron 30 pruebas PHP (184 aserciones), incluida la generación real de Word y la conservación de datos y permisos. Dos pruebas de navegador comprobaron los cambios de nacionalidad/residencia, la exclusión de controles ocultos del envío y los campos restringidos del portal.

Para repetir las pruebas específicas:

```bash
php artisan test --filter=CamposLaboralesTest
php artisan test --filter=SituacionLaboralTest
MICV_CHROME_PATH=/usr/bin/chromium npm run test:campos-laborales
```

Ajuste `MICV_CHROME_PATH` a su navegador instalado al ejecutar la prueba de navegador.
