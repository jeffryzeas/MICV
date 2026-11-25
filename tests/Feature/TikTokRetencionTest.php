<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{TikTokCuenta,TikTokExportacion,User,TikTokComentario};
use App\Services\{TikTokRetencion,TikTokInforme,TikTokConsulta,TikTokPdf};
use App\Jobs\ExportarTikTok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue};
use Illuminate\Support\Str;
class TikTokRetencionTest extends TestCase {
 use RefreshDatabase;
 private User $admin;private TikTokCuenta $cuenta;
 protected function setUp(): void {parent::setUp();Storage::fake('local');Queue::fake();$this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);$this->admin=User::create(['usuario'=>'retencion','name'=>'Administrador','email'=>'retencion@example.test','rol'=>'admin','activo'=>true,'password'=>'Prueba123456']);$this->actingAs($this->admin);$this->cuenta=TikTokCuenta::create(['usuario'=>'captura_publica']);$this->travelTo(now()->startOfMinute());}
 private function informe(string $estado='listo'): TikTokExportacion {$e=new TikTokExportacion(['cuenta_id'=>$this->cuenta->id,'user_id'=>$this->admin->id,'filtros'=>['desde'=>now()->format('Y-m-d'),'hasta'=>now()->format('Y-m-d')],'hasta_id'=>1,'estado'=>$estado,'total'=>1,'procesados'=>1,'expira_en'=>$estado==='listo'?now()->addMinutes(5):null]);$e->id=(string)Str::uuid();$e->archivo='tiktok/exportaciones/'.$e->id.'/informe.pdf';$e->paquete='tiktok/exportaciones/'.$e->id.'/paquete.zip';$e->save();Storage::disk('local')->put($e->archivo,'%PDF-1.4 ejemplo');Storage::disk('local')->put($e->paquete,'ZIP de ejemplo');Storage::disk('local')->put('tiktok/exportaciones/'.$e->id.'/datos.ndjson','copia temporal');return $e;}
 public function test_expira_a_los_cinco_minutos_y_conserva_capturas_fotos_y_otros_documentos(): void {
  $e=$this->informe();$otra=$this->informe();$otra->update(['expira_en'=>now()->addHour()]);$m=TikTokComentario::create(['cuenta_id'=>$this->cuenta->id,'clave'=>hash('sha256','captura'),'autor'=>'persona','texto'=>'Dato que se conserva','origen'=>'live_publico','ocurrido_ms'=>TikTokConsulta::ahora(),'capturado_ms'=>TikTokConsulta::ahora()]);Storage::disk('local')->put('tiktok/avatares/original.png','foto original');Storage::disk('local')->put('postulaciones/cv.pdf','CV que no debe borrarse');
  $this->travel(299)->seconds();$this->get(route('admin.tiktok.descargar',$e))->assertOk();$this->assertSame(0,app(TikTokRetencion::class)->limpiar());
  $this->travel(1)->seconds();$this->get(route('admin.tiktok.descargar',$e))->assertStatus(410);$this->get(route('admin.tiktok.paquete',$e))->assertStatus(410);$this->assertSame('caducado',$e->fresh()->estado);$this->assertNotNull($e->fresh()->limpiado_en);$this->assertNull($e->fresh()->archivo);
  Storage::disk('local')->assertMissing('tiktok/exportaciones/'.$e->id);Storage::disk('local')->assertExists($otra->archivo);Storage::disk('local')->assertExists('tiktok/avatares/original.png');Storage::disk('local')->assertExists('postulaciones/cv.pdf');$this->assertSame('Dato que se conserva',$m->fresh()->texto);$this->assertSame(1,TikTokCuenta::count());
  $this->getJson(route('admin.tiktok.exportacion',$e))->assertOk()->assertJsonPath('estado','caducado')->assertJsonPath('descarga',null)->assertJsonPath('paquete',null);$this->assertSame(0,app(TikTokRetencion::class)->limpiar());
 }
 public function test_reloj_comienza_al_terminar_la_generacion_y_no_al_encolar(): void {
  $e=$this->informe('en_cola');$inicio=now()->copy();$this->mock(TikTokInforme::class,fn($m)=>$m->shouldReceive('crear')->once()->andReturnUsing(function()use($e){$this->travel(8)->minutes();return ['archivo'=>$e->archivo,'paquete'=>$e->paquete,'total'=>1,'procesados'=>1];}));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),app(TikTokPdf::class));$this->assertSame('listo',$e->fresh()->estado);$this->assertTrue($e->fresh()->expira_en->equalTo($inicio->addMinutes(13)));Storage::disk('local')->assertExists($e->archivo);
 }
 public function test_limpieza_no_borra_informes_que_se_estan_generando(): void {$e=$this->informe('procesando');$this->travel(20)->minutes();$this->artisan('micv:tiktok-limpiar-informes')->assertSuccessful();Storage::disk('local')->assertExists($e->archivo);$this->assertSame('procesando',$e->fresh()->estado);}
 public function test_otros_administradores_no_pueden_descargar_ni_caducar_un_informe_ajeno(): void {$e=$this->informe();$otro=User::create(['usuario'=>'otro_retencion','name'=>'Otro','email'=>'otro@example.test','rol'=>'admin','activo'=>true,'password'=>'Prueba123456']);$this->travel(6)->minutes();$this->actingAs($otro)->get(route('admin.tiktok.descargar',$e))->assertNotFound();Storage::disk('local')->assertExists($e->archivo);}
}
