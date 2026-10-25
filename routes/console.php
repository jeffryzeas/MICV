<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza diaria (auditoría de más de 12 meses, códigos vencidos, registros sin verificar).
// En el VPS agregue al cron:  * * * * * cd /var/www/mi-cv && php artisan schedule:run >> /dev/null 2>&1
Artisan::command('micv:limpiar', function () {
    $r = \App\Support\Limpieza::ejecutar();
    $this->info('Borrados → auditoría: ' . $r['auditoria'] . ' · códigos: ' . $r['codigos'] . ' · registros sin verificar: ' . $r['pendientes'] . ' · autoservicio: ' . $r['autoservicio']);
})->purpose('Borra datos vencidos según la política de privacidad');

\Illuminate\Support\Facades\Schedule::command('micv:limpiar')->dailyAt('03:15');

Artisan::command('desktop:keys', function () {
 if(!function_exists('sodium_crypto_sign_keypair')){$this->error('Active la extensión sodium de PHP.');return 1;}
 $path=config('desktop.signing_key');
 if(!is_file($path)){
  if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
  $pair=sodium_crypto_sign_keypair();$key=base64_encode(sodium_crypto_sign_secretkey($pair));
  $file=fopen($path,'x');if(!$file)throw new RuntimeException('No se pudo crear la clave.');chmod($path,0600);fwrite($file,$key);fclose($file);
 }
 $this->info('Firma preparada. Clave pública: '.app(\App\Services\Desktop\LicenseSigner::class)->publicKey());
})->purpose('Prepara la firma privada de licencias sin sobrescribir una existente');
