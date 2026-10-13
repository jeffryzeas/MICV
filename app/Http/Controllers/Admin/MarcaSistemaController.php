<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Support\Archivos;
use Illuminate\Http\Request;

/** Marca del sistema: nombre, logo, colores, contacto, redes y app Android. */
class MarcaSistemaController extends Controller
{
    public function editar()
    {
        return view('admin.marca', ['cfg' => Configuracion::actual()]);
    }

    public function actualizar(Request $request)
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $url = ['nullable', 'url', 'max:200'];
        $d = $request->validate([
            'nombre_negocio'   => ['required', 'string', 'max:120'],
            'eslogan'          => ['nullable', 'string', 'max:160'],
            'color_primario'   => $hex,
            'color_secundario' => $hex,
            'logo'             => ['nullable', 'image', 'max:4096'],
            'marca_telefono'   => ['nullable', 'string', 'max:40'],
            'marca_whatsapp'   => ['nullable', 'string', 'max:40'],
            'marca_correo'     => ['nullable', 'email', 'max:150'],
            'marca_direccion'  => ['nullable', 'string', 'max:255'],
            'marca_sitio_web'  => $url,
            'marca_facebook'   => $url,
            'marca_instagram'  => $url,
            'marca_tiktok'     => $url,
            'pie_pagina'       => ['nullable', 'string', 'max:255'],
            'android_paquete'  => ['nullable', 'string', 'max:150', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/'],
            'android_sha256'   => ['nullable', 'string', 'max:2000'],
        ], ['android_paquete.regex' => 'El nombre del paquete debe ser como com.micv.app'], ['nombre_negocio' => 'nombre del sistema']);

        $cfg = Configuracion::actual();
        unset($d['logo']);
        $cfg->update($d);

        if ($request->hasFile('logo')) {
            Archivos::borrar($cfg->logo);
            $cfg->update(['logo' => Archivos::guardarImagen($request->file('logo'), 'marca', 600, true)]);
        } elseif ($request->boolean('quitar_logo')) {
            Archivos::borrar($cfg->logo);
            $cfg->update(['logo' => null]);
        }
        Actividad::registrar('marca_sistema');

        return back()->with('mensaje', 'Marca del sistema guardada.');
    }
}
