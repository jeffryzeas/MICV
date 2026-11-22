<?php
namespace App\Services;

use App\Models\{GmailCuenta, Postulacion};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\{Address, Email};

/** Transporte independiente por envío: nunca cambia el correo global del sistema. */
class GmailPostulaciones
{
    protected function transporte(GmailCuenta $cuenta): EsmtpTransport
    {
        $t = new EsmtpTransport('smtp.gmail.com', 587);
        $t->setAutoTls(true)->setRequireTls(true);
        $t->setUsername($cuenta->correo)->setPassword($cuenta->clave);
        $t->getStream()->setTimeout(20);
        return $t;
    }
    public function comprobar(GmailCuenta $cuenta): void
    {
        $t = $this->transporte($cuenta);
        try { $t->start(); } finally { $t->stop(); }
    }
    public function enviar(Postulacion $p, GmailCuenta $cuenta): string
    {
        $email = (new Email())->from(new Address($p->remitente, $p->remitente_nombre))
            ->replyTo(new Address($p->remitente, $p->remitente_nombre))
            ->to($p->destinatario)->subject($p->asunto)->text($p->mensaje)
            ->html($p->correo_html)
            ->attachFromPath(Storage::disk('local')->path($p->cv_ruta), $p->cv_nombre, 'application/pdf');
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'micv.local';
        $email->getHeaders()->addIdHeader('Message-ID', $p->id.'@'.$domain);
        $t = $this->transporte($cuenta);
        try {
            $enviado = $t->send($email);
            if (!$enviado) throw new \RuntimeException('No hay confirmación del transporte.');
            return $enviado->getMessageId();
        } finally { try { $t->stop(); } catch (\Throwable) {} }
    }
}
