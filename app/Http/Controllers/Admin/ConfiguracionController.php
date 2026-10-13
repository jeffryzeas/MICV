<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfiguracionController extends Controller
{
    public function editar()
    {
        $cfg = Configuracion::actual();
        return view('admin.configuracion', [
            'cfg'     => $cfg,
            'cv'      => $cfg->cv(),
            'paletas' => Configuracion::PALETAS_PRO,
        ]);
    }

    public function actualizar(Request $request)
    {
        $cfg = Configuracion::actual();
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $request->validate([
            'escudo'           => ['nullable', 'image', 'max:4096'],

            'cv.diseno_defecto'       => ['required', Rule::in(['clasico', 'pro'])],
            'cv.pro_paleta'           => ['required', Rule::in(array_keys(Configuracion::PALETAS_PRO))],
            'cv.nombre_formato'       => ['required', Rule::in(['mayusculas', 'capital', 'tal_cual'])],
            'cv.fuente_titulo_tam'    => ['required', 'numeric', 'between:20,80'],
            'cv.fuente_seccion_tam'   => ['required', 'numeric', 'between:8,30'],
            'cv.fuente_texto_tam'     => ['required', 'numeric', 'between:7,18'],
            'cv.fuente_vineta_tam'    => ['required', 'numeric', 'between:7,18'],
            'cv.etiqueta_ancho_pct'   => ['required', 'integer', 'between:20,60'],
            'cv.color_titulo'         => $hex,
            'cv.color_seccion'        => $hex,
            'cv.color_texto'          => $hex,
            'cv.foto_ancho'           => ['required', 'numeric', 'between:50,200'],
            'cv.foto_alto'            => ['required', 'numeric', 'between:50,250'],
            'cv.foto_borde'           => ['required', 'numeric', 'between:0,8'],
            'cv.escudo_tam'           => ['required', 'numeric', 'between:20,100'],
            'cv.marca_agua_ancho_pct' => ['required', 'integer', 'between:10,100'],
            'cv.marca_agua_opacidad'  => ['required', 'integer', 'between:1,60'],
            'cv.marco_margen'         => ['required', 'numeric', 'between:0,40'],
            'cv.marco_externo_grosor' => ['required', 'numeric', 'between:0,12'],
            'cv.marco_externo_color'  => $hex,
            'cv.marco_separacion'     => ['required', 'numeric', 'between:0,20'],
            'cv.marco_interno_grosor' => ['required', 'numeric', 'between:0,8'],
            'cv.marco_interno_color'  => $hex,
        ]);

        $cv = $cfg->cv();
        foreach ($request->input('cv', []) as $k => $v) {
            if (array_key_exists($k, Configuracion::CV_DEFECTOS) && !is_bool(Configuracion::CV_DEFECTOS[$k])) {
                $cv[$k] = is_numeric($v) ? $v + 0 : $v;
            }
        }
        // Casillas (si no vienen marcadas = false)
        foreach (Configuracion::CV_DEFECTOS as $k => $def) {
            if (is_bool($def)) {
                $cv[$k] = $request->boolean("cv.$k");
            }
        }

        $datos = ['cv_config' => $cv];

        if ($request->hasFile('escudo')) {
            Archivos::borrar($cfg->escudo);
            $datos['escudo'] = Archivos::guardarImagen($request->file('escudo'), 'marca', 700, true);
        } elseif ($request->boolean('quitar_escudo')) {
            Archivos::borrar($cfg->escudo);
            $datos['escudo'] = null;
        }

        $cfg->update($datos);
        Actividad::registrar('configuracion');

        return back()->with('mensaje', 'Configuración guardada.');
    }

    public function restablecerCv()
    {
        $cfg = Configuracion::actual();
        $cfg->update(['cv_config' => Configuracion::CV_DEFECTOS]);
        return back()->with('mensaje', 'Medidas del CV restablecidas a los valores de fábrica.');
    }
}
