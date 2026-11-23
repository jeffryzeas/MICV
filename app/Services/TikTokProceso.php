<?php
namespace App\Services;
use Symfony\Component\Process\Process;
class TikTokProceso {
    public function crear(string $usuario,?string $salaPublica=null): Process {
        $args=[config('tiktok.python'),'-u',resource_path('tiktok/capturar.py'),$usuario];if($salaPublica!==null)$args=array_merge($args,['--sala-publica',$salaPublica]);
        $p=new Process($args,base_path(),['PYTHONUNBUFFERED'=>'1']);$p->setTimeout(null);return $p;
    }
}
