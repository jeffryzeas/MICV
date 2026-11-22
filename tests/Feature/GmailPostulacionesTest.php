<?php
namespace Tests\Feature;

use App\Models\{GmailCuenta, Postulacion};
use App\Services\GmailPostulaciones;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\{Envelope, SentMessage};
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\{Email, RawMessage};
use Tests\TestCase;

class GmailPostulacionesTest extends TestCase
{
    public function test_mime_contiene_remitente_real_texto_html_y_los_bytes_exactos_del_pdf(): void
    {
        Storage::fake('local');$bytes='%PDF-1.4 copia inmutable';Storage::disk('local')->put('postulaciones/prueba/cv.pdf',$bytes);
        $t=new class('smtp.gmail.com',587) extends EsmtpTransport {
            public ?Email $mensaje=null;
            public function send(RawMessage $message, ?Envelope $envelope=null): ?SentMessage
            {
                $this->mensaje=$message;
                return new SentMessage($message,$envelope ?? Envelope::create($message));
            }
            public function stop(): void {}
            public function start(): void {}
        };
        $gmail=new class($t) extends GmailPostulaciones {
            public function __construct(private EsmtpTransport $t){}
            protected function transporte(GmailCuenta $cuenta): EsmtpTransport { return $this->t; }
        };
        $p=new Postulacion(['remitente'=>'ana@gmail.com','remitente_nombre'=>'Ana Pérez','destinatario'=>'rrhh@example.test','asunto'=>'Postulación laboral','mensaje'=>'Estimado equipo','correo_html'=>'<p>Estimado equipo</p>','cv_ruta'=>'postulaciones/prueba/cv.pdf','cv_nombre'=>'CV_Ana.pdf']);$p->id='cb18b44c-1bc2-4c09-88b4-744ec49df02d';
        $cuenta=new GmailCuenta(['correo'=>'ana@gmail.com','clave'=>'abcdefghijklmnop']);
        $id=$gmail->enviar($p,$cuenta);$m=$t->mensaje;
        $this->assertStringContainsString($p->id,$id);$this->assertSame('ana@gmail.com',$m->getFrom()[0]->getAddress());$this->assertSame('rrhh@example.test',$m->getTo()[0]->getAddress());
        $this->assertSame('ana@gmail.com',$m->getReplyTo()[0]->getAddress());$this->assertSame('Estimado equipo',$m->getTextBody());$this->assertSame('<p>Estimado equipo</p>',$m->getHtmlBody());
        $this->assertCount(1,$m->getAttachments());$this->assertSame($bytes,$m->getAttachments()[0]->getBody());$this->assertSame('CV_Ana.pdf',$m->getAttachments()[0]->getFilename());
        $this->assertStringNotContainsString('abcdefghijklmnop',$m->toString());
        $this->assertSame('array',config('mail.default')); // El transporte global permanece independiente.
    }
}
