<?php
namespace Tests\Feature;
use App\Models\{User,TikTokCuenta,TikTokLive,TikTokComentario,TikTokPublicacion,TikTokExportacion};
use App\Services\{TikTokConsulta,TikTokRegistro,TikTokPdf,TikTokProceso};
use App\Jobs\ExportarTikTok;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue,Storage};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TikTokMonitorTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void{parent::setUp();Storage::fake('local');Queue::fake([ExportarTikTok::class]);config(['tiktok.directorio'=>Storage::disk('local')->path('estado'),'tiktok.python'=>'/usr/bin/python3']);$this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);}
    private function usuario(string $rol='admin'): User{$s=Str::random(8);return User::create(['usuario'=>$s,'name'=>'Prueba '.$s,'email'=>$s.'@example.test','password'=>'Prueba123456','rol'=>$rol,'activo'=>true]);}
    private function cuenta(): TikTokCuenta{return TikTokCuenta::create(['usuario'=>'demo_'.Str::lower(Str::random(8)),'monitorear'=>true,'estado'=>'pendiente']);}
    private function evento(string $tipo,array $d=[]): array{return ['v'=>1,'tipo'=>$tipo,'capturado_ms'=>TikTokConsulta::ahora(),'sala'=>'7000000000000000001']+$d;}
    private function capturar(TikTokCuenta $c,int $n=2): TikTokLive{$eventos=[$this->evento('inicio',['titulo'=>'Live público de prueba','perfil'=>['nombre'=>'Cuenta de prueba','bio'=>'Descripción pública']])];for($i=1;$i<=$n;$i++)$eventos[]=$this->evento('comentario',['id'=>(string)$i,'autor'=>'persona','nombre'=>'Persona','texto'=>'Comentario '.$i,'ocurrido_ms'=>TikTokConsulta::ahora()]);app(TikTokRegistro::class)->registrar($c,$eventos);return $c->lives()->firstOrFail();}
    public function test_solo_admin_accede_a_todas_las_rutas_y_menu(): void {
        $c=$this->cuenta();$this->get(route('admin.tiktok.index'))->assertRedirect();
        foreach(['cliente','cyber']as$rol){$u=$this->usuario($rol);$u->rol==='cyber' && $u->update(['cyber_id'=>\App\Models\Cyber::create(['codigo'=>'TT'.Str::random(6),'nombre'=>'Negocio activo','activo'=>true])->id]);$this->actingAs($u);foreach(['index'=>[],'cuenta'=>[$c],'eventos'=>[$c]]as$r=>$p)$this->get(route('admin.tiktok.'.$r,$p))->assertForbidden();$this->post(route('admin.tiktok.captura',$c),['monitorear'=>1])->assertForbidden();$this->post(route('admin.tiktok.exportar',$c))->assertForbidden();$this->assertFalse(collect(\App\Support\Menu::para($u))->contains('id','tiktok'));}
        $this->actingAs($this->usuario())->get(route('admin.tiktok.index'))->assertOk()->assertSee('TikTok · Lives y comentarios');
    }
    public function test_registro_normaliza_usuario_no_activa_solo_y_rechaza_urls(): void {
        $this->actingAs($this->usuario())->post(route('admin.tiktok.guardar'),['usuario'=>' @Mi_Cuenta '])->assertRedirect();$c=TikTokCuenta::firstOrFail();$this->assertSame('mi_cuenta',$c->usuario);$this->assertFalse($c->monitorear);
        $this->post(route('admin.tiktok.guardar'),['usuario'=>'https://127.0.0.1/secreto'])->assertSessionHasErrors('usuario');$this->post(route('admin.tiktok.guardar'),['usuario'=>['x']])->assertSessionHasErrors('usuario');$this->assertSame(1,TikTokCuenta::count());
    }
    public function test_activar_y_pausar_conserva_comentarios_y_cierra_captura(): void {
        $c=$this->cuenta();$live=$this->capturar($c);$this->actingAs($this->usuario())->post(route('admin.tiktok.captura',$c),['monitorear'=>0])->assertRedirect();$this->assertFalse($c->fresh()->monitorear);$this->assertSame('interrumpido',$live->fresh()->estado);$this->assertSame(2,TikTokComentario::count());
        app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'sin_live'])]);$this->assertSame('inactivo',$c->fresh()->estado);
        $this->post(route('admin.tiktok.captura',$c),['monitorear'=>1])->assertRedirect();$this->assertTrue($c->fresh()->monitorear);
    }
    public function test_ingesta_deduplica_y_separa_salas_y_origen(): void {
        $c=$this->cuenta();$live=$this->capturar($c);$this->capturar($c);$this->assertSame(2,$live->fresh()->comentarios);$this->assertSame(2,TikTokComentario::count());
        app(TikTokRegistro::class)->registrar($c,[$this->evento('inicio')+[],array_replace($this->evento('inicio'),['sala'=>'7000000000000000002']),array_replace($this->evento('comentario',['id'=>'1','autor'=>'otra','texto'=>'Otra sala']),['sala'=>'7000000000000000002'])]);$this->assertSame(2,$c->lives()->count());$this->assertSame(3,TikTokComentario::count());$this->assertSame('interrumpido',$live->fresh()->estado);$this->assertSame('live_publico',TikTokComentario::first()->origen);
    }
    public function test_acceso_al_live_pendiente_reintenta_y_admite_perfil_publico_sin_inventar_una_captura(): void {
        $c=$this->cuenta();$r=app(TikTokRegistro::class);$r->registrar($c,[$this->evento('estado',['estado'=>'restringido','detalle'=>'Diagnóstico anterior','espera_segundos'=>60]),$this->evento('perfil',['perfil'=>['nombre'=>'Perfil público disponible']]),$this->evento('inicio',['perfil'=>['nombre'=>'NO CONECTADO']])]);
        $this->assertTrue($c->fresh()->monitorear);$this->assertSame('acceso_pendiente',$c->estado);$this->assertSame('Perfil público disponible',$c->perfil_publico['nombre']);$this->assertSame(0,TikTokLive::count());$this->assertGreaterThan(TikTokConsulta::ahora()+290000,$c->reintentar_ms);
        $this->actingAs($this->usuario())->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('etiqueta','Esperando acceso al LIVE')->assertJsonPath('monitorear',true);
        // Un nuevo intento confirmado por el capturador puede comenzar sin reactivar a mano.
        $r->registrar($c,[$this->evento('estado',['estado'=>'conectando']),$this->evento('inicio',['perfil'=>['nombre'=>'Perfil público disponible']]),$this->evento('comentario',['id'=>'nuevo','autor'=>'participante','texto'=>'Mensaje público recibido'])]);
        $this->assertTrue($c->fresh()->monitorear);$this->assertSame('en_live',$c->estado);$this->assertSame(1,TikTokComentario::count());
    }
    public function test_actualizacion_recupera_solo_pausas_automaticas_anteriores_y_respeta_esperas(): void {
        $vieja=$this->cuenta();$espera=TikTokConsulta::ahora()+600000;$vieja->update(['estado'=>'restringido','monitorear'=>false,'reintentar_ms'=>$espera]);
        $manual=$this->cuenta();$manual->update(['estado'=>'inactivo','monitorear'=>false]);$nueva=$this->cuenta();$nueva->update(['estado'=>'inactivo','monitorear'=>false]);
        $m=require database_path('migrations/2026_11_23_000005_reprogramar_tiktok_acceso.php');$m->up();$m->up();
        $this->assertTrue($vieja->fresh()->monitorear);$this->assertSame('acceso_pendiente',$vieja->fresh()->estado);$this->assertSame($espera,$vieja->fresh()->reintentar_ms);$this->assertFalse($manual->fresh()->monitorear);$this->assertFalse($nueva->fresh()->monitorear);
        $vieja->refresh()->update(['estado'=>'inactivo','monitorear'=>false]);$m->up();$this->assertFalse($vieja->fresh()->monitorear);
    }
    public function test_filtros_respetan_dia_en_costa_rica_e_id_de_otra_cuenta(): void {
        $c=$this->cuenta();$live=$this->capturar($c,0);$base=TikTokConsulta::ahora()-600000;$m=TikTokComentario::create(['cuenta_id'=>$c->id,'live_id'=>$live->id,'clave'=>hash('sha256','medianoche'),'autor'=>'uno','texto'=>'Día local','origen'=>'live_publico','ocurrido_ms'=>CarbonImmutable::parse('2026-11-23T05:59:59Z')->getTimestampMs(),'capturado_ms'=>$base]);
        TikTokComentario::create(['cuenta_id'=>$c->id,'live_id'=>$live->id,'clave'=>hash('sha256','otro-dia'),'autor'=>'dos','texto'=>'Día siguiente','origen'=>'live_publico','ocurrido_ms'=>CarbonImmutable::parse('2026-11-23T06:00:00Z')->getTimestampMs(),'capturado_ms'=>$base]);
        $this->actingAs($this->usuario())->getJson(route('admin.tiktok.eventos',[$c,'desde'=>'2026-11-22','hasta'=>'2026-11-22']))->assertOk()->assertJsonCount(1,'comentarios')->assertJsonPath('comentarios.0.texto','Día local')->assertJsonPath('comentarios.0.fecha','22/11/2026 11:59:59 PM');
        $otro=$this->cuenta();$this->get(route('admin.tiktok.cuenta',[$otro,'live_id'=>$live->id]))->assertNotFound();$this->getJson(route('admin.tiktok.eventos',[$c,'desde'=>'2026-11-23','hasta'=>'2026-11-22']))->assertUnprocessable();
    }
    public function test_eventos_cursor_no_repite_y_textos_se_escapan_en_la_vista(): void {
        $c=$this->cuenta();$live=$this->capturar($c);$id=TikTokComentario::max('id');app(TikTokRegistro::class)->registrar($c,[$this->evento('comentario',['id'=>'3','autor'=>'tercero','texto'=>'<script>alert(1)</script>'])]);$hoy=TikTokConsulta::fecha(TikTokConsulta::ahora(),'Y-m-d');
        $this->actingAs($this->usuario())->getJson(route('admin.tiktok.eventos',[$c,'cursor'=>$id,'desde'=>$hoy,'hasta'=>$hoy]))->assertOk()->assertJsonCount(1,'comentarios')->assertJsonPath('comentarios.0.autor','tercero');
        $this->get(route('admin.tiktok.cuenta',$c))->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)->assertHeader('Cache-Control','no-store, private');
    }
    public function test_publicaciones_solo_aceptan_urls_canonicas_de_esa_cuenta(): void {
        $c=$this->cuenta();$this->actingAs($this->usuario());foreach(['http://127.0.0.1/a','https://evil.test/@'.$c->usuario.'/video/123456','https://www.tiktok.com/@otra/video/123456']as$url)$this->post(route('admin.tiktok.publicacion',$c),['url'=>$url])->assertSessionHasErrors('url');
        $this->post(route('admin.tiktok.publicacion',$c),['url'=>$c->perfilUrl().'/video/123456789'])->assertRedirect();$this->assertSame('registro_manual',TikTokPublicacion::first()->origen);
    }
    public function test_importacion_json_es_atomica_deduplicada_y_de_origen_distinto(): void {
        $c=$this->cuenta();$p=TikTokPublicacion::create(['cuenta_id'=>$c->id,'video_id'=>'123456789']);$this->actingAs($this->usuario());$url=route('admin.tiktok.importar',[$c,$p]);$rows=[['id'=>'abc','autor'=>'autor','nombre'=>'Persona','texto'=>'Comentario de publicación','fecha'=>'2026-01-01T12:00:00Z']];
        for($i=0;$i<2;$i++)$this->post($url,['archivo'=>UploadedFile::fake()->createWithContent('datos.json',json_encode($rows)),'confirmo'=>1])->assertSessionHasNoErrors()->assertRedirect();$this->assertSame(1,TikTokComentario::count());$this->assertNull(TikTokComentario::first()->live_id);$this->assertSame('importacion_autorizada',TikTokComentario::first()->origen);
        $rows[0]['id']='nuevo';$rows[]=['id'=>'malo'];$this->post($url,['archivo'=>UploadedFile::fake()->createWithContent('datos.json',json_encode($rows)),'confirmo'=>1])->assertSessionHasErrors('archivo');$this->assertSame(1,TikTokComentario::count());
    }
    public function test_exportacion_congela_el_conjunto_y_es_privada(): void {
        $c=$this->cuenta();$this->capturar($c);$u=$this->usuario();$this->actingAs($u)->post(route('admin.tiktok.exportar',$c))->assertRedirect();Queue::assertPushed(ExportarTikTok::class,1);$e=TikTokExportacion::firstOrFail();$this->assertSame(2,$e->total);
        app(TikTokRegistro::class)->registrar($c,[$this->evento('comentario',['id'=>'3','autor'=>'nuevo','texto'=>'Después de solicitar exportación'])]);
        $pdf=$this->mock(TikTokPdf::class,fn($m)=>$m->shouldReceive('generar')->once()->withArgs(fn($d)=>$d['filas']->count()===2)->andReturn('%PDF-1.4 informe'));
        $job=new ExportarTikTok($e->id);$job->handle(app(TikTokConsulta::class),$pdf);$job->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);$this->assertSame(2,$e->fresh()->procesados);
        $this->get(route('admin.tiktok.descargar',$e))->assertOk();$this->actingAs($this->usuario())->get(route('admin.tiktok.descargar',$e))->assertNotFound();$this->actingAs($this->usuario('cliente'))->get(route('admin.tiktok.descargar',$e))->assertForbidden();
    }
    public function test_exportacion_grande_genera_zip_y_revocacion_impide_procesar(): void {
        $c=$this->cuenta();$live=$this->capturar($c,0);$base=[];for($i=0;$i<1001;$i++)$base[]=['cuenta_id'=>$c->id,'live_id'=>$live->id,'clave'=>hash('sha256','fila'.$i),'autor'=>'persona','texto'=>'Fila '.$i,'origen'=>'live_publico','ocurrido_ms'=>TikTokConsulta::ahora(),'capturado_ms'=>TikTokConsulta::ahora()];TikTokComentario::insert($base);
        $u=$this->usuario();$this->actingAs($u)->post(route('admin.tiktok.exportar',$c));$e=TikTokExportacion::firstOrFail();$pdf=$this->mock(TikTokPdf::class,fn($m)=>$m->shouldReceive('generar')->times(6)->andReturn('%PDF-1.4 informe'));
        (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$e->refresh();$this->assertSame(1001,$e->procesados);$this->assertStringEndsWith('.zip',$e->archivo);$z=new \ZipArchive();$this->assertTrue($z->open(Storage::disk('local')->path($e->archivo)));$this->assertSame(6,$z->numFiles);$z->close();
        $this->post(route('admin.tiktok.exportar',$c));$nuevo=TikTokExportacion::latest()->whereKeyNot($e->id)->firstOrFail();$u->update(['rol'=>'cliente']);(new ExportarTikTok($nuevo->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('error',$nuevo->fresh()->estado);
    }
    public function test_servicio_cli_consume_un_hijo_local_y_publica_salud_sin_api_abierta(): void {
        $c=$this->cuenta();$dir=Storage::disk('local')->path('estado');mkdir($dir,0700,true);$script=$dir.'/fake.py';$eventos=[$this->evento('inicio',['titulo'=>'Prueba local']),$this->evento('comentario',['id'=>'fake-1','autor'=>'prueba','texto'=>'Evento simulado'])];file_put_contents($script,'import json'."\n".'for line in '.json_encode(array_map(fn($e)=>json_encode($e),$eventos)).': print(line,flush=True)');
        $this->app->instance(TikTokProceso::class,new class($script)extends TikTokProceso{public function __construct(private string $s){}public function crear(string $usuario): Process{$p=new Process(['/usr/bin/python3',$this->s]);$p->setTimeout(null);return$p;}});
        $this->artisan('micv:tiktok-monitor',['--duracion'=>1])->assertSuccessful();$this->assertSame(1,TikTokComentario::count());$this->assertFalse(app(TikTokConsulta::class)->salud()['activo']);$this->assertSame('interrumpido',$c->lives()->first()->estado);
    }
    public function test_espera_y_fallos_temporales_conservan_monitoreo_y_el_panel_lo_explica(): void {
        $c=$this->cuenta();
        foreach(['sin_live','error','limitado']as$estado){
            app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>$estado,'detalle'=>'Reintento automático'])]);
            $this->assertTrue($c->fresh()->monitorear);$this->assertSame($estado,$c->estado);
        }
        $this->actingAs($this->usuario())->get(route('admin.tiktok.cuenta',$c))->assertOk()->assertSee('Monitoreo automático activado');
        $this->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('monitorear',true)->assertJsonPath('estado','limitado');
    }
    public function test_espera_del_proveedor_se_conserva_al_salir_del_proceso(): void {
        $c=$this->cuenta();$dir=Storage::disk('local')->path('estado');mkdir($dir,0700,true);$script=$dir.'/limited.py';
        file_put_contents($script,'print('.json_encode(json_encode($this->evento('estado',['estado'=>'limitado','espera_segundos'=>1800]))).',flush=True)');
        $this->app->instance(TikTokProceso::class,new class($script)extends TikTokProceso{public function __construct(private string $s){}public function crear(string $usuario): Process{$p=new Process(['/usr/bin/python3',$this->s]);$p->setTimeout(null);return$p;}});
        $this->artisan('micv:tiktok-monitor',['--duracion'=>1])->assertSuccessful();
        $c->refresh();$this->assertTrue($c->monitorear);$this->assertGreaterThan(TikTokConsulta::ahora()+1797000,$c->reintentar_ms);
        $c->update(['reintentar_ms'=>null]);app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'error','espera_segundos'=>999999999])]);
        $this->assertLessThanOrEqual(TikTokConsulta::ahora()+86400000,$c->fresh()->reintentar_ms);
    }
    public function test_sin_live_reintenta_y_captura_un_live_posterior_sin_reactivar(): void {
        config(['tiktok.intervalo'=>1]);$c=$this->cuenta();$dir=Storage::disk('local')->path('estado');mkdir($dir,0700,true);$script=$dir.'/offline_then_live.py';
        $primero=json_encode($this->evento('estado',['estado'=>'sin_live','detalle'=>'Esperando un LIVE público']));
        $inicio=json_encode($this->evento('inicio',['titulo'=>'LIVE posterior']));$comentario=json_encode($this->evento('comentario',['id'=>'posterior','autor'=>'prueba','texto'=>'Llegó después de estar offline']));
        file_put_contents($script,"from pathlib import Path\nimport time\np=Path(__file__).with_suffix('.count')\nn=int(p.read_text())+1 if p.exists() else 1\np.write_text(str(n))\nif n==1:\n print(".json_encode($primero).",flush=True)\nelse:\n print(".json_encode($inicio).",flush=True)\n print(".json_encode($comentario).",flush=True)\n time.sleep(10)\n");
        $this->app->instance(TikTokProceso::class,new class($script)extends TikTokProceso{public function __construct(private string $s){}public function crear(string $usuario): Process{$p=new Process(['/usr/bin/python3',$this->s]);$p->setTimeout(null);return$p;}});
        $this->artisan('micv:tiktok-monitor',['--duracion'=>5])->assertSuccessful();
        $this->assertSame('2',file_get_contents($dir.'/offline_then_live.count'));$this->assertTrue($c->fresh()->monitorear);
        $this->assertSame(1,TikTokComentario::count());$this->assertSame('Llegó después de estar offline',TikTokComentario::first()->texto);
    }

}
