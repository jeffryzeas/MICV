<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gmail_cuentas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('correo', 150);
            $t->string('nombre', 150);
            $t->text('clave')->nullable();
            $t->uuid('revision');
            $t->boolean('activo')->default(true);
            $t->timestamp('verificada_en')->nullable();
            $t->timestamps();
        });
        Schema::create('postulacion_empresas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('nombre', 150);
            $t->string('correo', 150);
            $t->string('contacto', 150)->nullable();
            $t->string('cargo', 150)->nullable();
            $t->text('notas')->nullable();
            $t->boolean('compartida')->default(false);
            $t->boolean('activa')->default(true);
            $t->timestamps();
        });
        Schema::create('postulaciones', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // Dueño de la cuenta remitente.
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('empresa_id')->nullable()->constrained('postulacion_empresas')->nullOnDelete();
            $t->foreignId('gmail_cuenta_id')->nullable()->constrained('gmail_cuentas')->nullOnDelete();
            $t->uuid('cuenta_revision');
            $t->string('remitente', 150);
            $t->string('remitente_nombre', 150);
            $t->string('candidato', 200);
            $t->string('empresa', 150);
            $t->string('destinatario', 150);
            $t->string('contacto', 150)->nullable();
            $t->string('cargo', 150)->nullable();
            $t->string('asunto', 200);
            $t->text('mensaje');
            $t->longText('correo_html');
            $t->string('cv_ruta');
            $t->string('cv_nombre', 180);
            $t->string('cv_sha256', 64);
            $t->unsignedInteger('cv_bytes');
            $t->json('cv_opciones');
            $t->string('estado', 20)->default('borrador');
            $t->string('detalle_estado', 500)->nullable();
            $t->string('message_id')->nullable();
            $t->timestamp('encolado_en')->nullable();
            $t->timestamp('iniciado_en')->nullable();
            $t->timestamp('enviado_en')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['estado', 'iniciado_en']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
        Schema::dropIfExists('postulacion_empresas');
        Schema::dropIfExists('gmail_cuentas');
    }
};
