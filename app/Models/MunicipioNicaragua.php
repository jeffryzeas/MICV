<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MunicipioNicaragua extends Model
{
    protected $table = "municipios_nicaragua";
    protected $primaryKey = "codigo";
    public $incrementing = false;
    protected $keyType = "string";
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            "especial" => "boolean",
            "activo" => "boolean",
            "version" => "integer",
        ];
    }
}
