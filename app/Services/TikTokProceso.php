<?php
namespace App\Services;
use Symfony\Component\Process\Process;
class TikTokProceso {
    public function crear(string $usuario): Process {
        $p=new Process([config('tiktok.python'),'-u',resource_path('tiktok/capturar.py'),$usuario],base_path(),['PYTHONUNBUFFERED'=>'1']);$p->setTimeout(null);return $p;
    }
}
