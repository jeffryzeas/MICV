<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TikTokCuenta extends Model {
    protected $table='tiktok_cuentas';protected $guarded=['id'];
    public const ESTADOS=['inactivo'=>'Pausado','pendiente'=>'Esperando al capturador','conectando'=>'Conectando','en_live'=>'En directo','sin_live'=>'Sin sala pública detectada','no_determinado'=>'No se pudo determinar la sala','restringido'=>'Esperando acceso al LIVE','acceso_pendiente'=>'Esperando acceso al LIVE','error'=>'Conexión pendiente · reintento automático','limitado'=>'Esperando por límite de TikTok'];
    protected function casts(): array{return ['monitorear'=>'boolean','perfil_publico'=>'array','latido_ms'=>'integer','reintentar_ms'=>'integer'];}
    public function etiquetaEstado(): string {return $this->estado==='en_live'&&$this->sala_publica_usuario?($this->fuentes()->count()>1?'Hay chats conectados':'Chat de la sala en directo'):(self::ESTADOS[$this->estado]??$this->estado);}
    public function fuentes(){return $this->hasMany(TikTokFuente::class,'cuenta_id');}
    public function salasPublicas(): array { $n=$this->fuentes()->orderBy('orden')->orderBy('id')->pluck('usuario')->all();return $n?:($this->sala_publica_usuario?[$this->sala_publica_usuario]:[]);}
    public function anfitrion(){return $this->belongsTo(self::class,'anfitrion_id');}
    public function invitados(){return $this->hasMany(self::class,'anfitrion_id');}
    public function lives(){return $this->hasMany(TikTokLive::class,'cuenta_id');}
    public function publicaciones(){return $this->hasMany(TikTokPublicacion::class,'cuenta_id');}
    public function comentarios(){return $this->hasMany(TikTokComentario::class,'cuenta_id');}
    public function perfilUrl(): string{return 'https://www.tiktok.com/@'.$this->usuario;}
    public function liveUrl(): string{return $this->perfilUrl().'/live';}
}
