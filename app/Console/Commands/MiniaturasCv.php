<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\{CvDatos,CvEjemplo,CvPdf};
class MiniaturasCv extends Command {
 protected $signature='micv:miniaturas-cv {--html : Guardar HTML de ejemplo para revisión}';
 protected $description='Genera capturas reales de las veinte plantillas con datos ficticios, sin crear clientes.';
 public function handle(): int {
  $cliente=CvEjemplo::cliente();$dir=public_path('images/cv');if(!is_dir($dir))mkdir($dir,0775,true);
  foreach(CvDatos::DISENOS as $key=>$name){$html=view('cv.'.$key,CvDatos::armar($cliente,$key,'azul',false))->render();if($this->option('html')){$out=storage_path('app/ejemplos-cv');if(!is_dir($out))mkdir($out,0775,true);file_put_contents($out.'/'.$key.'.html',$html);}else{$b=CvPdf::navegador($html)->windowSize(816,1056)->showBackground();if(CvDatos::esPro($key))$b->waitForFunction('window.cvListo===true',null,15000);file_put_contents($dir.'/'.$key.'.png',$b->screenshot());}$this->line($name);}
  return self::SUCCESS;
 }
}
