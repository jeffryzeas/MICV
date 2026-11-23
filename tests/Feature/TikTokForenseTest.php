<?php
namespace Tests\Feature;
use App\Models\{User,TikTokCuenta,TikTokLive,TikTokComentario,TikTokAdjunto,TikTokExportacion};
use App\Services\{TikTokConsulta,TikTokRegistro,TikTokIntegridad,TikTokVideo,TikTokPdf};
use App\Jobs\{GuardarTikTokAvatar,ExportarTikTok};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,Http,DB};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
class TikTokForenseTest extends TestCase {
 use RefreshDatabase;
 private User $admin;
 protected function setUp(): void {
  parent::setUp();Storage::fake('local');Queue::fake([GuardarTikTokAvatar::class,ExportarTikTok::class]);config(['tiktok.directorio'=>Storage::disk('local')->path('tiktok'),'tiktok.zona'=>'America/Costa_Rica']);
  $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);$this->admin=User::create(['usuario'=>Str::random(8),'name'=>'Analista de prueba','email'=>Str::random(8).'@example.test','password'=>'Prueba123456','rol'=>'admin','activo'=>true]);$this->actingAs($this->admin);
  $this->app->instance(TikTokVideo::class,new class extends TikTokVideo{protected function resolver(string $host): array{return [['ip'=>'8.8.8.8']];}});Http::preventStrayRequests();
 }
 private function evento(string $tipo,array $d=[]): array {return ['v'=>1,'tipo'=>$tipo,'capturado_ms'=>TikTokConsulta::ahora(),'sala'=>'7333333333333333333']+$d;}
 private function capturar(int $n=2): TikTokCuenta {
  $c=TikTokCuenta::create(['usuario'=>'demo_'.Str::lower(Str::random(6)),'monitorear'=>true,'estado'=>'pendiente']);$events=[$this->evento('inicio',['titulo'=>'LIVE de prueba','perfil'=>['nombre'=>'Emisor público']])];
  for($i=0;$i<$n;$i++)$events[]=$this->evento('comentario',['id'=>(string)($i+100),'autor'=>'public_user','nombre'=>'Participante público','texto'=>"Texto de prueba 😀\u{2028} segunda línea ".$i,'ocurrido_ms'=>TikTokConsulta::ahora()-$i*86400000,'publico'=>['id_usuario'=>'9999999999999999999','usuario'=>'public_user','avatar'=>'https://p16.tiktokcdn.com/foto.png?token=SECRETO','bio'=>'Biografía pública','id_evento_fuente'=>'tiktok']]);
  app(TikTokRegistro::class)->registrar($c,$events);return$c;
 }
 private function descargarFoto(): TikTokAdjunto {
  $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aR1sAAAAASUVORK5CYII=');Http::fake(['*'=>Http::response($png,200,['Content-Type'=>'image/png'])]);$a=TikTokAdjunto::firstOrFail();(new GuardarTikTokAvatar($a->id))->handle(app(TikTokVideo::class));return$a->fresh();
 }
 private function pdf(): TikTokPdf {return $this->mock(TikTokPdf::class,fn($m)=>$m->shouldReceive('generar')->once()->withArgs(fn($d)=>$d['filas']->count()===2&&strlen($d['hashDatos'])===64)->andReturnUsing(function($d){$doc=(new TikTokPdf)->documento($d);$this->assertStringContainsString('Informe técnico',$doc['html']);$this->assertStringNotContainsString('SECRETO',$doc['html']);return '%PDF-1.4 ejemplo de informe';}));}
 public function test_comentarios_de_cada_perfil_se_imprimen_debajo_y_el_creador_se_coteja_por_uid(): void {
  $c=$this->capturar();$live=$c->lives()->first();$live->update(['perfil_inicio'=>['nombre'=>'Titular','id_usuario'=>'7000000000000000001','usuario'=>$c->usuario]]);
  $ahora=TikTokConsulta::ahora();
  app(TikTokRegistro::class)->registrar($c,[
   $this->evento('comentario',['id'=>'creador1','autor'=>'alias_nuevo','nombre'=>'Creador de prueba','texto'=>'Mensaje escrito del creador','ocurrido_ms'=>$ahora,'publico'=>['id_usuario'=>'7000000000000000001','usuario'=>'alias_nuevo']]),
   $this->evento('comentario',['id'=>'otro1','autor'=>$c->usuario,'nombre'=>'Otra cuenta','texto'=>'Un alias igual no prueba titularidad','ocurrido_ms'=>$ahora,'publico'=>['id_usuario'=>'7000000000000000002','usuario'=>$c->usuario]])
  ]);
  $this->post(route('admin.tiktok.exportar-live',[$c,$live]));$e=TikTokExportacion::first();
  $pdf=$this->mock(TikTokPdf::class,fn($mock)=>$mock->shouldReceive('generar')->once()->andReturnUsing(function($d){
   $this->assertCount(3,$d['autores']);$this->assertTrue($d['autores'][0]['es_creador']);$this->assertSame('7000000000000000001',$d['autores'][0]['id']);
   $this->assertCount(1,array_filter($d['autores'],fn($a)=>$a['es_creador']));
   $html=(new TikTokPdf)->documento($d)['html'];$dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new \DOMXPath($dom);
   $grupos=$xp->query('//section[@class="participant"]');$this->assertCount(3,$grupos);$this->assertStringContainsString('Mensaje escrito del creador',$grupos->item(0)->textContent);
   foreach($d['autores'] as $i=>$autor){$grupo=$grupos->item($i);$this->assertSame($autor['comentarios'],$xp->query('.//article[@class="entry"]',$grupo)->length);foreach($autor['mensajes'] as $msg)$this->assertStringContainsString($msg['registro']['texto'],$grupo->textContent);}
   return '%PDF-1.4 ejemplo';
  }));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);
 }
 public function test_creador_legacy_por_usuario_se_declara_sin_cotejo_de_id(): void {
  $c=$this->capturar(1);$m=$c->comentarios()->first();$m->update(['autor'=>strtoupper($c->usuario),'datos_publicos'=>['usuario'=>strtoupper($c->usuario)],'hash_registro'=>null]);
  $this->post(route('admin.tiktok.exportar-live',[$c,$c->lives()->first()]));$e=TikTokExportacion::first();
  $pdf=$this->mock(TikTokPdf::class,fn($mock)=>$mock->shouldReceive('generar')->once()->andReturnUsing(function($d){$this->assertTrue($d['autores'][0]['es_creador']);$this->assertStringContainsString('sin cotejo de ID',$d['autores'][0]['criterio_creador']);return '%PDF-1.4 ejemplo';}));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);
 }
 public function test_no_selecciona_invitado_y_conserva_referencias_historicas_en_informes(): void {
  $host=TikTokCuenta::create(['usuario'=>'anfitrion_privado','monitorear'=>false,'estado'=>'restringido']);
  $this->get(route('admin.tiktok.index'))->assertOk()->assertDontSee('name="anfitrion_id"',false);
  $this->post(route('admin.tiktok.guardar'),['usuario'=>'invitado_publico','anfitrion_id'=>$host->id])->assertRedirect();$c=TikTokCuenta::where('usuario','invitado_publico')->firstOrFail();
  $this->assertNull($c->anfitrion_id);$this->assertFalse($c->monitorear);
  // Una referencia de la versión anterior sigue siendo legible, sin exigirla al registrar.
  $c->update(['anfitrion_id'=>$host->id]);
  $this->post(route('admin.tiktok.captura',$c),['monitorear'=>1])->assertRedirect();$c->refresh();
  app(TikTokRegistro::class)->registrar($c,[$this->evento('inicio'),$this->evento('comentario',['id'=>'guest-comment','autor'=>'visitante','texto'=>'Comentario de la sala pública'])]);
  $this->assertSame(0,$host->comentarios()->count());$this->assertSame('restringido',$host->fresh()->estado);$this->assertSame(1,$c->comentarios()->count());
  $this->post(route('admin.tiktok.exportar-live',[$c,$c->lives()->first()]));$e=TikTokExportacion::first();
  $pdf=$this->mock(TikTokPdf::class,fn($mock)=>$mock->shouldReceive('generar')->once()->andReturnUsing(function($d){$this->assertSame('invitado_publico',$d['referenciaSala']['cuenta_observada']);$this->assertSame('anfitrion_privado',$d['referenciaSala']['anfitrion_declarado']);$html=(new TikTokPdf)->documento($d)['html'];$this->assertStringContainsString('Esta relación no fue verificada por TikTok',$html);return '%PDF-1.4 ejemplo';}));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);
  $host->delete();$this->assertNull($c->fresh()->anfitrion_id);$this->assertSame(1,$c->comentarios()->count());
 }
 public function test_sala_automatica_preserva_perfil_objetivo_y_titular_por_separado(): void {
  $c=TikTokCuenta::create(['usuario'=>'usuario_invitado','monitorear'=>true]);$n=TikTokConsulta::ahora();
  $inicio=$this->evento('inicio',['perfil'=>['nombre'=>'Titular real de sala','id_usuario'=>'7000000000000000001','usuario'=>'titular_publico'],'perfil_objetivo'=>['nombre'=>'Invitado público','id_usuario'=>'7000000000000000002','usuario'=>$c->usuario],'deteccion'=>['cuenta_consultada'=>$c->usuario,'fuente_sala'=>'perfil_publico']]);
  $events=[$inicio,$this->evento('comentario',['id'=>'a','autor'=>$c->usuario,'nombre'=>'Invitado','texto'=>'Comentario del usuario monitoreado','publico'=>['id_usuario'=>'7000000000000000002','usuario'=>$c->usuario]]),$this->evento('comentario',['id'=>'b','autor'=>'titular_publico','nombre'=>'Titular','texto'=>'Comentario del titular de la sala','publico'=>['id_usuario'=>'7000000000000000001','usuario'=>'titular_publico']])];
  app(TikTokRegistro::class)->registrar($c,$events);$c->refresh();$live=$c->lives()->first();
  $this->assertSame('Invitado público',$c->perfil_publico['nombre']);$this->assertSame('titular_publico',$live->perfil_inicio['usuario']);$this->assertSame('perfil_publico',$live->deteccion_inicio['fuente_sala']);$this->assertSame(2,$live->comentarios);
  $this->post(route('admin.tiktok.exportar-live',[$c,$live]));$e=TikTokExportacion::first();
  $pdf=$this->mock(TikTokPdf::class,fn($mock)=>$mock->shouldReceive('generar')->once()->andReturnUsing(function($d){$this->assertTrue($d['autores'][0]['es_creador']);$this->assertSame('titular_publico',$d['autores'][0]['autor']);$this->assertFalse($d['autores'][1]['es_creador']);$this->assertSame('perfil_publico',$d['filas'][0]['contexto']['deteccion_inicio']['fuente_sala']);$html=(new TikTokPdf)->documento($d)['html'];$this->assertStringContainsString('Detección automática',$html);return '%PDF-1.4 ejemplo';}));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);
  $inicio['deteccion']['fuente_sala']='api_usuario';$inicio['perfil_objetivo']=[];app(TikTokRegistro::class)->registrar($c,[$inicio]);
  $this->assertSame('perfil_publico',$live->fresh()->deteccion_inicio['fuente_sala']);$this->assertSame('Invitado público',$c->fresh()->perfil_publico['nombre']);
 }
 public function test_sala_no_determinada_no_desactiva_monitoreo_ni_inventa_un_live(): void {
  $c=TikTokCuenta::create(['usuario'=>'usuario_sin_sala','monitorear'=>true]);
  app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'no_determinado','detalle'=>'Sin asociación pública suficiente'])]);
  $this->assertTrue($c->fresh()->monitorear);$this->assertSame('no_determinado',$c->estado);$this->assertSame(0,$c->lives()->count());
  $this->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('estado','no_determinado')->assertJsonPath('etiqueta','No se pudo determinar la sala');
 }
 public function test_chat_de_sala_publica_se_guarda_bajo_perfil_sin_asegurar_su_participacion_y_sin_video(): void {
  $c=TikTokCuenta::create(['usuario'=>'perfil_objetivo','sala_publica_usuario'=>'sala_publica','monitorear'=>true]);
  app(TikTokRegistro::class)->registrar($c,[$this->evento('inicio',['titulo'=>'Chat público','perfil_objetivo'=>['nombre'=>'Perfil objetivo','usuario'=>$c->usuario],'perfil'=>[],'deteccion'=>['cuenta_consultada'=>$c->usuario,'fuente_sala'=>'sala_publica_indicada','usuario_sala'=>'sala_publica','solo_comentarios'=>true]]),$this->evento('comentario',['id'=>'solo-chat','autor'=>'comentario_publico','texto'=>'Comentario recibido sin solicitar video','publico'=>['usuario'=>'comentario_publico','id_usuario'=>'7000000000000000001','usuario_sala'=>'sala_publica','solo_comentarios'=>true]])]);
  $live=$c->lives()->firstOrFail();$this->assertSame(1,$live->comentarios);$this->assertFalse($live->deteccion_inicio['participacion_confirmada']);$this->assertSame('sala_publica',$live->deteccion_inicio['usuario_sala']);
  $this->getJson(route('admin.tiktok.eventos',$c))->assertOk()->assertJsonPath('etiqueta','Chat de la sala en directo')->assertJsonPath('comentarios.0.usuario_sala','sala_publica')->assertJsonPath('video.disponible',false)->assertJsonPath('total',1);
  $this->get(route('admin.tiktok.cuenta',$c))->assertOk()->assertSee('tt-workspace-chat')->assertSee('no está confirmada automáticamente');
  $this->post(route('admin.tiktok.exportar-live',[$c,$live]));$e=TikTokExportacion::first();
  $pdf=$this->mock(TikTokPdf::class,fn($mock)=>$mock->shouldReceive('generar')->once()->andReturnUsing(function($d){$this->assertFalse($d['autores'][0]['es_creador']);$html=(new TikTokPdf)->documento($d)['html'];$this->assertStringContainsString('Cuenta de acceso al chat: @sala_publica',$html);$this->assertStringContainsString('Fuente del chat: @sala_publica',$html);$this->assertStringContainsString('Comentario recibido sin solicitar video',$html);return '%PDF-1.4 ejemplo';}));
  (new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$pdf);$this->assertSame('listo',$e->fresh()->estado);$this->assertSame(1,$e->fresh()->metadatos['resumen']['sellados']);
 }
 public function test_deduplica_fotos_y_preserva_ids_grandes_y_hash_del_registro(): void {
  $c=$this->capturar();Queue::assertPushed(GuardarTikTokAvatar::class,1);$this->assertSame(1,TikTokAdjunto::count());$this->assertSame(2,TikTokComentario::count());
  foreach(TikTokComentario::all()as$m){$this->assertSame('9999999999999999999',$m->autor_id);$this->assertSame($m->hash_registro,hash('sha256',TikTokIntegridad::json(TikTokIntegridad::original($m))));$this->assertStringNotContainsString('SECRETO',json_encode($m->datos_publicos));}
  $this->assertStringNotContainsString('SECRETO',DB::table('tiktok_adjuntos')->value('origen_url'));$this->assertSame(1,DB::table('tiktok_sesiones')->count());
 }
 public function test_foto_original_se_conserva_y_se_descarga_con_hash_y_permisos(): void {
  $this->capturar();$a=$this->descargarFoto();$this->assertSame('listo',$a->estado);$this->assertSame($a->sha256,hash_file('sha256',Storage::disk('local')->path($a->archivo)));
  $r=$this->get(route('admin.tiktok.adjunto',[$a,'descarga'=>1]));$r->assertOk()->assertHeader('Content-Type','image/png');$this->assertStringContainsString('attachment',$r->headers->get('Content-Disposition'));
  Storage::disk('local')->put($a->archivo,'alterado');$this->get(route('admin.tiktok.adjunto',[$a,'descarga'=>1]))->assertStatus(409);
 }
 public function test_exportacion_por_live_incluye_fechas_anteriores_y_paquete_verificable(): void {
  $c=$this->capturar();$this->descargarFoto();$l=$c->lives()->firstOrFail();$this->post(route('admin.tiktok.exportar-live',[$c,$l]),['referencia'=>'DEMO-001'])->assertRedirect();$e=TikTokExportacion::firstOrFail();$this->assertSame(2,$e->total);
  config(['tiktok.zona'=>'Asia/Tokyo']);(new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$this->pdf());$e->refresh();$this->assertSame('listo',$e->estado);$this->assertSame(2,$e->procesados);$this->assertSame('America/Costa_Rica',$e->filtros['zona']);
  $z=new \ZipArchive();$this->assertTrue($z->open(Storage::disk('local')->path($e->paquete)));$manifest=json_decode($z->getFromName('manifiesto.json'),true);$ndjson=$z->getFromName('datos.ndjson');$this->assertSame(hash('sha256',$ndjson),$manifest['sha256_datos']);$this->assertSame(2,$manifest['resumen']['sellados']);$this->assertSame(1,$manifest['resumen']['fotos_disponibles']);$this->assertSame('DEMO-001',$manifest['referencia']);$this->assertStringNotContainsString('SECRETO',$z->getFromName('manifiesto.json').$ndjson);
  $path=Storage::disk('local')->path('verificar');mkdir($path);$z->extractTo($path);$z->close();$p=new Process(['/usr/bin/python3','verificar.py'],$path);$p->setTimeout(10)->mustRun();$this->assertStringContainsString('verificada',$p->getOutput());
  file_put_contents($path.'/datos.ndjson',$ndjson.'alterado');$p->run();$this->assertFalse($p->isSuccessful());$this->get(route('admin.tiktok.paquete',$e))->assertOk();
  $otro=User::create(['usuario'=>Str::random(8),'name'=>'Otro admin','email'=>Str::random(8).'@example.test','password'=>'Prueba123456','rol'=>'admin','activo'=>true]);$this->actingAs($otro)->get(route('admin.tiktok.paquete',$e))->assertNotFound();
 }
 public function test_no_reconstruye_hashes_originales_para_datos_antiguos(): void {
  $c=$this->capturar();TikTokComentario::query()->update(['hash_registro'=>null,'datos_publicos'=>null]);$l=$c->lives()->first();$this->post(route('admin.tiktok.exportar-live',[$c,$l]));$e=TikTokExportacion::first();(new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$this->pdf());
  $this->assertSame(2,$e->fresh()->metadatos['resumen']['sin_sello_original']);$this->assertSame(0,$e->fresh()->metadatos['resumen']['sellados']);
 }
 public function test_bitacora_distingue_reconexion_y_cierre_sin_perder_comentarios(): void {
  $c=$this->capturar();app(TikTokRegistro::class)->registrar($c,[$this->evento('estado',['estado'=>'error']),$this->evento('inicio',['titulo'=>'Reanudado']),$this->evento('latido'),$this->evento('fin')]);
  $this->assertSame(2,DB::table('tiktok_sesiones')->count());$this->assertSame(0,DB::table('tiktok_sesiones')->whereNull('fin_ms')->count());$this->assertSame('error',DB::table('tiktok_sesiones')->orderBy('inicio_ms')->first()->motivo);$this->assertSame(2,TikTokComentario::count());
 }
 public function test_eliminar_mientras_descarga_no_deja_foto_huerfana(): void {
  $c=$this->capturar();$a=TikTokAdjunto::first();Http::fake(function()use($c){$c->delete();return Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aR1sAAAAASUVORK5CYII='),200,['Content-Type'=>'image/png']);});
  (new GuardarTikTokAvatar($a->id))->handle(app(TikTokVideo::class));$this->assertSame(0,TikTokAdjunto::count());$this->assertFalse(Storage::disk('local')->exists('tiktok/avatares/'.$a->id));
 }
 public function test_informe_mixto_identifica_publicacion_importada_y_su_origen(): void {
  $c=$this->capturar(1);$p=\App\Models\TikTokPublicacion::create(['cuenta_id'=>$c->id,'video_id'=>'7777777777777777777']);
  TikTokComentario::create(['cuenta_id'=>$c->id,'publicacion_id'=>$p->id,'clave'=>hash('sha256','importado'),'autor'=>'importado','nombre'=>'Nombre importado','texto'=>'Registro autorizado','origen'=>'importacion_autorizada','ocurrido_ms'=>TikTokConsulta::ahora(),'capturado_ms'=>TikTokConsulta::ahora()]);
  $this->post(route('admin.tiktok.exportar',$c));$e=TikTokExportacion::first();(new ExportarTikTok($e->id))->handle(app(TikTokConsulta::class),$this->pdf());$e->refresh();
  $z=new \ZipArchive();$z->open(Storage::disk('local')->path($e->paquete));$rows=array_map(fn($s)=>json_decode($s,true),explode("\n",trim($z->getFromName('datos.ndjson'))));$z->close();$row=collect($rows)->first(fn($r)=>$r['registro']['origen']==='importacion_autorizada');
  $this->assertSame('7777777777777777777',$row['contexto']['video_id']);$this->assertStringEndsWith('/video/7777777777777777777',$row['contexto']['publicacion_url']);$this->assertNull($row['hash_captura']);$this->assertSame(1,$e->metadatos['resumen']['sin_sello_original']);
 }

 public function test_boton_exporta_el_live_seleccionado_aunque_haya_otro_en_directo(): void {
  $c=$this->capturar();$old=$c->lives()->first();app(TikTokRegistro::class)->registrar($c,[array_replace($this->evento('inicio',['titulo'=>'Otro LIVE']),['sala'=>'7555555555555555555'])]);
  $this->getJson(route('admin.tiktok.eventos',[$c,'live_id'=>$old->id]))->assertOk()->assertJsonPath('exportar_live_url',route('admin.tiktok.exportar-live',[$c,$old]))->assertJsonPath('comentarios_live_informe',2)->assertJsonPath('exportar_live_etiqueta','PDF del LIVE seleccionado');
 }

}
