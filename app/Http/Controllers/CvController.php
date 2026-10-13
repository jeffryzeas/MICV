<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Services\CvDatos;
use App\Services\CvPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** El CV nunca se guarda: se genera al instante cada vez. */
class CvController extends Controller
{
    /** Vista previa en HTML (exactamente lo que va al PDF). */
    public function vista(Request $request, Cliente $cliente)
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'No tiene acceso a este CV.');
        [$diseno, $paleta, $foto] = $this->opciones($request);
        $html = view("cv.$diseno", CvDatos::armar($cliente, $diseno, $paleta, $foto, $request->user()) + ['modoPantalla' => true])->render();

        return response($html);
    }

    /** PDF con Chromium. ?descargar=1 lo baja; si no, lo abre en el navegador. */
    public function pdf(Request $request, Cliente $cliente)
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'No tiene acceso a este CV.');
        [$diseno, $paleta, $foto] = $this->opciones($request);
        $html = view("cv.$diseno", CvDatos::armar($cliente, $diseno, $paleta, $foto, $request->user()))->render();

        try {
            $pdf = CvPdf::desdeHtml($html, CvDatos::esPro($diseno));
        } catch (\Throwable $e) {
            report($e);
            $detalle = get_class($e) . ': ' . ($e->getMessage() ?: '(sin mensaje)')
                . "\nArchivo: " . $e->getFile() . ':' . $e->getLine();
            if ($e instanceof \Symfony\Component\Process\Exception\ProcessFailedException) {
                $detalle .= "\n\nSalida de Node/Chrome:\n" . $e->getProcess()->getErrorOutput() . "\n" . $e->getProcess()->getOutput();
            }
            if ($e->getPrevious()) {
                $detalle .= "\n\nCausa: " . get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage();
            }
            return response()->view('errores.pdf', ['error' => $detalle], 500);
        }

        Actividad::registrar('cv_pdf', "#{$cliente->id} {$cliente->nombreCompleto()} ($diseno)");

        $nombre = 'CV_' . Str::slug($cliente->nombreCompleto(), '_') . '_' . $diseno . '.pdf';
        $modo = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $modo . '; filename="' . $nombre . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    protected function opciones(Request $request): array
    {
        $cv = Configuracion::actual()->cv();
        $diseno = $request->query('diseno', $cv['diseno_defecto']);
        if (!array_key_exists($diseno, CvDatos::DISENOS)) {
            $diseno = 'pro';
        }
        $paleta = $request->query('paleta');
        if ($paleta && $paleta !== 'cyber' && !array_key_exists($paleta, Configuracion::PALETAS_PRO)) {
            $paleta = null;
        }
        return [$diseno, $paleta, $request->query('foto', '1') !== '0'];
    }
}
