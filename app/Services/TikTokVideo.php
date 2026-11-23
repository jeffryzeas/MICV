<?php
namespace App\Services;

use App\Models\{TikTokCuenta,TikTokLive};
use GuzzleHttp\Psr7\{Uri,UriResolver};
use Illuminate\Support\Facades\{Crypt,File,Http};
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Reproducción de HLS público. Sin URL libre ni credenciales; redirecciones públicas validadas. */
class TikTokVideo
{
    public function urlValida(string $url): bool
    {
        if(strlen($url)>2048||preg_match('/[\x00-\x20\x7f]/',$url))return false;
        $p=parse_url($url);if(!$p||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||($p['port']??443)!==443)return false;
        $host=strtolower($p['host']??'');if(!preg_match('/^[a-z0-9.-]+$/D',$host))return false;
        foreach(['tiktokcdn.com','tiktokcdn-us.com','tiktokcdn-eu.com']as$dominio)if(str_ends_with($host,'.'.$dominio))return true;
        return false;
    }
    public function registrar(TikTokLive $live,?string $url): void
    {
        $path=$this->archivo($live);if(!$url||!$this->urlValida($url)){File::delete($path);return;}
        File::ensureDirectoryExists(dirname($path),0700);$tmp=$path.'.tmp';
        file_put_contents($tmp,Crypt::encryptString($url),LOCK_EX);chmod($tmp,0600);rename($tmp,$path);
    }
    private function archivo(TikTokLive $live): string{return config('tiktok.directorio').'/reproduccion/'.$live->id.'.enc';}
    private function fuente(TikTokLive $live): ?string
    {
        $bytes=@file_get_contents($this->archivo($live));if(!$bytes)return null;
        try{$url=Crypt::decryptString($bytes);return $this->urlValida($url)?$url:null;}catch(\Throwable){return null;}
    }
    public function activo(TikTokCuenta $cuenta,TikTokLive $live): bool
    {
        return !$cuenta->sala_publica_usuario&&$live->cuenta_id===$cuenta->id&&$cuenta->monitorear&&$cuenta->estado==='en_live'&&$live->estado==='en_live'&&($cuenta->latido_ms??0)>TikTokConsulta::ahora()-45000;
    }
    public function estado(TikTokCuenta $cuenta,?TikTokLive $live): array
    {
        if($cuenta->sala_publica_usuario)return ['disponible'=>false,'url'=>null,'mensaje'=>'Modo solo comentarios: no se solicita señal de video.'];
        $disponible=$live&&$this->activo($cuenta,$live)&&$this->fuente($live);
        return ['disponible'=>(bool)$disponible,'url'=>$disponible?route('admin.tiktok.video',[$cuenta,$live]):null,
            'mensaje'=>$disponible?'Señal pública disponible. Pulse Reproducir para verla.':($live&&$this->activo($cuenta,$live)?'TikTok no entregó una señal HLS pública compatible. Puede abrir el LIVE en TikTok; la captura de comentarios continúa.':'La reproducción estará disponible cuando se conecte a un LIVE público compatible.')];
    }
    protected function resolver(string $host): array {return dns_get_record($host,DNS_A|DNS_AAAA)?:[];}
    protected function direccion(string $host): string
    {
        $registros=$this->resolver($host);$ips=[];
        foreach($registros?:[]as$r){$ip=$r['ip']??$r['ipv6']??null;if(!$ip)continue;
            if(str_starts_with(strtolower($ip),'::ffff:')||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))abort(502,'El origen de video no es válido.');$ips[]=$ip;
        }
        if(!$ips)abort(502,'No se pudo resolver el servidor de video público.');return $ips[0];
    }
    private function token(string $url,TikTokLive $live): string
    {
        abort_unless($this->urlValida($url),502,'El origen de video no es compatible.');
        return Crypt::encryptString(json_encode(['url'=>$url,'live'=>$live->id,'exp'=>time()+1200],JSON_THROW_ON_ERROR));
    }
    private function contenido(string $url,?string $rango,int $maximo=16777216): array
    {
        abort_unless($this->urlValida($url)&&extension_loaded('curl'),502,'El servidor no admite este origen de video.');
        $headers=['Accept'=>'*/*','User-Agent'=>'MiCV-PublicLive/1.0'];if($rango)$headers['Range']=$rango;
        try{
            for($salto=0;$salto<=2;$salto++){
                abort_unless($this->urlValida($url),502,'El origen de video no es compatible.');
                $host=parse_url($url,PHP_URL_HOST);$ip=$this->direccion($host);$resolve=$host.':443:'.(str_contains($ip,':')?'['.$ip.']':$ip);
                $r=Http::withHeaders($headers)->withOptions(['allow_redirects'=>false,'stream'=>true,'verify'=>true,'connect_timeout'=>5,'timeout'=>10,'curl'=>[CURLOPT_RESOLVE=>[$resolve],CURLOPT_PROXY=>'']])->get($url);
                if(in_array($r->status(),[301,302,303,307,308],true)){
                    abort_if($salto===2||!$r->header('Location'),502,'La señal redirige demasiadas veces.');
                    $url=(string)UriResolver::resolve(new Uri($url),new Uri($r->header('Location')));continue;
                }
                if(!in_array($r->status(),[200,206],true))abort(502,'El servidor público rechazó el recurso (HTTP '.$r->status().').');
                $body=$r->toPsrResponse()->getBody();$bytes='';
                while(!$body->eof()){$bytes.=$body->read(65536);if(strlen($bytes)>$maximo)abort(502,'El recurso supera el tamaño permitido.');}
                return [$bytes,strtolower(explode(';',$r->header('Content-Type'))[0]),$r->status(),$r->header('Content-Range'),$url];
            }
        }catch(HttpException $e){throw $e;}catch(\Throwable){abort(502,'No se pudo leer la señal pública. Revise la conexión del VPS.');}
    }
    public function descargarImagen(string $url): array {
        [$bytes,$tipo]=$this->contenido($url,null,1048576);$info=@getimagesizefromstring($bytes);
        abort_unless(in_array($tipo,['image/jpeg','image/png','image/webp'],true)&&$info!==false&&in_array($info['mime'],['image/jpeg','image/png','image/webp'],true)&&$info[0]>0&&$info[1]>0&&$info[0]*$info[1]<=16000000,502,'No hay una foto pública compatible.');
        return [$bytes,$info['mime']];
    }
    public function avatar(TikTokCuenta $cuenta)
    {
        $url=$cuenta->perfil_publico['avatar']??null;abort_unless(is_string($url)&&$this->urlValida($url),404);
        [$bytes,$tipo]=$this->descargarImagen($url);
        return response($bytes,200,['Content-Type'=>$tipo,'Cache-Control'=>'private, max-age=300','Referrer-Policy'=>'no-referrer']);
    }
    public function eliminar(array $ids): void {foreach($ids as$id)File::delete(config('tiktok.directorio').'/reproduccion/'.(int)$id.'.enc');}
    private function manifiesto(string $texto,string $base,TikTokCuenta $cuenta,TikTokLive $live): string
    {
        abort_if(strlen($texto)>262144||!str_starts_with(ltrim($texto), '#EXTM3U'),502,'La señal no contiene un manifiesto HLS válido.');
        // HLS AES-128 con clave pública es compatible; no se gestionan licencias DRM.
        foreach(preg_split('/\r?\n/',$texto)as$linea)if(preg_match('/^#EXT-X-(?:SESSION-)?KEY:/',$linea)){abort_unless(preg_match('/METHOD=(?:NONE|AES-128)(?:,|$)/',$linea),502,'La señal requiere un método de acceso no compatible.');if(preg_match('/KEYFORMAT="([^"]+)"/',$linea,$m))abort_unless($m[1]==='identity',502,'Esta señal requiere una licencia externa.');}
        $reescribir=function(string $valor)use($base,$cuenta,$live){$url=(string)UriResolver::resolve(new Uri($base),new Uri($valor));return route('admin.tiktok.video.recurso',[$cuenta,$live,'t'=>$this->token($url,$live)]);};
        $lineas=preg_split('/\r?\n/',$texto);
        foreach($lineas as&$linea){$valor=trim($linea);if($valor==='' )continue;
            if(str_starts_with($valor,'#'))$linea=preg_replace_callback('/\bURI="([^"\r\n]+)"/',fn($m)=>'URI="'.$reescribir($m[1]).'"',$linea);
            else $linea=$reescribir($valor);
        }unset($linea);return implode("\n",$lineas);
    }
    public function servir(TikTokCuenta $cuenta,TikTokLive $live,?string $token,?string $rango)
    {
        abort_unless($this->activo($cuenta,$live),410,'Este LIVE no tiene una captura activa.');
        abort_if($rango!==null&&!preg_match('/^bytes=\d{1,15}-\d{0,15}$/D',$rango),416);
        $raiz=$token===null;
        if($raiz){$url=$this->fuente($live);abort_unless($url,404,'No hay una señal pública compatible.');}
        else{
            try{$d=json_decode(Crypt::decryptString($token),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(403);}
            abort_unless(is_array($d)&&($d['live']??null)===$live->id&&is_int($d['exp']??null)&&$d['exp']>=time()&&$d['exp']<=time()+1200&&is_string($d['url']??null),403);
            $url=$d['url'];
        }
        [$bytes,$tipo,$status,$contentRange,$url]=$this->contenido($url,$rango);
        $esManifiesto=$raiz||str_starts_with(ltrim($bytes),'#EXTM3U');
        if($esManifiesto){$bytes=$this->manifiesto($bytes,$url,$cuenta,$live);$tipo='application/vnd.apple.mpegurl';$status=200;}
        else abort_unless(in_array($tipo,['video/mp2t','video/mp4','audio/mp4','video/x-m4v','application/octet-stream'],true),502,'Formato de video no compatible.');
        $headers=['Content-Type'=>$tipo,'Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff'];
        if(!$esManifiesto&&$status===206&&preg_match('/^bytes \d+-\d+\/(?:\d+|\*)$/D',$contentRange))$headers['Content-Range']=$contentRange;
        return response($bytes,$status,$headers);
    }
}
