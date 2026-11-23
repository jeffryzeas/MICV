<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  // La pausa manual usa estado=inactivo. Solo recuperar las antiguas pausas automáticas.
  // No cambiar reintentar_ms: conserva cualquier espera mayor indicada por el proveedor.
  DB::table('tiktok_cuentas')->where('estado','restringido')->update([
   'estado'=>'acceso_pendiente','monitorear'=>true,
   'detalle'=>'Monitoreo recuperado de una pausa automática anterior. El acceso al LIVE se volverá a comprobar; esto no indica que el perfil sea privado.',
  ]);
 }
 public function down(): void {
  // Una reversión no debe desactivar cuentas ni reconstruir pausas históricas.
 }
};
