<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DesktopLicense extends Model {
 protected $guarded=[];
 protected $hidden=['key_hash'];
 protected function casts(): array{return ['activa'=>'boolean','expires_at'=>'datetime','modulos'=>'array','device_limit'=>'integer','offline_days'=>'integer'];}
 public function cyber(){return $this->belongsTo(Cyber::class);}
 public function devices(){return $this->hasMany(DesktopDevice::class,'license_id');}
}
