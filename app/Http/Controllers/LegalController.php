<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Configuracion;
use App\Support\Legal;
use Illuminate\Http\Request;

/** Páginas legales públicas y aceptación de nuevas versiones. */
class LegalController extends Controller
{
    public function ver(string $doc)
    {
        abort_unless(array_key_exists($doc, Legal::DOCUMENTOS), 404);
        $cfg = Configuracion::actual();
        return view('legal.ver', [
            'doc' => $doc, 'titulo' => Legal::DOCUMENTOS[$doc][0], 'html' => Legal::html($doc, $cfg), 'cfg' => $cfg,
        ]);
    }

    public function aceptarMostrar(Request $request)
    {
        $cfg = Configuracion::actual();
        return view('legal.aceptar', ['cfg' => $cfg, 'user' => $request->user()]);
    }

    public function aceptarGuardar(Request $request)
    {
        $u = $request->user();
        $reglas = ['acepto' => ['accepted']];
        if ($u->esCyber()) {
            $reglas['contrato'] = ['accepted'];
        }
        $request->validate($reglas, [
            'acepto.accepted' => 'Debe aceptar los términos y la política de privacidad para continuar.',
            'contrato.accepted' => 'Debe aceptar el contrato de servicio del Cyber para continuar.',
        ]);
        $cfg = Configuracion::actual();
        $u->forceFill(['terminos_version' => $cfg->legal_version, 'terminos_aceptados_en' => now()])->save();
        Actividad::registrar('terminos_aceptados', 'versión ' . $cfg->legal_version);

        return redirect()->route('dashboard');
    }
}
