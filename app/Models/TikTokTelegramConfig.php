<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class TikTokTelegramConfig extends Model {
 public const DESTINO='8476238020';
 public const USUARIO_DESTINO='jeffryzeasni';
 protected $table='tiktok_telegram_config';protected $guarded=[];protected $hidden=['bot_token'];
 protected function casts(): array{return ['bot_token'=>'encrypted','activo'=>'boolean','cuentas'=>'array','verificada_en'=>'datetime','no_antes_de'=>'datetime'];}
 public static function actual(): self {return self::firstOrCreate(['id'=>1],['revision'=>(string)Str::uuid(),'activo'=>false,'alcance'=>'todas','chat_id'=>self::DESTINO,'chat_tipo'=>'private']);}
 public function incluye(int $id): bool {return $this->alcance==='todas'||in_array($id,array_map('intval',$this->cuentas??[]),true);}
}
