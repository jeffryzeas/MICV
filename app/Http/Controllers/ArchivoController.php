<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Cyber;
use App\Support\Archivos;
use Illuminate\Support\Facades\Storage;

/** Sirve imágenes guardadas en storage privado (sin storage:link). */
class ArchivoController extends Controller
{
    /** Logo del negocio: público (se ve en el login). */
    public function logo()
    {
        $cfg = Configuracion::actualSegura();
        abort_unless($cfg && Archivos::existe($cfg->logo), 404);
        return $this->servir($cfg->logo);
    }

    /** Logo de un Cyber: público (se ve en el menú y en el CV). */
    public function logoCyber(Cyber $cyber)
    {
        abort_unless(Archivos::existe($cyber->logo), 404);
        return $this->servir($cyber->logo);
    }

    /** Foto de un cliente: solo con sesión y si el cliente es de su Cyber. */
    public function fotoCliente(\Illuminate\Http\Request $request, Cliente $cliente)
    {
        abort_unless($cliente->esVisiblePara($request->user()), 403);
        abort_unless($cliente->tieneFoto(), 404);
        return $this->servir($cliente->foto);
    }

    protected function servir(string $ruta)
    {
        $bin = Storage::disk(Archivos::DISCO)->get($ruta);
        return response($bin, 200, [
            'Content-Type'  => Archivos::mime($ruta, $bin),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
