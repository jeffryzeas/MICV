<?php
namespace Tests\Feature;

use App\Models\{User,TikTokCuenta,TikTokLive,TikTokComentario,TikTokExportacion};
use App\Services\{TikTokVideo,TikTokRegistro,TikTokConsulta};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage,Crypt,File,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class TikTokVideoTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void {
        parent::setUp();Storage::fake('local');Queue::fake();config(['tiktok.directorio'=>Storage::disk('local')->path('tiktok')]);
        $this->withoutMiddleware([\App\Http\Middleware\CorreoVerificado::class,\App\Http\Middleware\AceptaTerminos::class]);
        $this->admin=User::create(['usuario'=>'admin_'.Str::random(8),'name'=>'Admin','email'=>Str::random(8).'@example.test','password'=>'Prueba123456','rol'=>'admin','activo'=>true]);$this->actingAs($this->admin);
        $this->app->instance(TikTokVideo::class,new class extends TikTokVideo{protected function resolver(string $host): array{return [['ip'=>'8.8.8.8']];}});Http::preventStrayRequests();
    }
    private function cuenta(): TikTokCuenta{return TikTokCuenta::create(['usuario'=>'demo_'.Str::lower(Str::random(6)),'monitorear'=>true,'estado'=>'pendiente']);}
    private function inicio(TikTokCuenta $c,string $sala='7000000000000000001',string $titulo='LIVE público'): TikTokLive {
        app(TikTokRegistro::class)->registrar($c,[['v'=>1,'tipo'=>'inicio','sala'=>$sala,'capturado_ms'=>TikTokConsulta::ahora(),'titulo'=>$titulo,'perfil'=>['nombre'=>'Nombre público','bio'=>'Biografía pública','avatar'=>'https://img.tiktokcdn.com/avatar.png?secret=avatar','region'=>'NI'],'video_hls'=>'https://pull-hls.tiktokcdn.com/live/index.m3u8?secret=origen']]);return $c->lives()->where('sala',$sala)->firstOrFail();
    }
    public function test_historial_actualiza_sin_comentarios_y_ordena_por_nombre_y_hora_am_pm(): void {
        $c=$this->cuenta();$this->getJson(route('admin.tiktok.eventos',$c))->assertJsonCount(0,'lives');
        $this->inicio($c,'7000000000000000001','Zeta');$this->inicio($c,'7000000000000000002','Alfa');
        $r=$this->getJson(route('admin.tiktok.eventos',[$c,'orden_lives'=>'nombre']));$r->assertOk()->assertJsonCount(2,'lives')->assertJsonPath('lives.0.titulo','Alfa')->assertJsonPath('perfil.region','Nicaragua')->assertJsonPath('video.disponible',true);
        $this->assertMatchesRegularExpression('/(?:AM|PM)$/',$r->json('lives.0.fecha'));$this->assertStringNotContainsString('secret=', $r->getContent());
        $this->get(route('admin.tiktok.cuenta',[$c,'q_lives'=>'Alfa']))->assertOk()->assertSee('Alfa')->assertSee('Nombre público');
    }
    public function test_hls_se_reescribe_sin_url_libre_y_recursos_requieren_token_admin_y_live_activo(): void {
        $c=$this->cuenta();$l=$this->inicio($c);
        Http::fake(['*index.m3u8*'=>Http::response("#EXTM3U\n#EXT-X-TARGETDURATION:2\n#EXTINF:2,\nseg.ts\n",200,['Content-Type'=>'application/vnd.apple.mpegurl']),'*seg.ts'=>Http::response('video-bytes',200,['Content-Type'=>'video/mp2t'])]);
        $r=$this->get(route('admin.tiktok.video',[$c,$l]));$r->assertOk()->assertHeader('Cache-Control','no-store, private');$this->assertStringNotContainsString('tiktokcdn.com',$r->getContent());
        $lines=explode("\n",trim($r->getContent()));$segment=end($lines);$this->get($segment)->assertOk()->assertContent('video-bytes');
        $this->get(route('admin.tiktok.video.recurso',[$c,$l,'t'=>'inventado']))->assertForbidden();$otro=$this->cuenta();$this->get(route('admin.tiktok.video',[$otro,$l]))->assertNotFound();
        $c->update(['monitorear'=>false]);$this->get($segment)->assertGone();
    }
    public function test_rechaza_redirecciones_y_origenes_fuera_del_cdn_sin_hacer_proxy_abierto(): void {
        $v=app(TikTokVideo::class);foreach(['https://127.0.0.1/x','https://pull.tiktokcdn.com.evil.test/a','https://user:pass@pull.tiktokcdn.com/a','http://pull.tiktokcdn.com/a','https://pull.tiktokcdn.com:8080/a','https://pull.tiktokcdn.com/a#fragmento']as$url)$this->assertFalse($v->urlValida($url));
        $c=$this->cuenta();$l=$this->inicio($c);Http::fake(['*'=>Http::response('',302,['Location'=>'http://127.0.0.1/secreto'])]);$this->get(route('admin.tiktok.video',[$c,$l]))->assertStatus(502);Http::assertSentCount(1);
    }
    public function test_dns_privado_y_manifiesto_remoto_no_se_siguen(): void {
        $c=$this->cuenta();$l=$this->inicio($c);
        $this->app->instance(TikTokVideo::class,new class extends TikTokVideo{protected function resolver(string $host): array{return [['ip'=>'127.0.0.1']];}});
        $this->get(route('admin.tiktok.video',[$c,$l]))->assertStatus(502);Http::assertNothingSent();
        $this->app->instance(TikTokVideo::class,new class extends TikTokVideo{protected function resolver(string $host): array{return [['ip'=>'8.8.8.8']];}});
        Http::fake(['*'=>Http::response("#EXTM3U\nhttps://evil.test/segment.ts\n",200,['Content-Type'=>'application/vnd.apple.mpegurl'])]);$this->get(route('admin.tiktok.video',[$c,$l]))->assertStatus(502);Http::assertSentCount(1);
    }
    public function test_foto_publica_es_local_y_no_se_reproducen_senales_protegidas(): void {
        $c=$this->cuenta();$l=$this->inicio($c);$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aR1sAAAAASUVORK5CYII=');
        Http::fake(['*avatar.png*'=>Http::response($png,200,['Content-Type'=>'image/png']),'*m3u8*'=>Http::response("#EXTM3U\n#EXT-X-KEY:METHOD=SAMPLE-AES,URI=\"key\"\nseg.ts\n",200,['Content-Type'=>'application/vnd.apple.mpegurl'])]);
        $this->get(route('admin.tiktok.avatar',$c))->assertOk()->assertHeader('Content-Type','image/png');$this->get(route('admin.tiktok.video',[$c,$l]))->assertStatus(502);
    }
    public function test_cerrar_sesion_no_pausa_y_se_guardan_comentarios_publicos_de_un_perfil_privado(): void {
        $c=$this->cuenta();$l=$this->inicio($c);$this->post(route('logout'))->assertRedirect();$this->assertGuest();$this->assertTrue($c->fresh()->monitorear);
        app(TikTokRegistro::class)->registrar($c,[['v'=>1,'tipo'=>'comentario','sala'=>$l->sala,'id'=>'privado-1','autor'=>'perfil_privado','nombre'=>'Persona','texto'=>'Comentario visible en el LIVE público','capturado_ms'=>TikTokConsulta::ahora(),'privado'=>true]]);
        $this->assertSame(1,TikTokComentario::count());$this->get(route('admin.tiktok.video',[$c,$l]))->assertRedirect();$this->actingAs($this->admin)->getJson(route('admin.tiktok.eventos',$c))->assertJsonPath('comentarios.0.autor','perfil_privado');
    }
    public function test_eliminar_monitoreo_elimina_historial_y_no_resucita_por_eventos_en_vuelo(): void {
        $c=$this->cuenta();$live=$this->inicio($c);$id=$c->id;$path=config('tiktok.directorio').'/reproduccion/'.$live->id.'.enc';$this->assertFileExists($path);$this->delete(route('admin.tiktok.eliminar',$c))->assertRedirect(route('admin.tiktok.index'));
        $this->assertFileDoesNotExist($path);$this->assertDatabaseMissing('tiktok_cuentas',['id'=>$id]);$this->assertSame(0,TikTokLive::count());$this->assertSame(0,app(TikTokRegistro::class)->registrar($c,[['v'=>1,'tipo'=>'latido','capturado_ms'=>TikTokConsulta::ahora()]]));
    }
    public function test_redireccion_publica_validada_resuelve_segmentos_en_el_origen_final(): void {
        $c=$this->cuenta();$l=$this->inicio($c);
        Http::fake(['https://pull-hls.tiktokcdn.com/live/index.m3u8*'=>Http::response('',302,['Location'=>'https://edge.tiktokcdn.com/actual/list.m3u8']),'https://edge.tiktokcdn.com/actual/list.m3u8'=>Http::response("#EXTM3U\n#EXTINF:2,\nseg.ts\n",200,['Content-Type'=>'application/vnd.apple.mpegurl']),'https://edge.tiktokcdn.com/actual/seg.ts'=>Http::response('segmento',200,['Content-Type'=>'video/mp2t'])]);
        $r=$this->get(route('admin.tiktok.video',[$c,$l]));$r->assertOk();$lines=explode("\n",trim($r->getContent()));$this->get(end($lines))->assertOk()->assertContent('segmento');Http::assertSent(fn($r)=>$r->url()==='https://edge.tiktokcdn.com/actual/seg.ts');
    }
    public function test_hls_aes128_con_clave_publica_es_compatible_sin_licencias_drm(): void {
        $c=$this->cuenta();$l=$this->inicio($c);Http::fake(['*index.m3u8*'=>Http::response("#EXTM3U\n#EXT-X-KEY:METHOD=AES-128,URI=\"clave.key\"\n#EXTINF:2,\nseg.ts\n",200,['Content-Type'=>'application/vnd.apple.mpegurl']),'*clave.key'=>Http::response('0123456789abcdef',200,['Content-Type'=>'application/octet-stream'])]);
        $r=$this->get(route('admin.tiktok.video',[$c,$l]));$r->assertOk();preg_match('/URI="([^"]+)"/',$r->getContent(),$m);$this->get($m[1])->assertOk()->assertContent('0123456789abcdef');
    }

}
