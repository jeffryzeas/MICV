<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('encargado_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->json('permisos')->nullable();
        });
        Schema::table('pos_productos', function (Blueprint $t) { $t->string('foto')->nullable(); });
    }
    public function down(): void {
        Schema::table('pos_productos', fn (Blueprint $t) => $t->dropColumn('foto'));
        Schema::table('users', function (Blueprint $t) { $t->dropConstrainedForeignId('encargado_id'); $t->dropColumn('permisos'); });
    }
};
