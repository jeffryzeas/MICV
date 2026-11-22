<?php
namespace App\Jobs;

use App\Models\{GmailCuenta, Postulacion, User};
use App\Services\GmailPostulaciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Cache, Storage};

class EnviarPostulacion implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=90;
    public bool $failOnTimeout=true;
    public function __construct(public string $id)
    {
        $this->onConnection('postulaciones')->onQueue('postulaciones');
    }
    public function handle(GmailPostulaciones $gmail): void
    {
        Cache::put('postulaciones:worker', now()->toIso8601String(), 300);
        // Reclamo atómico: dos clics, dos trabajadores o una redespachada nunca duplican el correo.
        if (!Postulacion::whereKey($this->id)->where('estado','en_cola')->update(['estado'=>'enviando','iniciado_en'=>now()])) return;
        $p=Postulacion::find($this->id);
        if (!$p) return;
        try {
            $cuenta=$p->cuenta; $actor=User::find($p->confirmado_por);
            if (!$actor?->activo || !($actor->esAdmin() || ((int)$actor->id===(int)$p->user_id && ($actor->esCliente() || $actor->tienePermiso('cv')))) || !$p->cliente?->activo || !$p->cliente?->esVisiblePara($actor)) {
                $this->fallo('La cuenta que confirmó el envío ya no tiene acceso a este CV.');return;
            }
            if (!$cuenta?->lista() || !$cuenta->user?->activo || $cuenta->revision!==$p->cuenta_revision || $cuenta->correo!==$p->remitente) {
                $this->fallo('La cuenta de Gmail fue modificada o desconectada. Prepare un nuevo envío después de comprobar la conexión.');return;
            }
            if (!Storage::disk('local')->exists($p->cv_ruta) || hash_file('sha256',Storage::disk('local')->path($p->cv_ruta))!==$p->cv_sha256) {
                $this->fallo('No se encontró la versión original del CV o el archivo cambió. No se envió el correo.');return;
            }
        } catch (\Throwable) { $this->fallo('No se pudo preparar el archivo o leer la cuenta configurada. No se envió el correo.');return; }
        try {
            $id=$gmail->enviar($p,$cuenta);
            Postulacion::whereKey($p->id)->where('estado','enviando')->update(['estado'=>'enviado','enviado_en'=>now(),'message_id'=>$id,'detalle_estado'=>null]);
        } catch (\Throwable) {
            // SMTP puede aceptar DATA antes de que se corte la conexión. Nunca reintentar a ciegas.
            Postulacion::whereKey($p->id)->where('estado','enviando')->update(['estado'=>'incierto','detalle_estado'=>'Gmail no devolvió una confirmación completa. Revise la carpeta Enviados antes de preparar otro envío.']);
        }
    }
    private function fallo(string $detalle): void { Postulacion::whereKey($this->id)->where('estado','enviando')->update(['estado'=>'fallido','detalle_estado'=>$detalle]); }
    public function failed(?\Throwable $e): void
    {
        Postulacion::whereKey($this->id)->where('estado','enviando')->update(['estado'=>'incierto','detalle_estado'=>'El trabajador se interrumpió durante el envío. Revise Enviados de Gmail antes de volver a enviar.']);
        Postulacion::whereKey($this->id)->where('estado','en_cola')->update(['estado'=>'fallido','detalle_estado'=>'El trabajador no pudo iniciar el envío. Prepare un nuevo borrador.']);
    }
}
