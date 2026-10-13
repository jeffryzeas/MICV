<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JzMensaje extends Model
{
    protected $table = "jz_mensajes";

    protected $guarded = [];

    protected function casts(): array
    {
        return ["texto" => "encrypted", "sistema" => "boolean"];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function chat()
    {
        return $this->belongsTo(JzChat::class, "chat_id");
    }
}
