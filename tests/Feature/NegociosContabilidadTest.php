<?php
namespace Tests\Feature;
use App\Models\{Cyber, User, ContableMovimiento};
use App\Models\Pos\{PosConfig,PosCaja,PosProducto};
use App\Services\{LibroIngresosGastos};
use App\Services\Pos\Ventas;
use App\Support\{Negocios,Exportacion};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class NegociosContabilidadTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);}
 private function negocio(string $pais='NI',array $modulos=['pos','contabilidad','clientes']): Cyber {return Cyber::create(['codigo'=>Str::upper(Str::random(8)),'nombre'=>'Negocio de prueba','pais'=>$pais,'modulos'=>$modulos,'contabilidad_pos'=>true,'activo'=>true]);}
 private function usuario(Cyber $cy,array $permisos=['contabilidad.ver','contabilidad.registrar','contabilidad.anular','contabilidad.exportar']): User {$s=Str::random(8);return User::create(['usuario'=>$s,'email'=>$s.'@example.test','name'=>'Prueba','password'=>'Prueba12345678','rol'=>'cyber','cyber_id'=>$cy->id,'activo'=>true,'permisos'=>$permisos]);}
 private function manual(array $extra=[]): array {return $extra+['clave_operacion'=>(string)Str::uuid(),'tipo'=>'ingreso','categoria'=>'Servicios','concepto'=>'Ingreso de prueba','monto'=>'100.50','fecha'=>now('America/Managua')->toDateString()];}
 public function test_pais_del_negocio_define_moneda_sin_depender_de_clientes(): void {$cr=$this->negocio('CR');$co=$this->negocio('CO');$this->assertSame('CRC',PosConfig::para($cr)->moneda);$this->assertSame('₡',PosConfig::para($cr)->simbolo);$this->assertSame('COP',PosConfig::para($co)->moneda);}
 public function test_movimiento_es_idempotente_y_no_se_requiere_pos(): void {$cy=$this->negocio('NI',['contabilidad']);$u=$this->usuario($cy);$datos=$this->manual();$this->actingAs($u)->post(route('contabilidad.store'),$datos)->assertRedirect();$this->post(route('contabilidad.store'),$datos)->assertRedirect();$this->assertDatabaseCount('contable_movimientos',1);$this->assertDatabaseCount('pos_cajas',0);$this->get(route('contabilidad.index'))->assertOk()->assertSee('Ingreso de prueba');$this->get(route('pos.vender'))->assertForbidden();}
 public function test_libro_no_expone_ni_anula_registros_de_otro_negocio(): void {$a=$this->negocio();$b=$this->negocio();$u=$this->usuario($a);$otro=$this->usuario($b);$m=LibroIngresosGastos::manual($b,$otro,$this->manual(['concepto'=>'Secreto de otro negocio']));$this->actingAs($u)->get(route('contabilidad.index',['negocio'=>$b->id]))->assertOk()->assertDontSee('Secreto de otro negocio');$this->post(route('contabilidad.anular',$m),['motivo'=>'No autorizado'])->assertNotFound();$this->assertSame('activo',$m->fresh()->estado);}
 public function test_anulacion_manual_conserva_monto_y_cambia_saldo(): void {$cy=$this->negocio();$u=$this->usuario($cy);$m=LibroIngresosGastos::manual($cy,$u,$this->manual());$this->actingAs($u)->post(route('contabilidad.anular',$m),['motivo'=>'Duplicado registrado por error'])->assertRedirect();$this->assertSame('100.50',$m->fresh()->monto);$this->assertSame('anulado',$m->fresh()->estado);$this->get(route('contabilidad.index'))->assertOk()->assertViewHas('saldo',fn($s)=>(float)$s===0.0);}
 public function test_pos_se_vincula_una_vez_y_anulacion_se_refleja_aun_deshabilitado(): void {$cy=$this->negocio();$u=$this->usuario($cy,['pos.vender','pos.anular','pos.precios']);$cfg=PosConfig::para($cy);$cfg->update(['impuesto_tasa'=>0,'precios_con_impuesto'=>true]);$caja=PosCaja::create(['cyber_id'=>$cy->id,'user_id'=>$u->id,'abierta_en'=>now(),'monto_inicial'=>0]);$p=PosProducto::create(['cyber_id'=>$cy->id,'nombre'=>'Producto','precio'=>10,'costo'=>3,'stock'=>5,'controla_stock'=>true,'activo'=>true]);$d=['clave_operacion'=>(string)Str::uuid(),'items'=>[['producto_id'=>$p->id,'cantidad'=>1,'precio'=>10]],'pagos'=>[['metodo'=>'efectivo','monto'=>20]]];$v=Ventas::registrar($d,$cfg,$caja,$u);Ventas::registrar($d,$cfg,$caja,$u);$this->assertDatabaseCount('contable_movimientos',1);$this->assertSame('10.00',ContableMovimiento::first()->monto);$cy->update(['contabilidad_pos'=>false]);Ventas::anular($v,$u,'Cancelación de prueba');$this->assertSame('anulado',ContableMovimiento::first()->estado);}
 public function test_permiso_de_registro_no_concede_exportacion_ni_anulacion(): void {$cy=$this->negocio();$u=$this->usuario($cy,['contabilidad.registrar']);$this->assertTrue($u->tienePermiso('contabilidad.ver'));$this->actingAs($u)->get(route('contabilidad.exportar',['formato'=>'pdf']))->assertForbidden();}
 public function test_catalogo_de_documentos_es_exclusivo_admin(): void {$cy=$this->negocio();$u=$this->usuario($cy,['clientes.ver','correos']);$this->actingAs($u)->get(route('municipios.index'))->assertForbidden();}
 public function test_catalogo_cv_tiene_31_y_empieza_por_sencillos(): void {$this->assertCount(31,\App\Services\CvDatos::DISENOS);$this->assertSame('clasico',array_key_first(\App\Services\CvDatos::DISENOS));foreach(array_keys(\App\Services\CvDatos::DISENOS) as $key)$this->assertTrue(view()->exists('cv.'.$key));}

 public function test_crear_negocio_solicita_pais_tipo_y_configura_su_moneda(): void {
  $cy=$this->negocio();$admin=$this->usuario($cy);$admin->update(['rol'=>'admin']);
  $this->actingAs($admin)->post(route('admin.cybers.store'),['codigo'=>'TIENDA-CO','nombre'=>'Tienda de ejemplo','tipo_negocio'=>'tienda','naturaleza'=>'juridica','pais'=>'CO','perfil_uso'=>'pos_contabilidad','color_primario'=>'#0B3C74','color_secundario'=>'#C9971F','activo'=>'1','contabilidad_pos'=>'1'])->assertRedirect(route('admin.cybers.index'));
  $nuevo=Cyber::where('codigo','TIENDA-CO')->firstOrFail();$this->assertSame('juridica',$nuevo->naturaleza);$this->assertTrue($nuevo->contabilidad_pos);$this->assertSame('COP',PosConfig::para($nuevo)->moneda);$this->assertFalse($nuevo->moduloHabilitado('cv'));
 }
 public function test_pais_y_moneda_no_se_reetiquetan_sobre_historial(): void {
  $cy=$this->negocio();$admin=$this->usuario($cy);$admin->update(['rol'=>'admin']);LibroIngresosGastos::manual($cy,$admin,$this->manual());
  $this->actingAs($admin)->put(route('admin.cybers.update',$cy),['codigo'=>$cy->codigo,'nombre'=>$cy->nombre,'tipo_negocio'=>'comercio','naturaleza'=>'natural','pais'=>'CR','perfil_uso'=>'pos_contabilidad','color_primario'=>'#0B3C74','color_secundario'=>'#C9971F','activo'=>'1'])->assertSessionHasErrors('pais');$this->assertSame('NI',$cy->fresh()->pais);$this->assertSame('NIO',ContableMovimiento::first()->moneda);
 }
}
