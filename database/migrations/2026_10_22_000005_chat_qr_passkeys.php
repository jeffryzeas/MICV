<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table("cybers", function (Blueprint $t) {
            $t->timestamp("autoservicio_hasta")->nullable();
        });
        // Un QR anterior debe ser abierto expresamente con una duración.
        DB::table("cybers")->update(["autoservicio" => false]);
        Schema::create("jz_chats", function (Blueprint $t) {
            $t->id();
            $t->foreignId("uno_id")->constrained("users")->cascadeOnDelete();
            $t->foreignId("dos_id")->constrained("users")->cascadeOnDelete();
            $t->unsignedBigInteger("uno_leido")->default(0);
            $t->unsignedBigInteger("dos_leido")->default(0);
            $t->timestamps();
            $t->unique(["uno_id", "dos_id"]);
        });
        Schema::create("jz_mensajes", function (Blueprint $t) {
            $t->id();
            $t->foreignId("chat_id")
                ->constrained("jz_chats")
                ->cascadeOnDelete();
            $t->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $t->uuid("clave");
            $t->text("texto")->nullable();
            $t->string("archivo")->nullable();
            $t->string("miniatura")->nullable();
            $t->string("nombre")->nullable();
            $t->string("mime", 120)->nullable();
            $t->unsignedInteger("bytes")->default(0);
            $t->boolean("sistema")->default(false);
            $t->timestamps();
            $t->unique(["user_id", "clave"]);
            $t->index(["chat_id", "id"]);
        });
        Schema::create("jz_avisos", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $t->uuid("clave");
            $t->unsignedInteger("destinatarios");
            $t->timestamps();
            $t->unique(["user_id", "clave"]);
        });
        Schema::create("jz_invitaciones", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $t->string("token", 64)->unique();
            $t->timestamp("vence");
            $t->timestamps();
        });
        Schema::create("jz_passkeys", function (Blueprint $t) {
            $t->id();
            $t->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $t->string("cred_hash", 64)->unique();
            $t->text("cred_id");
            $t->text("public_key");
            $t->unsignedBigInteger("contador")->default(0);
            $t->string("nombre", 80);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (
            [
                "jz_avisos",
                "jz_passkeys",
                "jz_invitaciones",
                "jz_mensajes",
                "jz_chats",
            ]
            as $n
        ) {
            Schema::dropIfExists($n);
        }
        Schema::table(
            "cybers",
            fn(Blueprint $t) => $t->dropColumn("autoservicio_hasta"),
        );
    }
};
