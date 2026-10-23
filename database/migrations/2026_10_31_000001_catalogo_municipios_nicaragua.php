<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
use App\Support\MunicipiosNicaragua;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("municipios_nicaragua", function (Blueprint $t) {
            $t->char("codigo", 3)->primary();
            $t->string("municipio", 120);
            $t->string("departamento", 100);
            $t->string("alias", 200)->nullable();
            $t->boolean("especial")->default(false);
            $t->boolean("activo")->default(true);
            $t->string("fuente", 500);
            $t->string("notas", 1000)->nullable();
            $t->unsignedInteger("version")->default(1);
            $t->foreignId("modificado_por")
                ->nullable()
                ->constrained("users")
                ->nullOnDelete();
            $t->timestamps();
        });
        $hoy = now();
        DB::table("municipios_nicaragua")->insert(
            array_map(
                fn($r) => $r + ["created_at" => $hoy, "updated_at" => $hoy],
                MunicipiosNicaragua::base(),
            ),
        );
    }
    public function down(): void
    {
        Schema::dropIfExists("municipios_nicaragua");
    }
};
