<?php
namespace Tests\Feature;
use App\Models\{User,TikTokCuenta,TikTokFuente,TikTokComentario,TikTokLive};
use App\Services\{TikTokConsulta,TikTokRegistro,TikTokProceso};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,DB};
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TikTokFuentesTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {
  parent::setUp();Storage::fake('local');Queue::fake();config(['tiktok.directorio'=>Storage::disk('local')->path('estado'),'tiktok.python'=>'/usr/bin/python3']);
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);$this->actingAs(User::create(['usuario'=>'admin_fuentes','name'=>'Administrador','email'=>'admin@example.test','password'=>'Prueba123456','rol'=>'admin','activo'=>true]));
 }
 private function cuenta(bool $activo=true): TikTokCuenta {return TikTokCuenta::create(['usuario'=>'perfil_objetivo','monitorear'=>$activo,'estado'=>$activo?'pendiente':'inactivo']);}
 private function evento(string $tipo,array $datos=[]): array {return ['v'=>1,'tipo'=>$tipo,'capturado_ms'=>TikTokConsulta::ahora(),'sala'=>'7777777777777777771']+$datos;}
 private function fuentes(TikTokCuenta $c): array {$this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>"@uno\n@dos"])->assertSessionHasNoErrors();return [$c->fuentes()->where('usuario','uno')->firstOrFail(),$c->fuentes()->where('usuario','dos')->firstOrFail()];}
 private function inicio(TikTokCuenta $c,TikTokFuente $f,string $sala): void {
  app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'conectando']),array_replace($this->evento('inicio',['deteccion'=>['cuenta_consultada'=>$c->usuario,'fuente_sala'=>'sala_publica_indicada','usuario_sala'=>$f->usuario,'solo_comentarios'=>true]]),['sala'=>$sala])],$f);
 }
 private function comentario(TikTokCuenta $c,TikTokFuente $f,string $sala,string $id): void {app(TikTokRegistro::class)->registrar($c,[array_replace($this->evento('comentario',['id'=>$id,'autor'=>'persona','texto'=>'Comentario '.$id,'publico'=>['usuario_sala'=>$f->usuario,'solo_comentarios'=>true]]),['sala'=>$sala])],$f);}
 public function test_lista_normaliza_separadores_y_duplicados_y_respeta_pausa(): void {
  $c=$this->cuenta(false);$this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>" @Uno\n@DOS, uno ; @tres "])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(['uno','dos','tres'],$c->fresh()->salasPublicas());$this->assertSame('uno',$c->fresh()->sala_publica_usuario);$this->assertFalse($c->fresh()->monitorear);
  $this->get(route('admin.tiktok.cuenta',$c))->assertOk()->assertSee('name="salas_publicas"',false)->assertSee('Estado de cada sala');
  $this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>''])->assertSessionHasNoErrors();$this->assertSame(0,$c->fuentes()->count());$this->assertNull($c->fresh()->sala_publica_usuario);$this->assertFalse($c->fresh()->monitorear);
 }
 public function test_lista_invalida_no_guarda_fuentes_parciales_y_limita_a_20(): void {
  $c=$this->cuenta();$this->fuentes($c);foreach(["uno\nhttps://127.0.0.1",implode(',',array_map(fn($i)=>'user'.$i,range(1,21)))]as$lista)$this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>$lista])->assertSessionHasErrors('salas_publicas');
  $this->assertSame(['uno','dos'],$c->fresh()->salasPublicas());
 }
 public function test_estados_y_reintentos_de_una_fuente_no_cierran_la_otra(): void {
  $c=$this->cuenta();[$uno,$dos]=$this->fuentes($c);$a='7777777777777777771';$b='7777777777777777772';$this->inicio($c,$uno,$a);$this->comentario($c,$uno,$a,'uno');
  app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'limitado','espera_segundos'=>1800])],$dos);
  $this->assertSame('en_live',$c->fresh()->estado);$this->assertSame('en_live',$c->lives()->where('sala',$a)->first()->estado);$this->assertNull($uno->fresh()->reintentar_ms);$this->assertGreaterThan(TikTokConsulta::ahora()+1790000,$dos->fresh()->reintentar_ms);
  $this->inicio($c,$dos,$b);$this->comentario($c,$dos,$b,'dos');$this->assertSame(2,$c->lives()->where('estado','en_live')->count());
  app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'error'])],$uno);
  $this->assertSame('en_live',$c->fresh()->estado);$this->assertSame('interrumpido',$c->lives()->where('sala',$a)->first()->estado);$this->assertSame('en_live',$c->lives()->where('sala',$b)->first()->estado);
  $this->inicio($c,$uno,$a);$this->assertSame('en_live',$c->lives()->where('sala',$a)->first()->estado);
  $this->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('fuentes.0.estado','en_live')->assertJsonPath('fuentes.1.estado','en_live')->assertJsonPath('total',2);
 }
 public function test_misma_sala_deduplica_y_retirar_una_fuente_conserva_chat_y_procedencia(): void {
  $c=$this->cuenta();[$uno,$dos]=$this->fuentes($c);$s='7777777777777777771';$this->inicio($c,$uno,$s);$this->inicio($c,$dos,$s);$this->comentario($c,$uno,$s,'compartido');$this->comentario($c,$dos,$s,'compartido');
  $this->assertSame(1,TikTokComentario::count());$this->assertSame(2,DB::table('tiktok_sesiones')->whereNull('fin_ms')->count());
  $this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>'dos'])->assertSessionHasNoErrors();$this->assertSame('en_live',$c->lives()->first()->estado);$this->assertSame('en_live',$c->fresh()->estado);
  $this->assertSame('uno',DB::table('tiktok_sesiones')->whereNotNull('fin_ms')->first()->usuario_sala);$this->assertSame('uno',TikTokComentario::first()->datos_publicos['usuario_sala']);
  $this->comentario($c,$uno,$s,'descartado');$this->assertSame(1,TikTokComentario::count());$this->comentario($c,$dos,$s,'nuevo');$this->assertSame(2,TikTokComentario::count());
  app(TikTokRegistro::class)->registrar($c,[$this->evento('fin')],$dos);$this->assertSame('terminado',$c->lives()->first()->estado);$this->assertSame(0,DB::table('tiktok_sesiones')->whereNull('fin_ms')->count());
 }
 public function test_guardar_o_reordenar_no_acorta_esperas_ni_cambia_identidad_de_fuentes(): void {
  $c=$this->cuenta();[$uno,$dos]=$this->fuentes($c);$espera=TikTokConsulta::ahora()+1800000;$uno->update(['estado'=>'limitado','reintentar_ms'=>$espera]);\App\Services\TikTokFuentes::resumir($c);
  $this->post(route('admin.tiktok.sala-publica',$c),['salas_publicas'=>"dos\nuno\ntres"])->assertSessionHasNoErrors();
  $this->assertSame($uno->id,$c->fuentes()->where('usuario','uno')->value('id'));$this->assertSame($espera,$uno->fresh()->reintentar_ms);$this->assertSame('limitado',$uno->fresh()->estado);$this->assertSame(['dos','uno','tres'],$c->fresh()->salasPublicas());$this->assertNull($c->fuentes()->where('usuario','tres')->value('reintentar_ms'));
 }
 public function test_pausa_manual_se_respeta_aunque_un_proceso_arranque_con_datos_anteriores(): void {
  $c=$this->cuenta();[$uno,$dos]=$this->fuentes($c);$anterior=$c->fresh();
  $this->post(route('admin.tiktok.captura',$c),['monitorear'=>0])->assertRedirect();
  $uno->update(['estado'=>'conectando']); // Un trabajo seleccionado justo antes de la pausa.
  \App\Services\TikTokFuentes::resumir($anterior);
  $this->assertFalse($c->fresh()->monitorear);$this->assertSame('inactivo',$c->fresh()->estado);
  $this->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('fuentes.0.etiqueta','Pausado')->assertJsonPath('estado','inactivo');
 }
 public function test_monitor_inicia_dos_fuentes_y_un_offline_no_impide_guardar_la_otra(): void {
  config(['tiktok.simultaneos'=>2]);$c=$this->cuenta();$this->fuentes($c);$dir=Storage::disk('local')->path('scripts');mkdir($dir,0700,true);$script=$dir.'/fuentes.py';
  $offline=json_encode($this->evento('estado',['estado'=>'sin_live']));$inicio=json_encode($this->evento('inicio'));$mensaje=json_encode($this->evento('comentario',['id'=>'dos-activa','autor'=>'persona','texto'=>'Mensaje de la segunda fuente','publico'=>['usuario_sala'=>'dos'] ]));
  file_put_contents($script,"import sys,time\nif sys.argv[1]=='uno':\n print(".json_encode($offline).",flush=True)\nelse:\n print(".json_encode($inicio).",flush=True)\n print(".json_encode($mensaje).",flush=True)\n time.sleep(10)\n");
  $factory=new class($script)extends TikTokProceso {public array $llamadas=[];public function __construct(private string $script){}public function crear(string $usuario,?string $salaPublica=null): Process{$this->llamadas[]=[$usuario,$salaPublica];$p=new Process(['/usr/bin/python3',$this->script,$salaPublica]);$p->setTimeout(null);return$p;}};
  $this->app->instance(TikTokProceso::class,$factory);$this->artisan('micv:tiktok-monitor',['--duracion'=>1])->assertSuccessful();
  $this->assertSame([[$c->usuario,'uno'],[$c->usuario,'dos']],$factory->llamadas);$this->assertSame(1,TikTokComentario::count());$this->assertSame('dos',TikTokComentario::first()->datos_publicos['usuario_sala']);$this->assertTrue($c->fresh()->monitorear);
 }
}
