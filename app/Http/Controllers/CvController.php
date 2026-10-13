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
        [$diseno, $paleta, $foto, $idioma] = $this->opciones($request);
        $html = view("cv.$diseno", CvDatos::armar($cliente, $diseno, $paleta, $foto, $request->user(), $idioma) + ['modoPantalla' => true])->render();

        return response($html);
    }

    /** PDF con Chromium. ?descargar=1 lo baja; si no, lo abre en el navegador. */
    public function pdf(Request $request, Cliente $cliente)
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'No tiene acceso a este CV.');
        [$diseno] = $opc = $this->opciones($request);

        try {
            $pdf = $this->generar($request, $cliente, $opc);
        } catch (\Throwable $e) {
            report($e);
            return response()->view('errores.pdf', ['error' => $this->detalleError($e)], 500);
        }

        Actividad::registrar('cv_pdf', "#{$cliente->id} {$cliente->nombreCompleto()} ($diseno, {$opc[3]})");
        $modo = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $modo . '; filename="' . $this->nombreArchivo($cliente, $opc) . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /** CV en Word (.docx) editable. */
    public function word(Request $request, Cliente $cliente)
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403, 'No tiene acceso a este CV.');
        $opc = $this->opciones($request);
        try {
            $docx = \App\Services\CvWord::generar($cliente, $opc[3], $opc[2], $request->user());
        } catch (\Throwable $e) {
            report($e);
            return response()->view('errores.pdf', ['error' => $this->detalleError($e)], 500);
        }
        Actividad::registrar('cv_word', "#{$cliente->id} {$cliente->nombreCompleto()} ({$opc[3]})");
        $nombre = preg_replace('/\.pdf$/', '.docx', str_replace('_' . $opc[0] . '.pdf', '.pdf', $this->nombreArchivo($cliente, $opc)));
        return response($docx, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /** Envía el CV en PDF al correo de la persona (o al de la cuenta del usuario). */
    public function enviar(Request $request, Cliente $cliente)
    {
        $u = $request->user();
        abort_unless($cliente->esVisiblePara($u), 403, 'No tiene acceso a este CV.');
        $para = $u->esCliente() ? $u->email : $cliente->correo;
        if (!$para) {
            return response()->json(['ok' => false, 'mensaje' => 'Esta persona no tiene correo registrado. Agréguelo en sus datos.'], 422);
        }
        if (!\App\Services\Correo::configurado()) {
            return response()->json(['ok' => false, 'mensaje' => 'El correo del sistema todavía no está configurado. Avise al administrador.'], 422);
        }
        $opc = $this->opciones($request);
        try {
            $pdf = $this->generar($request, $cliente, $opc);
            $archivo = $this->nombreArchivo($cliente, $opc);
            \App\Services\Correo::enviar($para, ($opc[3] === 'en' ? 'Su CV en inglés' : 'Su currículum') . ' — ' . $cliente->nombreCompleto(), 'correos.cv', [
                'nombre'  => $cliente->primer_nombre ?: $cliente->nombreCompleto(),
                'idioma'  => $opc[3],
                'archivo' => $archivo,
                'puesto'  => $cliente->profesion,
            ], null, [[$pdf, $archivo, 'application/pdf']]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }
        Actividad::registrar('cv_correo', "#{$cliente->id} → $para ({$opc[0]}, {$opc[3]})");

        return response()->json(['ok' => true, 'mensaje' => 'Listo: enviamos el CV a ' . $this->ocultar($para) . '. Revise también la carpeta de spam.']);
    }

    protected function generar(Request $request, Cliente $cliente, array $opc): string
    {
        [$diseno, $paleta, $foto, $idioma] = $opc;
        $html = view("cv.$diseno", CvDatos::armar($cliente, $diseno, $paleta, $foto, $request->user(), $idioma))->render();
        return CvPdf::desdeHtml($html, CvDatos::esPro($diseno));
    }

    protected function nombreArchivo(Cliente $cliente, array $opc): string
    {
        return ($opc[3] === 'en' ? 'Resume_' : 'CV_') . Str::slug($cliente->nombreCompleto(), '_') . '_' . $opc[0] . '.pdf';
    }

    protected function ocultar(string $correo): string
    {
        [$a, $b] = explode('@', $correo) + [1 => ''];
        return mb_substr($a, 0, 2) . str_repeat('•', max(1, mb_strlen($a) - 2)) . '@' . $b;
    }

    protected function detalleError(\Throwable $e): string
    {
        $detalle = get_class($e) . ': ' . ($e->getMessage() ?: '(sin mensaje)') . "\nArchivo: " . $e->getFile() . ':' . $e->getLine();
        if ($e instanceof \Symfony\Component\Process\Exception\ProcessFailedException) {
            $detalle .= "\n\nSalida de Node/Chrome:\n" . $e->getProcess()->getErrorOutput() . "\n" . $e->getProcess()->getOutput();
        }
        if ($e->getPrevious()) {
            $detalle .= "\n\nCausa: " . get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage();
        }
        return $detalle;
    }

    protected function opciones(Request $request): array
    {
        $cv = Configuracion::actual()->cv();
        $diseno = $request->input('diseno', $cv['diseno_defecto']);
        if (!array_key_exists($diseno, CvDatos::DISENOS)) {
            $diseno = 'pro';
        }
        $paleta = $request->input('paleta');
        if ($paleta && $paleta !== 'cyber' && !array_key_exists($paleta, Configuracion::PALETAS_PRO)) {
            $paleta = null;
        }
        $idioma = $request->input('idioma') === 'en' ? 'en' : 'es';
        return [$diseno, $paleta, $request->input('foto', '1') !== '0', $idioma];
    }
}
