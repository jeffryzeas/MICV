<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JzChat extends Model
{
    protected $table = "jz_chats";

    protected $guarded = [];

    public function mensajes()
    {
        return $this->hasMany(JzMensaje::class, "chat_id");
    }

    public function uno()
    {
        return $this->belongsTo(User::class, "uno_id");
    }

    public function dos()
    {
        return $this->belongsTo(User::class, "dos_id");
    }

    public function miembro(User $u): bool
    {
        return in_array(
            (int) $u->id,
            [(int) $this->uno_id, (int) $this->dos_id],
            true,
        );
    }

    public function otro(User $u): User
    {
        return (int) $this->uno_id === (int) $u->id ? $this->dos : $this->uno;
    }

    public function lectura(User $u): string
    {
        return (int) $this->uno_id === (int) $u->id ? "uno_leido" : "dos_leido";
    }
}
