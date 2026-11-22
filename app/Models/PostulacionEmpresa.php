<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostulacionEmpresa extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['compartida'=>'boolean', 'activa'=>'boolean']; }
    public function scopeVisibles(Builder $q, User $u): Builder
    {
        return $u->esAdmin() ? $q : $q->where(fn($w) => $w->where('user_id', $u->id)->orWhere('compartida', true));
    }
    public function puedeEditar(User $u): bool { return $u->esAdmin() || (int)$this->user_id === (int)$u->id; }
    public function user() { return $this->belongsTo(User::class); }
}
