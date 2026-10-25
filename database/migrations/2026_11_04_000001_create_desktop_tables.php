<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('desktop_licenses',function(Blueprint $t){$t->id();$t->foreignId('cyber_id')->constrained('cybers')->restrictOnDelete();$t->string('nombre',100);$t->string('key_hash',64)->unique();$t->boolean('activa')->default(true);$t->timestamp('expires_at');$t->unsignedSmallInteger('device_limit')->default(1);$t->unsignedTinyInteger('offline_days')->default(7);$t->json('modulos');$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();});
  Schema::create('desktop_devices',function(Blueprint $t){$t->id();$t->foreignId('license_id')->constrained('desktop_licenses')->restrictOnDelete();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->uuid('uuid')->unique();$t->string('nombre',100);$t->string('token_hash',64)->unique();$t->boolean('revoked')->default(false);$t->timestamp('last_seen_at')->nullable();$t->timestamps();});
  Schema::create('desktop_receipts',function(Blueprint $t){$t->id();$t->foreignId('device_id')->constrained('desktop_devices')->restrictOnDelete();$t->uuid('operation_uuid');$t->string('payload_hash',64);$t->string('tipo',40);$t->unsignedBigInteger('entity_id');$t->timestamp('occurred_at');$t->timestamps();$t->unique(['device_id','operation_uuid']);});
 }
 public function down(): void {Schema::dropIfExists('desktop_receipts');Schema::dropIfExists('desktop_devices');Schema::dropIfExists('desktop_licenses');}
};
