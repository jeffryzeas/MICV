<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\TikTokTelegram;
class TikTokTelegramEnviar extends Command {
 protected $signature='micv:tiktok-telegram {--duracion=0} {--una-vez}';protected $description='Envía comentarios nuevos a un bot configurado, respetando las esperas de Telegram';
 public function handle(TikTokTelegram $telegram): int {
  $dir=config('tiktok.directorio');File::ensureDirectoryExists($dir,0700);$lock=fopen($dir.'/telegram.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){$this->error('Ya hay un trabajador de Telegram activo.');return 1;}
  $seguir=true;$fin=$this->option('duracion')?microtime(true)+(int)$this->option('duracion'):INF;
  if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$seguir){$seguir=false;});pcntl_signal(SIGINT,function()use(&$seguir){$seguir=false;});}
  try{do{$hecho=$telegram->procesarUno();if($this->option('una-vez'))break;usleep($hecho?100000:500000);}while($seguir&&microtime(true)<$fin);return 0;}
  finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}
