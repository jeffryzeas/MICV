<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCategoria extends Model
{
    protected $table = 'pos_categorias';

    protected $fillable = ['cyber_id', 'nombre', 'color', 'orden'];

    public function productos(): HasMany { return $this->hasMany(PosProducto::class, 'categoria_id'); }
}
