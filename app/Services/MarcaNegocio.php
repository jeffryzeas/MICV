<?php

namespace App\Services;

use App\Models\Cyber;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarcaNegocio
{

    public function guardar(Request $request, Cyber $negocio): void
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $datos = $request->validate([
            'color_primario' => $hex,
            'color_secundario' => $hex,
            'telefono' => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:150'],
            'logo' => ['nullable', 'image', 'max:51200'],
        ]);
        unset($datos['logo']);
        $quitar = $request->boolean('quitar_logo');
        $archivo = $request->file('logo');
        $nuevo = null;

        try {
            DB::transaction(function () use ($negocio, $datos, $quitar, $archivo, &$nuevo) {
                $cyber = Cyber::query()->whereKey($negocio->id)->lockForUpdate()->firstOrFail();
                $anterior = $cyber->logo;
                $cyber->fill($datos);
                if ($archivo) {
                    $nuevo = Archivos::guardarImagen($archivo, 'logos', 600, true);
                    $cyber->logo = $nuevo;
                } elseif ($quitar) {
                    $cyber->logo = null;
                }
                $cyber->save();
                if ($anterior !== $cyber->logo) {
                    DB::afterCommit(fn () => rescue(fn () => Archivos::borrar($anterior)));
                }
            });
        } catch (\Throwable $error) {
            Archivos::borrar($nuevo);
            throw $error;
        }
    }
}
