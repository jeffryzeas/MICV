<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::create("correo_servicios", function (Blueprint $t) {
            $t->id();
            $t->foreignId("cyber_id")->constrained("cybers")->cascadeOnDelete();
            $t->foreignId("cliente_id")
                ->constrained("clientes")
                ->cascadeOnDelete();
            $t->foreignId("creado_por")
                ->nullable()
                ->constrained("users")
                ->nullOnDelete();
            $t->string("proveedor", 30);
            $t->string("correo", 150);
            $t->string("celular", 40);
            $t->text("clave_correo");
            $t->string("pais", 2);
            $t->timestamps();
            $t->unique(["cyber_id", "correo"]);
        });
        Schema::table("jz_mensajes", function (Blueprint $t) {
            $t->timestamp("editado_en")->nullable();
            $t->timestamp("eliminado_en")->nullable();
        });
        Schema::create("jz_mensajes_ocultos", function (Blueprint $t) {
            $t->foreignId("mensaje_id")
                ->constrained("jz_mensajes")
                ->cascadeOnDelete();
            $t->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $t->unique(["mensaje_id", "user_id"]);
        });
        DB::table("users")
            ->where("rol", "cliente")
            ->whereNotNull("chat_foto")
            ->orderBy("id")
            ->each(function ($u) {
                $c = DB::table("clientes")->where("user_id", $u->id)->first();
                if ($c && !$c->foto) {
                    DB::table("clientes")
                        ->where("id", $c->id)
                        ->update(["foto" => $u->chat_foto]);
                    DB::table("users")
                        ->where("id", $u->id)
                        ->update(["chat_foto" => null]);
                }
            });
        if (Schema::hasTable("jz_passkeys")) {
            DB::table("jz_passkeys")->delete();
        }
    }
    public function down(): void
    {
        Schema::dropIfExists("jz_mensajes_ocultos");
        Schema::dropIfExists("correo_servicios");
        Schema::table(
            "jz_mensajes",
            fn(Blueprint $t) => $t->dropColumn(["editado_en", "eliminado_en"]),
        );
    }
};
