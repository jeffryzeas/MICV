<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_negocio', 120)->default('Mi CV');
            $table->string('logo')->nullable();             // logo del negocio (panel)
            $table->string('escudo')->nullable();           // imagen de las esquinas del CV clásico
            $table->string('color_primario', 7)->default('#0B3C74');
            $table->string('color_secundario', 7)->default('#C9A227');
            $table->json('cv_config')->nullable();
            $table->timestamps();
        });

        Schema::create('actividad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 60);
            $table->string('detalle', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad');
        Schema::dropIfExists('configuracion');
    }
};
