<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosProveedor extends Model
{
    protected $table = 'pos_proveedores';

    protected $fillable = ['cyber_id', 'nombre', 'identificacion', 'contacto', 'telefono', 'correo', 'direccion', 'notas', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function productos(): HasMany { return $this->hasMany(PosProducto::class, 'proveedor_id'); }
    public function compras(): HasMany { return $this->hasMany(PosCompra::class, 'proveedor_id'); }
}
