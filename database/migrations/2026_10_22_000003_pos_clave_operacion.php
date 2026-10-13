<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('pos_ventas', function (Blueprint $table) {
            $table->uuid('clave_operacion')->nullable();
            $table->unique(['cyber_id', 'clave_operacion']);
        });
    }
    public function down(): void {
        Schema::table('pos_ventas', function (Blueprint $table) {
            $table->dropUnique(['cyber_id', 'clave_operacion']);
            $table->dropColumn('clave_operacion');
        });
    }
};
