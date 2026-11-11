<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_pendientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('pos_ventas')->cascadeOnDelete();
            $table->string('tipo', 12);
            $table->longText('contenido');
            $table->timestamps();
            $table->index(['cyber_id', 'user_id', 'venta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_pendientes');
    }
};
