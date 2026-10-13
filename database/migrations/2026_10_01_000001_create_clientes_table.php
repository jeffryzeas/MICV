<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 30)->default('cedula'); // cedula | pasaporte | residencia | partida_nacimiento
            $table->string('numero_documento', 40);
            $table->string('inss_nss', 40)->nullable();

            $table->string('primer_nombre', 60);
            $table->string('segundo_nombre', 60)->nullable();
            $table->string('primer_apellido', 60);
            $table->string('segundo_apellido', 60)->nullable();

            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedTinyInteger('edad_calculada')->nullable();
            $table->string('municipio_nacimiento', 100)->nullable();
            $table->string('departamento_nacimiento', 100)->nullable();
            $table->string('sexo', 1)->nullable();          // M | F | O
            $table->string('nacionalidad', 60)->nullable();
            $table->unsignedTinyInteger('numero_hijos')->nullable();
            $table->string('estado_civil', 40)->nullable();
            $table->string('profesion', 120)->nullable();
            $table->text('perfil_profesional')->nullable();  // resumen (se usa en el diseño Pro)

            $table->string('correo', 150)->nullable();
            $table->string('telefono_codigo_pais', 8)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('telefono_red', 30)->nullable();
            $table->string('telefono_secundario', 40)->nullable();
            $table->string('telefono_secundario_red', 30)->nullable();
            $table->string('direccion')->nullable();

            $table->string('nombre_padre', 150)->nullable();
            $table->string('nombre_madre', 150)->nullable();

            $table->text('notas')->nullable();
            $table->string('foto')->nullable();             // ruta dentro del disco "local"
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo_documento', 'numero_documento'], 'uq_cliente_documento');
            $table->index(['primer_apellido', 'primer_nombre']);
        });

        // Un cliente puede estar en varios cyber (base general compartida, igual que el Sistema JZ).
        Schema::create('cliente_cyber', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cliente_id', 'cyber_id']);
        });

        Schema::create('cliente_estudios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nivel', 20);                    // universidad | tecnico | secundaria | primaria | curso
            $table->string('titulo', 200)->nullable();
            $table->string('institucion', 200)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('detalle', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('cliente_experiencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('cargo', 150)->nullable();
            $table->string('empresa', 200)->nullable();
            $table->string('descripcion', 1000)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();          // null = actualidad
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('cliente_habilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('habilidad', 150);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('cliente_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('tratamiento', 20)->nullable();
            $table->string('nombre', 150);
            $table->string('telefono', 40)->nullable();
            $table->string('correo', 150)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_referencias');
        Schema::dropIfExists('cliente_habilidades');
        Schema::dropIfExists('cliente_experiencias');
        Schema::dropIfExists('cliente_estudios');
        Schema::dropIfExists('cliente_cyber');
        Schema::dropIfExists('clientes');
    }
};
