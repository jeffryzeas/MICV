<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tiktok_cuentas', function(Blueprint $t){
            $t->id();$t->string('usuario',24)->unique();$t->string('nombre',150)->nullable();$t->text('notas')->nullable();
            $t->boolean('monitorear')->default(false);$t->string('estado',20)->default('inactivo');$t->string('detalle',300)->nullable();
            $t->json('perfil_publico')->nullable();$t->unsignedBigInteger('latido_ms')->nullable();$t->unsignedBigInteger('reintentar_ms')->nullable();$t->timestamps();
        });
        Schema::create('tiktok_lives',function(Blueprint $t){
            $t->id();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->string('sala',40);$t->string('titulo',255)->nullable();
            $t->string('estado',20)->default('en_live');$t->unsignedBigInteger('iniciado_ms');$t->unsignedBigInteger('ultimo_ms');$t->unsignedBigInteger('terminado_ms')->nullable();
            $t->unsignedInteger('comentarios')->default(0);$t->unsignedInteger('max_espectadores')->nullable();$t->timestamps();$t->unique(['cuenta_id','sala']);
        });
        Schema::create('tiktok_publicaciones',function(Blueprint $t){
            $t->id();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->string('video_id',30);$t->string('titulo',255)->nullable();$t->string('origen',30)->default('registro_manual');$t->timestamps();$t->unique(['cuenta_id','video_id']);
        });
        Schema::create('tiktok_comentarios',function(Blueprint $t){
            $t->id();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();$t->foreignId('live_id')->nullable()->constrained('tiktok_lives')->cascadeOnDelete();
            $t->foreignId('publicacion_id')->nullable()->constrained('tiktok_publicaciones')->cascadeOnDelete();$t->string('clave',64)->unique();$t->string('autor',100);$t->string('nombre',150)->nullable();$t->text('texto');
            $t->string('origen',30)->default('live_publico');$t->unsignedBigInteger('ocurrido_ms');$t->unsignedBigInteger('capturado_ms');$t->index(['cuenta_id','ocurrido_ms']);$t->index(['live_id','id']);$t->index(['publicacion_id','id']);
        });
        Schema::create('tiktok_exportaciones',function(Blueprint $t){
            $t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('cuenta_id')->constrained('tiktok_cuentas')->cascadeOnDelete();
            $t->json('filtros');$t->unsignedBigInteger('hasta_id');$t->string('estado',20)->default('en_cola');$t->unsignedInteger('total');$t->unsignedInteger('procesados')->default(0);$t->string('archivo')->nullable();$t->string('error',300)->nullable();$t->timestamps();
        });
    }
    public function down(): void {foreach(['tiktok_exportaciones','tiktok_comentarios','tiktok_publicaciones','tiktok_lives','tiktok_cuentas']as$t)Schema::dropIfExists($t);}
};
