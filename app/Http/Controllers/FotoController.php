<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Services\CvPdf;
use App\Services\QuitarFondo;
use App\Support\Archivos;
use App\Support\FotoFormatos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fotos carné y pasaporte: quitar el fondo, recortar a la medida y armar la
 * hoja (carta, A4 o papel fotográfico 10×15) con marcas de corte.
 */
class FotoController extends Controller
{
    public function index(Request $request)
    {
        $cliente = null;
        if ($request->filled('cliente')) {
            $cliente = Cliente::find($request->integer('cliente'));
            if ($cliente && !$cliente->esVisiblePara($request->user())) {
                $cliente = null;
            }
        }
        return view('fotos.index', [
            'cliente'       => $cliente,
            'fondoListo'    => QuitarFondo::disponible(),
            'configJs'      => FotoFormatos::paraJs(),
        ]);
    }

    /** Recibe la foto y devuelve la misma foto sin fondo (PNG en data URI). */
    public function procesar(Request $request)
    {
        $request->validate([
            'foto'  => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'color' => ['nullable', 'string', 'max:20'],
        ], ['foto.max' => 'La foto pesa más de 12 MB.']);
        $color = (string) $request->input('color', '#ffffff');
        try {
            $png = QuitarFondo::procesar(file_get_contents($request->file('foto')->getRealPath()), $color);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'mensaje' => QuitarFondo::disponible()
                ? 'No se pudo quitar el fondo de esta foto. Pruebe con otra o use "Dejar el fondo original".'
                : $e->getMessage()], 422);
        }
        Actividad::registrar('foto_fondo', null);
        return response()->json(['ok' => true, 'imagen' => 'data:image/png;base64,' . base64_encode($png)]);
    }

    /** Hoja lista para imprimir (PDF). */
    public function hoja(Request $request)
    {
        $d = $request->validate([
            'imagen'   => ['required', 'string'],
            'formato'  => ['required', Rule::in(array_keys(FotoFormatos::FORMATOS))],
            'papel'    => ['required', Rule::in(array_keys(FotoFormatos::PAPELES))],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:200'],
            'ancho'    => ['nullable', 'numeric', 'min:15', 'max:100'],
            'alto'     => ['nullable', 'numeric', 'min:15', 'max:120'],
            'marcas'   => ['nullable', 'boolean'],
        ]);
        $img = Archivos::desdeDataUri($d['imagen']);
        if (!$img) {
            return response()->json(['ok' => false, 'mensaje' => 'La foto no llegó bien. Vuelva a recortarla.'], 422);
        }
        [$w, $h] = FotoFormatos::medida($d['formato'], $d['ancho'] ?? null, $d['alto'] ?? null);
        $acomodo = FotoFormatos::acomodo($w, $h, $d['papel']);
        if (!$acomodo['maximo']) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa medida no cabe en ese papel.'], 422);
        }
        $pos = array_slice($acomodo['posiciones'], 0, min($acomodo['maximo'], (int) ($d['cantidad'] ?? $acomodo['maximo'])));
        [, $pw, $ph] = FotoFormatos::PAPELES[$d['papel']];

        $html = view('fotos.hoja', [
            'imagen' => 'data:image/' . ($img[1] === 'jpg' ? 'jpeg' : $img[1]) . ';base64,' . base64_encode($img[0]),
            'w' => $w, 'h' => $h, 'pw' => $pw, 'ph' => $ph, 'pos' => $pos,
            'marcas' => $request->boolean('marcas', true),
        ])->render();
        $pdf = CvPdf::pdfTamano($html, $pw, $ph);
        Actividad::registrar('foto_hoja', count($pos) . ' × ' . FotoFormatos::FORMATOS[$d['formato']][0]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Fotos_' . $d['formato'] . '_' . count($pos) . '.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Pone la foto recortada como foto del CV del cliente. */
    public function usarEnCv(Request $request, Cliente $cliente)
    {
        abort_if($request->user()->esCliente() && !\App\Support\EdicionCliente::puede($request->user(),'foto'),403);
        abort_unless($cliente->esVisiblePara($request->user()), 403);
        $img = Archivos::desdeDataUri($request->input('imagen'));
        if (!$img) {
            return response()->json(['ok' => false, 'mensaje' => 'La foto no llegó bien.'], 422);
        }
        $anterior = $cliente->foto;
        $cliente->foto = Archivos::guardarBinario($img[0], 'fotos', $img[1]);
        $cliente->save();
        \Illuminate\Support\Facades\DB::afterCommit(fn () => Archivos::borrar($anterior));
        Actividad::registrar('foto_cv', '#' . $cliente->id);
        return response()->json(['ok' => true, 'mensaje' => 'Listo: es la nueva foto del CV de ' . $cliente->nombreCompleto() . '.']);
    }
}
