<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContableMovimiento extends Model {
 protected $table='contable_movimientos';
 protected $guarded=[];
 protected function casts(): array {return ['fecha'=>'date','monto'=>'decimal:2','anulada_en'=>'datetime'];}
 public function usuario(){return $this->belongsTo(User::class,'user_id');}
 public function venta(){return $this->belongsTo(\App\Models\Pos\PosVenta::class,'venta_id');}
}
