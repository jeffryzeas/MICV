<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use RuntimeException;

class VideoPromocional
{
    public const COLA = 'video-promocional';
    public const ORIGEN_CAPTURA = 'http://micv-video.test';

    public function generar(string $salida): void
    {
        $carpeta = dirname($salida).'/render';
        File::ensureDirectoryExists($carpeta, 0700);

        try {
            $datos = $this->prepararEscenas($carpeta);
            Nodo::correr(resource_path('video-promocional/generar.cjs'), [], 540, json_encode(
                $datos + [
                    'carpeta' => $carpeta,
                    'publico' => public_path(),
                    'salida' => $salida,
                    'chrome' => config('micv.pdf.chrome_path'),
                    'sinSandbox' => config('micv.pdf.no_sandbox'),
                    'ffmpeg' => config('micv.video.ffmpeg'),
                    'espeak' => config('micv.video.espeak'),
                    'piper' => config('micv.video.piper'),
                    'modeloVoz' => config('micv.video.modelo_voz'),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ));

            if (!is_file($salida) || filesize($salida) < 1024) {
                throw new RuntimeException('La generación no produjo un archivo MP4.');
            }
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    private function prepararEscenas(string $carpeta): array
    {
        $configuracion = Configuracion::actual();
        if (!$configuracion->registro_abierto) {
            throw new RuntimeException('El registro público está cerrado.');
        }

        // La demostración usa una sesión en memoria y modelos sin persistir.
        $anterior = app('request');
        $request = Request::create(self::ORIGEN_CAPTURA);
        $sesionDriver = config('session.driver');
        $sesion = app('session')->driver('array');
        $sesion->flush();
        $sesion->start();
        $sesion->regenerateToken();
        $request->setLaravelSession($sesion);
        $sesionAnterior = app()->bound('session.store') ? app('session.store') : null;
        $guard = Auth::guard();
        $usuarioAnterior = $guard->user();

        config(['session.driver' => 'array']);
        app()->instance('request', $request);
        app()->instance('session.store', $sesion);
        URL::useAssetOrigin(self::ORIGEN_CAPTURA);
        $guard->forgetUser();
        $erroresAnteriores = view()->shared('errors');
        view()->share('errors', new ViewErrorBag);

        try {
            $cliente = CvEjemplo::cliente();
            $cliente->forceFill([
                'id' => 0,
                'primer_nombre' => 'Valeria',
                'segundo_nombre' => null,
                'primer_apellido' => 'Mendoza',
                'segundo_apellido' => 'Ríos',
                'correo' => 'valeria.cv@example.com',
                'telefono' => null,
                'direccion' => 'El Tuma–La Dalia, Matagalpa',
                'pais_residencia' => 'NI',
                'profesion' => 'Asistente administrativa',
                'perfil_profesional' => 'Organizo documentos, doy seguimiento a solicitudes y preparo reportes.',
            ]);
            // Los datos de contacto del ejemplo también se omiten de la plantilla final.
            $cliente->referencias->each(fn ($referencia) => $referencia->forceFill(['telefono' => null, 'correo' => null]));

            $url = rtrim((string) config('app.url'), '/');
            $dominio = parse_url($url, PHP_URL_HOST) ?: 'micv.sistemajz.com';
            $dominioHablado = str_replace(['micv', 'sistemajz', '.'], ['mi ce uve', 'sistema jota zeta', ' punto '], $dominio);
            $logo = Archivos::dataUri($configuracion->logo);

            $this->guardarEscena($carpeta, 'inicio', 'auth.login');
            $this->guardarEscena($carpeta, 'registro', 'auth.registro');
            $this->guardarEscena($carpeta, 'codigo', 'auth.registro-codigo', [
                'p' => (object) ['email' => $cliente->correo],
                'espera' => 0,
            ]);

            $demostracion = new User([
                'name' => $cliente->nombreCompleto(),
                'email' => $cliente->correo,
                'rol' => 'cliente',
                'activo' => true,
            ]);
            $demostracion->forceFill(['id' => 0, 'email_verified_at' => now()]);
            $demostracion->setRelation('cyber', null);
            $demostracion->setRelation('cliente', $cliente);
            $guard->setUser($demostracion);

            $this->guardarEscena($carpeta, 'datos', 'mi-cv.datos', ['cliente' => $cliente]);
            $cv = $configuracion->cv();
            $cv['diseno_defecto'] = 'esencial';
            $this->guardarEscena($carpeta, 'panel', 'mi-cv.panel', [
                'cliente' => $cliente,
                'avance' => $cliente->porcentajeCompleto(),
                'pendientes' => $cliente->pendientesCv(),
                'cv' => $cv,
                'paletas' => Configuracion::PALETAS_PRO,
            ]);
            $datosCv = CvDatos::armar(
                $cliente,
                'esencial',
                conFoto: false,
                u: $demostracion,
            );
            $this->guardarEscena($carpeta, 'curriculum', 'cv.esencial', $datosCv);
            $this->guardarEscena($carpeta, 'vista-curriculum', 'cv.esencial', $datosCv + ['modoPantalla' => true]);

            return [
                'nombre' => $configuracion->nombre_negocio ?: 'Mi CV',
                'dominio' => $dominio,
                'logo' => $logo,
                'primario' => $configuracion->color_primario,
                'secundario' => $configuracion->color_secundario,
                'origen' => self::ORIGEN_CAPTURA,
                'escenas' => [
                    ['archivo' => 'inicio', 'modo' => 'inicio', 'titulo' => 'Su CV empieza aquí', 'subtitulo' => 'Abra el sitio y toque Crear mi cuenta gratis.', 'voz' => "¿Necesita un currículum? Entre a {$dominioHablado} y toque Crear mi cuenta gratis.", 'caption' => "¿Necesita un currículum? Entre a {$dominio} y toque Crear mi cuenta gratis."],
                    ['archivo' => 'registro', 'modo' => 'registro', 'titulo' => 'Cree su cuenta', 'subtitulo' => 'Elija su país, escriba su nombre y su correo.', 'voz' => 'Elija el país de su documento. Escriba su nombre, su correo y una contraseña.'],
                    ['archivo' => 'registro', 'modo' => 'crear', 'titulo' => 'Confirme el registro', 'subtitulo' => 'Lea los términos y toque Crear cuenta.', 'voz' => 'Lea y acepte los términos. Luego toque Crear cuenta.'],
                    ['archivo' => 'codigo', 'modo' => 'codigo', 'titulo' => 'Verifique su correo', 'subtitulo' => 'Ingrese el código de 6 dígitos que recibió.', 'voz' => 'Revise su correo e ingrese el código de seis dígitos. Con ese paso se crea su cuenta.'],
                    ['archivo' => 'datos', 'modo' => 'datos', 'titulo' => 'Complete Mis datos', 'subtitulo' => 'Explique en unas líneas qué sabe hacer.', 'voz' => 'En Mis datos, escriba un perfil breve sobre lo que sabe hacer.'],
                    ['archivo' => 'datos', 'modo' => 'estudios', 'titulo' => 'Agregue sus estudios', 'subtitulo' => 'Incluya su formación y los cursos relevantes.', 'voz' => 'Agregue sus estudios, el centro donde estudió y los cursos más relevantes.'],
                    ['archivo' => 'datos', 'modo' => 'experiencia', 'titulo' => 'Añada su experiencia', 'subtitulo' => 'Indique su cargo y sus tareas. Luego, guarde.', 'voz' => 'Describa su experiencia y toque Guardar para aplicar los cambios.'],
                    ['archivo' => 'panel', 'modo' => 'galeria', 'titulo' => 'Elija un diseño', 'subtitulo' => 'Abra la galería, compare las vistas previas y seleccione su plantilla.', 'voz' => 'Regrese a Mi panel, abra la galería de diseños y elija la plantilla que mejor presente su experiencia.'],
                    ['archivo' => 'panel', 'modo' => 'descarga', 'titulo' => 'Descargue su PDF', 'subtitulo' => 'Revise la vista previa y toque Descargar PDF.', 'voz' => 'Revise la vista previa y toque Descargar PDF. Así tendrá su currículum listo para enviar.'],
                    ['archivo' => 'curriculum', 'modo' => 'fin', 'titulo' => 'Haga su CV hoy', 'subtitulo' => $dominio, 'voz' => 'Su próximo paso comienza con su currículum. Cree su cuenta y haga su CV hoy.'],
                ],
            ];
        } finally {
            view()->share('errors', $erroresAnteriores);
            URL::useAssetOrigin(config('app.asset_url'));
            config(['session.driver' => $sesionDriver]);
            app()->instance('request', $anterior);
            if ($sesionAnterior) {
                app()->instance('session.store', $sesionAnterior);
            } else {
                app()->forgetInstance('session.store');
            }
            $guard->forgetUser();
            if ($usuarioAnterior) {
                $guard->setUser($usuarioAnterior);
            }
        }
    }

    private function guardarEscena(string $carpeta, string $archivo, string $vista, array $datos = []): void
    {
        File::put($carpeta.'/'.$archivo.'.html', view($vista, $datos)->render(), true);
    }
}
