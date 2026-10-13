<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Support\Legal;
use App\Support\Pais;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LegalAdminController extends Controller
{
    public function editar()
    {
        $cfg = Configuracion::actual();
        $textos = [];
        foreach (Legal::DOCUMENTOS as $k => [, $campo]) {
            $textos[$k] = $cfg->{$campo} ?: Legal::base($k);
        }
        return view('admin.legal', ['cfg' => $cfg, 'textos' => $textos]);
    }

    public function actualizar(Request $request)
    {
        $d = $request->validate([
            'legal_responsable'    => ['required', 'string', 'max:160'],
            'legal_identificacion' => ['nullable', 'string', 'max:60'],
            'legal_pais'           => ['required', Rule::in(Pais::codigos())],
            'textos'               => ['required', 'array'],
            'textos.*'             => ['required', 'string', 'max:60000'],
        ], [], ['legal_responsable' => 'responsable', 'textos.*' => 'texto']);

        $cfg = Configuracion::actual();
        $datos = collect($d)->only(['legal_responsable', 'legal_identificacion', 'legal_pais'])->all();
        foreach (Legal::DOCUMENTOS as $k => [, $campo]) {
            $nuevo = str_replace("\r\n", "\n", $d['textos'][$k] ?? '');
            // Si es idéntico al de fábrica se guarda vacío (así recibe mejoras futuras).
            $datos[$campo] = trim($nuevo) === trim(Legal::base($k)) ? null : $nuevo;
        }

        if ($request->boolean('nueva_version')) {
            $partes = explode('.', $cfg->legal_version ?: '1.0');
            $datos['legal_version'] = ($partes[0] ?? 1) . '.' . ((int) ($partes[1] ?? 0) + 1);
            $datos['legal_actualizado_en'] = now();
        } elseif (!$cfg->legal_actualizado_en) {
            $datos['legal_actualizado_en'] = now();
        }

        $cfg->update($datos);
        Actividad::registrar('legal_actualizar', 'versión ' . ($datos['legal_version'] ?? $cfg->legal_version));

        return back()->with('mensaje', $request->boolean('nueva_version')
            ? 'Textos publicados como versión ' . $datos['legal_version'] . '. Los usuarios deberán aceptarla al entrar.'
            : 'Textos legales guardados.');
    }
}
