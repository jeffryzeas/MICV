<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarVideoPromocional;
use App\Models\Configuracion;
use App\Services\VideosPromocionales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VideoPromocionalController extends Controller
{
    public function index(Request $request, VideosPromocionales $videos)
    {
        $usuario = (int) $request->user()->getKey();
        $actual = $videos->ultimo($usuario);
        $abierto = (bool) Configuracion::actualSegura()?->registro_abierto;

        return response()->view('admin.video-promocional.index', compact('actual', 'abierto'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, VideosPromocionales $videos)
    {
        if (!Configuracion::actualSegura()?->registro_abierto) {
            throw ValidationException::withMessages(['general' => 'Abra el registro público antes de generar el tutorial.']);
        }

        $usuario = (int) $request->user()->getKey();
        Cache::lock('video-promocional:'.$usuario, 20)->block(3, function () use ($usuario, $videos) {
            $ultimo = $videos->ultimo($usuario);
            if ($ultimo && in_array($ultimo['estado'], ['pendiente', 'procesando'], true)
                && $ultimo['actualizado'] >= now()->subMinutes(20)->getTimestamp()) {
                return;
            }

            $id = (string) Str::uuid();
            $videos->actualizar($usuario, $id, 'pendiente');
            try {
                Bus::dispatch(new GenerarVideoPromocional($usuario, $id));
            } catch (\Throwable $error) {
                report($error);
                $videos->actualizar($usuario, $id, 'error');
                throw ValidationException::withMessages(['general' => 'No se pudo poner la generación en cola.']);
            }
        });

        return redirect()->route('admin.video-promocional.index');
    }

    public function estado(Request $request, VideosPromocionales $videos, string $video)
    {
        $actual = $videos->estado((int) $request->user()->getKey(), $video);
        abort_unless($actual, 404);
        if ($actual['estado'] === 'listo') {
            $actual['vista'] = route('admin.video-promocional.archivo', $video);
            $actual['descarga'] = route('admin.video-promocional.archivo', [$video, 'descargar' => 1]);
        }

        return response()->json($actual)->header('Cache-Control', 'private, no-store');
    }

    public function archivo(Request $request, VideosPromocionales $videos, string $video)
    {
        $usuario = (int) $request->user()->getKey();
        abort_unless(($videos->estado($usuario, $video)['estado'] ?? null) === 'listo', 404);
        $ruta = $videos->carpeta($usuario, $video).'/video.mp4';
        abort_unless(is_file($ruta), 404);
        $cabeceras = ['Cache-Control' => 'private, no-store', 'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff'];

        return $request->boolean('descargar')
            ? response()->download($ruta, 'Mi-CV-Tutorial.mp4', $cabeceras)
            : response()->file($ruta, $cabeceras);
    }
}
