<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\User;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use RuntimeException;

class VideoPromocional
{
    public const COLA = 'video-promocional';

    public const ESCENAS = 13;

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
                    'musica' => config('micv.video.musica'),
                    'espeak' => config('micv.video.espeak'),
                    'piper' => config('micv.video.piper'),
                    'modeloVoz' => config('micv.video.modelo_voz'),
                    'motorVoz' => config('micv.video.motor_voz'),
                    'pocket' => config('micv.video.pocket'),
                    'cacheVoz' => storage_path('app/video-voz/cache'),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ));

            if (! is_file($salida) || filesize($salida) < 1024) {
                throw new RuntimeException('La generación no produjo un archivo MP4.');
            }
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    private function prepararEscenas(string $carpeta): array
    {
        $configuracion = Configuracion::actual();
        if (! $configuracion->registro_abierto) {
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

        // Foto ficticia temporal: permite usar las mismas vistas y comprobaciones de foto.
        $fotoTemporal = 'videos-promocionales/foto-demo-'.Str::uuid().'.png';
        try {
            if (! Storage::disk(Archivos::DISCO)->put($fotoTemporal, File::get(resource_path('video-promocional/valeria.png')))) {
                throw new RuntimeException('No se pudo preparar la foto de demostración.');
            }
            $cliente = CvEjemplo::cliente();
            $cliente->forceFill([
                'id' => 0,
                'foto' => $fotoTemporal,
                'primer_nombre' => 'Valeria',
                'segundo_nombre' => null,
                'primer_apellido' => 'Mendoza',
                'segundo_apellido' => 'Ríos',
                'correo' => 'valeria.cv@example.com',
                'telefono' => null,
                'direccion' => 'El Tuma–La Dalia, Matagalpa',
                'pais_residencia' => 'NI',
                'profesion' => 'Asistente administrativa',
                'estado_civil' => null,
                'perfil_profesional' => 'Organizo documentos, doy seguimiento a solicitudes y preparo reportes.',
            ]);
            // Datos ficticios completos y legibles, sin texto de relleno en el tutorial.
            $cliente->experiencias[0]->forceFill([
                'cargo' => 'Asistente administrativa',
                'descripcion' => 'Organización de documentos, seguimiento de solicitudes y elaboración de reportes con Excel.',
            ]);
            $cliente->experiencias[1]->forceFill(['empresa' => 'Comercial de Ejemplo']);
            $cliente->estudios[1]->forceFill(['institucion' => 'Centro de Formación de Ejemplo']);
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
            $cv['diseno_defecto'] = 'pro';
            $this->guardarEscena($carpeta, 'panel', 'mi-cv.panel', [
                'cliente' => $cliente,
                'avance' => $cliente->porcentajeCompleto(),
                'pendientes' => $cliente->pendientesCv(),
                'cv' => $cv,
                'paletas' => Configuracion::PALETAS_PRO,
            ]);
            $datosCv = CvDatos::armar(
                $cliente,
                'pro',
                conFoto: true,
                u: $demostracion,
            );
            $this->guardarEscena($carpeta, 'curriculum', 'cv.pro', $datosCv);
            $this->guardarEscena($carpeta, 'vista-curriculum', 'cv.pro', $datosCv + ['modoPantalla' => true]);

            return [
                'nombre' => $configuracion->nombre_negocio ?: 'Mi CV',
                'dominio' => $dominio,
                'logo' => $logo,
                'primario' => $configuracion->color_primario,
                'secundario' => $configuracion->color_secundario,
                'origen' => self::ORIGEN_CAPTURA,
                'foto' => $datosCv['foto'],
                'fotoArchivo' => resource_path('video-promocional/valeria.png'),
                'escenas' => [
                    ['archivo' => 'curriculum', 'modo' => 'hero', 'capitulo' => 'SU EXPERIENCIA, BIEN PRESENTADA', 'titulo' => 'Un CV que habla de usted', 'subtitulo' => 'Foto profesional · Diseño lateral · Listo para enviar', 'detalle' => 'ASÍ QUEDARÁ SU CURRÍCULUM', 'voz' => 'Su experiencia merece una buena presentación. Vamos a crear un currículum con foto y diseño lateral, paso a paso.'],
                    ['archivo' => 'inicio', 'modo' => 'inicio', 'capitulo' => '01 / CREE SU CUENTA', 'titulo' => 'El primer paso es entrar', 'subtitulo' => 'Abra el sitio y toque Crear mi cuenta gratis.', 'detalle' => 'TODO COMIENZA AQUÍ', 'voz' => "Abra {$dominioHablado}. En la página de inicio, toque Crear mi cuenta gratis.", 'caption' => "Abra {$dominio}. En la página de inicio, toque Crear mi cuenta gratis."],
                    ['archivo' => 'registro', 'modo' => 'registro', 'capitulo' => '01 / CREE SU CUENTA', 'titulo' => 'Una cuenta para su historia', 'subtitulo' => 'País, nombre, correo y una contraseña segura.', 'detalle' => 'USE UN CORREO AL QUE TENGA ACCESO', 'voz' => 'Elija el país de su documento. Escriba su nombre y un correo que pueda revisar. Cree una contraseña segura y repítala.'],
                    ['archivo' => 'registro', 'modo' => 'crear', 'capitulo' => '01 / CREE SU CUENTA', 'titulo' => 'Confirme su registro', 'subtitulo' => 'Lea los términos y toque Crear cuenta.', 'detalle' => 'REVISE LOS DATOS ANTES DE CONTINUAR', 'voz' => 'Revise que sus datos estén correctos. Lea y acepte los términos. Después, toque Crear cuenta.'],
                    ['archivo' => 'codigo', 'modo' => 'codigo', 'capitulo' => '01 / CREE SU CUENTA', 'titulo' => 'Su correo, verificado', 'subtitulo' => 'Escriba el código de seis dígitos recibido.', 'detalle' => 'REVISE TAMBIÉN CORREO NO DESEADO', 'voz' => 'Abra el mensaje de verificación e ingrese el código de seis dígitos. Si no lo encuentra, revise la carpeta de correo no deseado.'],
                    ['archivo' => 'datos', 'modo' => 'foto', 'capitulo' => '02 / DÉ FORMA A SU PERFIL', 'titulo' => 'Una foto que le represente', 'subtitulo' => 'Buena luz, fondo claro y el rostro centrado.', 'detalle' => 'ENCUADRE EL ROSTRO Y USE ESTA FOTO', 'voz' => 'En Mis datos, abra Foto y elija una imagen clara. Centre el rostro en el recuadro y pulse Usar esta foto. Guarde los cambios.'],
                    ['archivo' => 'datos', 'modo' => 'datos', 'capitulo' => '02 / DÉ FORMA A SU PERFIL', 'titulo' => 'Cuente qué sabe hacer', 'subtitulo' => 'Un perfil breve, concreto y fácil de leer.', 'detalle' => 'SU PERFIL EN DOS O TRES FRASES', 'voz' => 'En Perfil, explique qué sabe hacer y en qué puede aportar. Use dos o tres frases claras, relacionadas con el trabajo que busca.'],
                    ['archivo' => 'datos', 'modo' => 'experiencia', 'capitulo' => '02 / DÉ FORMA A SU PERFIL', 'titulo' => 'Su experiencia tiene valor', 'subtitulo' => 'Cargo, empresa, fechas y tareas principales.', 'detalle' => 'DESCRIBA TAREAS Y LOGROS REALES', 'voz' => 'Añada su cargo, la empresa y las fechas. Describa sus tareas y logros con ejemplos concretos. Use siempre información verdadera.'],
                    ['archivo' => 'datos', 'modo' => 'estudios', 'capitulo' => '02 / DÉ FORMA A SU PERFIL', 'titulo' => 'Muestre su preparación', 'subtitulo' => 'Estudios y cursos relevantes para su objetivo.', 'detalle' => 'GUARDE LOS CAMBIOS AL TERMINAR', 'voz' => 'Agregue sus estudios, el centro de formación y el año. Incluya los cursos relevantes y guarde todos los cambios.'],
                    ['archivo' => 'panel', 'modo' => 'galeria', 'capitulo' => '03 / ELIJA CÓMO PRESENTARSE', 'titulo' => 'Elija el diseño Lateral', 'subtitulo' => 'Una columna de color y una foto destacada.', 'detalle' => 'GALERÍA → LATERAL', 'voz' => 'Regrese a Mi panel y abra la galería de diseños. Elija Lateral: la foto y los datos de contacto quedan en una columna de color.'],
                    ['archivo' => 'curriculum', 'modo' => 'vista', 'capitulo' => '03 / ELIJA CÓMO PRESENTARSE', 'titulo' => 'Todo en su lugar', 'subtitulo' => 'Revise la foto, el correo, las fechas y la ortografía.', 'detalle' => 'UNA ÚLTIMA REVISIÓN HACE LA DIFERENCIA', 'voz' => 'Revise la vista previa. Compruebe que su foto se vea bien y que el correo, las fechas y la ortografía estén correctos.'],
                    ['archivo' => 'panel', 'modo' => 'descarga', 'capitulo' => '04 / COMPARTA SU TALENTO', 'titulo' => 'Descargue con su foto', 'subtitulo' => 'Descargar PDF → Con foto → Español → Descargar.', 'detalle' => 'PDF LISTO PARA ENVIAR', 'voz' => 'Toque Descargar PDF. Mantenga la opción Con foto, seleccione Español y confirme la descarga. Ya puede enviar su currículum a una oportunidad de trabajo.'],
                    ['archivo' => 'curriculum', 'modo' => 'fin', 'capitulo' => 'SU SIGUIENTE OPORTUNIDAD', 'titulo' => 'Su próximo paso empieza hoy', 'subtitulo' => $dominio, 'detalle' => 'CREE SU CUENTA · HAGA SU CV', 'voz' => 'Una buena presentación puede abrir una conversación. Prepare su currículum, manténgalo actualizado y dé el siguiente paso con confianza.'],
                ],
            ];
        } finally {
            Storage::disk(Archivos::DISCO)->delete($fotoTemporal);
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
