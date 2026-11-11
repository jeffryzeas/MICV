<?php

namespace App\Services;

use App\Models\Cyber;
use App\Services\Desktop\{FullClientSync, LocalBusinessJournal};
use App\Support\Archivos;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Validator};

class MarcaNegocio
{
    private const CAMPOS = ['color_primario', 'color_secundario', 'telefono', 'direccion', 'correo'];

    public function guardar(Request $request, Cyber $negocio, ?string $revision = null): void
    {
        $local = $request->attributes->has('local_permissions');
        if ($local) {
            abort_unless($negocio->id === (int) getenv('MICV_LOCAL_TENANT'), 403,
                'Abra el espacio de este negocio para modificar su marca.');
        }
        $desktop = $local || $revision !== null;
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $datos = $request->validate([
            'color_primario' => $hex,
            'color_secundario' => $hex,
            'telefono' => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:150'],
            'logo' => $desktop
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:600']
                : ['nullable', 'image', 'max:51200'],
        ]);
        unset($datos['logo']);
        $quitar = $request->boolean('quitar_logo');
        $archivo = $request->file('logo');
        $nuevo = null;

        try {
            DB::transaction(function () use ($request, $negocio, $revision, $datos, $local, $quitar, $archivo, &$nuevo) {
                $cyber = Cyber::query()->whereKey($negocio->id)->lockForUpdate()->firstOrFail();
                $base = $local || $revision !== null ? self::revision($cyber) : null;
                abort_if($revision !== null && !hash_equals($base, $revision), 409,
                    'La marca del negocio cambió en la web. Revise los valores antes de sincronizar.');

                $anterior = $cyber->logo;
                $cyber->fill($datos);
                if ($archivo) {
                    $nuevo = Archivos::guardarImagen($archivo, 'logos', 600, true);
                    $cyber->logo = $nuevo;
                } elseif ($quitar) {
                    $cyber->logo = null;
                }
                $cyber->save();
                if ($local && $base !== self::revision($cyber)) {
                    $payload = ['revision' => $base, 'marca' => $datos + ['quitar_logo' => $quitar]];
                    if ($archivo) {
                        $payload['logo'] = base64_encode($archivo->getContent());
                    }
                    LocalBusinessJournal::record($request, $cyber->id, 'brand.update', $cyber->id, $payload);
                }
                if ($anterior !== $cyber->logo) {
                    DB::afterCommit(fn () => rescue(fn () => Archivos::borrar($anterior)));
                }
            });
        } catch (\Throwable $error) {
            Archivos::borrar($nuevo);
            throw $error;
        }
    }

    public function sincronizar(Cyber $negocio, array $payload): void
    {
        $datos = Validator::make($payload, [
            'revision' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'marca' => ['required', 'array'],
            'logo' => ['nullable', 'string', 'max:819200'],
        ])->validate();
        $request = Request::create('/desktop/marca', 'PUT', $datos['marca']);
        $temporary = null;

        try {
            if (isset($datos['logo'])) {
                $bytes = base64_decode($datos['logo'], true);
                abort_unless($bytes !== false && $bytes !== '' && strlen($bytes) <= 600 * 1024, 422, 'El logo no es válido.');
                $temporary = tempnam(sys_get_temp_dir(), 'micv-brand-');
                abort_unless($temporary && file_put_contents($temporary, $bytes) === strlen($bytes), 503);
                $request->files->set('logo', new UploadedFile($temporary, 'logo', test: true));
            }
            $this->guardar($request, $negocio, $datos['revision']);
        } finally {
            if ($temporary) {
                @unlink($temporary);
            }
        }
    }

    private static function revision(Cyber $cyber): string
    {
        $logo = Archivos::dataUri($cyber->logo);
        return FullClientSync::fingerprint($cyber->only(self::CAMPOS) + [
            'logo' => $logo === null ? null : hash('sha256', $logo),
        ]);
    }
}
