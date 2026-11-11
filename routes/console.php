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

Artisan::command('desktop:pending-cv {--after=0}', function () {
    if (getenv('MICV_READ_LOCAL_EDITS') !== '1' || \Illuminate\Support\Facades\DB::getDefaultConnection() !== 'sqlite') {
        return 1;
    }
    $operations = [];
    $next = null;
    if (\Illuminate\Support\Facades\Schema::hasTable('local_cv_edits')) {
        $rows = \Illuminate\Support\Facades\DB::table('local_cv_edits')->whereNotNull('operation')->where('client_id','>',max(0,(int)$this->option('after')))
            ->orderBy('client_id')->limit(100)->get();
        if ($rows->count() === 100) $next = $rows->last()->client_id;
        foreach ($rows as $row) {
            $operations[] = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($row->operation), true, flags: JSON_THROW_ON_ERROR);
        }
    }
    $this->output->write(json_encode(['operations'=>$operations,'next'=>$next], JSON_THROW_ON_ERROR));
    return 0;
});

Artisan::command('desktop:confirm-cv', function () {
    if (getenv('MICV_READ_LOCAL_EDITS') !== '1' || \Illuminate\Support\Facades\DB::getDefaultConnection() !== 'sqlite') {
        return 1;
    }
    $receipts = json_decode(stream_get_contents(STDIN, 256 * 1024), true, flags: JSON_THROW_ON_ERROR);
    foreach ($receipts as $receipt) {
        if (!preg_match('/^[a-f0-9]{64}$/D', $receipt['revision'])) {
            return 1;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($receipt) {
            if (!empty($receipt['created'])) {
                $exists = \Illuminate\Support\Facades\DB::table('local_business_edits')
                    ->where('uuid',$receipt['uuid'])->where('type','client.full.create')->where('entity_id',$receipt['client_id'])->exists();
                if ($exists) {
                    \Illuminate\Support\Facades\DB::table('local_cv_edits')->insertOrIgnore([
                        'client_id'=>$receipt['client_id'],'revision'=>$receipt['revision'],
                    ]);
                }
                return;
            }
            $entry = \Illuminate\Support\Facades\DB::table('local_cv_edits')->where('client_id',$receipt['client_id'])->first();
            if (!$entry?->operation) {
                return;
            }
            $operation = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($entry->operation),true,flags:JSON_THROW_ON_ERROR);
            if ($operation['uuid'] === $receipt['uuid']) {
                \Illuminate\Support\Facades\DB::table('local_cv_edits')->where('client_id',$entry->client_id)
                    ->where('operation',$entry->operation)->update(['revision'=>$receipt['revision'],'operation'=>null]);
            }
        });
    }
    return 0;
});

Artisan::command('desktop:pending-business {--after=0}', function () {
    if (getenv('MICV_READ_LOCAL_EDITS') !== '1' || \Illuminate\Support\Facades\DB::getDefaultConnection() !== 'sqlite') {
        return 1;
    }
    $operations = [];
    $next = null;
    if (\Illuminate\Support\Facades\Schema::hasTable('local_business_edits')) {
        $rows = \Illuminate\Support\Facades\DB::table('local_business_edits')
            ->where('id','>',max(0,(int)$this->option('after')))->orderBy('id')->limit(100)->get();
        if ($rows->count() === 100) $next = $rows->last()->id;
        foreach ($rows as $row) {
            $operations[] = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($row->operation), true, flags: JSON_THROW_ON_ERROR);
        }
    }
    $this->output->write(json_encode(['operations'=>$operations,'next'=>$next], JSON_THROW_ON_ERROR));
    return 0;
});
