<?php
namespace App\Services;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FotoProducto
{
    public static function guardar(UploadedFile $archivo): string
    {
        $dir=storage_path('app/tmp-productos');if(!is_dir($dir))mkdir($dir,0775,true);
        $salida=$dir.'/'.Str::random(24).'.jpg';
        try {
            Nodo::correr(resource_path('images/producto-foto.cjs'),[$archivo->getRealPath(),$salida],60);
            $bin=file_get_contents($salida);
            if($bin===false)throw new \RuntimeException('No se pudo leer la foto procesada.');
            $ruta='productos/'.Str::random(32).'.jpg';
            if(!Storage::disk(Archivos::DISCO)->put($ruta,$bin))throw new \RuntimeException('No se pudo guardar la foto.');
            return $ruta;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['foto'=>'No se pudo procesar la foto. Use JPG, PNG o WebP de hasta 8 MB.']);
        } finally { @unlink($salida); }
    }
}
