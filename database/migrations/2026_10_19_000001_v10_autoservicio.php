<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v10: autoservicio por QR (el cliente llena sus datos desde su celular en el mostrador). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cybers', function (Blueprint $t) {
            $t->boolean('autoservicio')->default(true)->after('activo');
        });
        Schema::create('autoservicio_solicitudes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->date('fecha');
            $t->unsignedInteger('turno');
            $t->string('nombre', 160);
            $t->string('numero_documento', 40)->nullable();
            $t->json('datos');
            $t->string('estado', 15)->default('pendiente'); // pendiente | atendida | descartada
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('atendida_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('atendida_en')->nullable();
            $t->timestamps();
            $t->index(['cyber_id', 'estado']);
            $t->index(['cyber_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoservicio_solicitudes');
        Schema::table('cybers', fn (Blueprint $t) => $t->dropColumn('autoservicio'));
    }
};
