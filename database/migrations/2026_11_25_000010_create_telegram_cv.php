<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_cv_cuentas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->unsignedInteger('api_id')->nullable();
            $t->text('api_hash')->nullable();
            $t->string('telefono', 20)->nullable();
            $t->text('sesion')->nullable();
            $t->text('codigo_hash')->nullable();
            $t->string('estado', 24)->default('desconectada');
            $t->string('telegram_id', 24)->nullable();
            $t->string('nombre', 200)->nullable();
            $t->string('usuario', 64)->nullable();
            $t->uuid('revision');
            $t->timestamp('codigo_en')->nullable();
            $t->timestamp('conectada_en')->nullable();
            $t->timestamp('no_antes_de')->nullable();
            $t->string('ultimo_error', 300)->nullable();
            $t->timestamps();
        });
        Schema::create('telegram_cv_envios', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('telegram_cuenta_id')->constrained('telegram_cv_cuentas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->uuid('cuenta_revision');
            $t->string('remitente_id', 24);
            $t->string('remitente', 200);
            $t->string('telefono', 20);
            $t->string('destinatario', 200);
            $t->string('destinatario_id', 24);
            $t->text('peer');
            $t->string('tipo', 16);
            $t->text('mensaje');
            $t->string('cv_ruta')->nullable();
            $t->string('cv_nombre')->nullable();
            $t->string('candidato', 200)->nullable();
            $t->char('cv_sha256', 64)->nullable();
            $t->unsignedInteger('cv_bytes')->nullable();
            $t->json('cv_opciones')->nullable();
            $t->string('random_id', 24)->unique();
            $t->string('estado', 24)->default('borrador')->index();
            $t->string('detalle', 300)->nullable();
            $t->string('mensaje_id', 24)->nullable();
            $t->timestamp('encolado_en')->nullable();
            $t->timestamp('iniciado_en')->nullable();
            $t->timestamp('enviado_en')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_cv_envios');
        Schema::dropIfExists('telegram_cv_cuentas');
    }
};
