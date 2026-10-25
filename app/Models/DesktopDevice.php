<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DesktopDevice extends Model {
 protected $guarded=[];protected $hidden=['token_hash'];
 protected function casts(): array{return ['revoked'=>'boolean','last_seen_at'=>'datetime'];}
 public function license(){return $this->belongsTo(DesktopLicense::class,'license_id');}
 public function user(){return $this->belongsTo(User::class);}
}
