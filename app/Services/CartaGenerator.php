<?php

namespace App\Services;

use App\Models\Cliente;

/**
 * Genera cartas (renuncia, solicitud de empleo, recomendación) con:
 *  - Al menos 4 modelos de redacción distintos por tipo de carta.
 *  - Lenguaje correcto según el sexo de cada persona (el/la, suscrito/a,
 *    trabajador/a, lo/la recomiendo) — nunca deja "lo/la" literal.
 *  - El firmante de las cartas de recomendación se busca en la base de
 *    datos (igual que un cliente); si no está registrado, se puede
 *    escribir su nombre y celular a mano y se deja un espacio en blanco
 *    para que firme/complete la cédula físicamente.
 *  - Nada de "generado por" ni pie de sistema — es un documento para
 *    imprimir y usar tal cual, como si lo hubiera escrito la persona.
 *  - Adaptado al país: nombre del documento (cédula, DIMEX, pasaporte) y
 *    ley laboral de Nicaragua o de Costa Rica (preaviso del art. 28).
 *  - El PDF lo hace Chromium (mismo formato de siempre).
 */
class CartaGenerator
{
    public const TIPOS = [
        'renuncia_inmediata'     => 'Carta de renuncia (inmediata)',
        'renuncia_15dias'        => 'Carta de renuncia (con preaviso de ley)',
        'solicitud_empleo'       => 'Carta de solicitud de empleo',
        'recomendacion_laboral'  => 'Carta de recomendación laboral',
        'recomendacion_personal' => 'Carta de recomendación personal',
        'presentacion'           => 'Carta de presentación (con los datos de su CV)',
    ];

    /** Cuántos modelos de redacción distintos hay para CADA tipo (todos tienen 4). */
    public const CANTIDAD_MODELOS = 4;

    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /** Descripción corta de cada carta (para las tarjetas del menú). */
    public const DESCRIPCION = [
        'renuncia_inmediata'     => 'Renuncia irrevocable con efecto inmediato.',
        'renuncia_15dias'        => 'Renuncia respetando el preaviso que manda la ley.',
        'solicitud_empleo'       => 'Para entregar junto al CV en una empresa.',
        'recomendacion_laboral'  => 'La firma un jefe o patrono anterior.',
        'recomendacion_personal' => 'La firma un vecino, amigo o conocido.',
        'presentacion'           => 'Acompaña su CV: resume su experiencia, estudios y habilidades.',
    ];

    public const ICONOS = [
        'renuncia_inmediata' => 'door-open', 'renuncia_15dias' => 'calendar-clock', 'solicitud_empleo' => 'briefcase',
        'recomendacion_laboral' => 'award', 'recomendacion_personal' => 'handshake', 'presentacion' => 'file-user',
    ];

    protected string $docNombre;
    protected array $pais;

    public function __construct(protected Cliente $cliente, protected string $tipo, protected array $datos = [])
    {
        $this->pais = $cliente->datosPais();
        $this->docNombre = match ($cliente->tipo_documento) {
            'pasaporte' => 'pasaporte',
            'dimex'     => 'documento DIMEX',
            'dni_hn', 'dni_es' => 'DNI',
            'dui_sv'    => 'Documento Único de Identidad (DUI)',
            'dpi_gt'    => 'Documento Personal de Identificación (DPI)',
            'curp_mx'   => 'CURP',
            'ine_mx'    => 'credencial para votar',
            'cedula_co' => 'cédula de ciudadanía',
            'ce_co'     => 'cédula de extranjería',
            'nie_es'    => 'NIE',
            'licencia_us' => 'licencia de conducir',
            'green_us'  => 'tarjeta de residente permanente',
            'cedula_pa', 'cedula_ec' => 'cédula de identidad',
            'cedula_do' => 'cédula de identidad y electoral',
            'ci_cu'     => 'carné de identidad',
            'ci_ve', 'ci_bo', 'ci_uy', 'ci_py' => 'cédula de identidad',
            'dni_pe', 'dni_ar' => 'DNI',
            'ce_pe'     => 'carné de extranjería',
            'rut_cl'    => 'RUT',
            'cuil_ar'   => 'CUIL',
            'residencia'=> 'cédula de residencia',
            'partida_nacimiento' => 'código de persona',
            'otro'      => 'documento de identidad',
            default     => isset(\App\Support\Pais::reglasPersonalizadas()[$cliente->tipo_documento])
                ? mb_strtolower(\App\Support\Pais::reglasPersonalizadas()[$cliente->tipo_documento]['nombre'])
                : 'cédula de identidad',
        };
    }

    /** Frase de la ley laboral del país. */
    protected function ley(): string
    {
        return $this->pais['ley'] ? 'de conformidad con lo que establece ' . $this->pais['ley'] : 'de conformidad con la legislación laboral vigente';
    }

    /** "La Dalia, 23 de julio del año 2026" — lugar + fecha en español, todo escrito. */
    public static function fechaEnLetras(string $lugar, ?\DateTimeInterface $fecha = null): string
    {
        $fecha = $fecha ?? now();
        $dia = (int) $fecha->format('j');
        $mes = self::MESES[(int) $fecha->format('n')];
        $anio = $fecha->format('Y');
        return "{$lugar}, {$dia} de {$mes} del año {$anio}";
    }

    /** Palabras según el sexo indicado ('M'/'F'/'O'/null). Por defecto masculino si no hay dato. */
    private function genero(?string $sexo): array
    {
        if ($sexo === 'F') {
            return [
                'suscrito' => 'La suscrita', 'suscrito_min' => 'la suscrita', 'o_a' => 'a', 'trabajador' => 'trabajadora',
                'portador' => 'portadora', 'candidato' => 'candidata', 'nacido' => 'nacida',
                'mismo' => 'misma', 'el_la' => 'la', 'un_una' => 'una', 'lo_la' => 'la',
                'comprometido' => 'comprometida', 'responsable' => 'responsable', 'atento' => 'atenta', 'conocido' => 'conocida mía',
            ];
        }
        return [
            'suscrito' => 'El suscrito', 'suscrito_min' => 'el suscrito', 'o_a' => 'o', 'trabajador' => 'trabajador',
            'portador' => 'portador', 'candidato' => 'candidato', 'nacido' => 'nacido',
            'mismo' => 'mismo', 'el_la' => 'el', 'un_una' => 'un', 'lo_la' => 'lo',
            'comprometido' => 'comprometido', 'responsable' => 'responsable', 'atento' => 'atento', 'conocido' => 'conocido mío',
        ];
    }

    private function fechaCarta(): \Illuminate\Support\Carbon
    {
        $valor = $this->datos['fecha_carta'] ?? null;
        return $valor ? \Illuminate\Support\Carbon::parse($valor) : now();
    }

    private function modelo(): int
    {
        $m = (int) ($this->datos['modelo'] ?? 1);
        return ($m >= 1 && $m <= self::CANTIDAD_MODELOS) ? $m : 1;
    }

    public function datosVista(): array
    {
        $g = $this->genero($this->cliente->sexo);
        $c = $this->cliente;
        $lugar = trim($this->datos['lugar'] ?? '') ?: ($c->municipio_nacimiento ?: $this->pais['ciudad_def']);
        $fechaTexto = self::fechaEnLetras($lugar ?: '________', $this->fechaCarta());
        $modelo = $this->modelo();
        $cuerpo = $this->cuerpoPorDefecto($g, $modelo);

        return [
            'cliente'      => $c,
            'tipo'         => $this->tipo,
            'tituloTipo'   => $this->tituloDocumento(),
            'fechaTexto'   => $fechaTexto,
            'cuerpo'       => $cuerpo,
            'modelo'       => $modelo,
            'firmaCliente' => in_array($this->tipo, ['renuncia_inmediata', 'renuncia_15dias', 'solicitud_empleo', 'presentacion'], true),
            'firmante'     => $this->datosFirmante(),
        ];
    }

    /** Texto de la carta según el tipo y el modelo elegido. */
    private function cuerpoPorDefecto(array $g, int $modelo): string
    {
        // El "Atentamente" y los datos de contacto van una sola vez, junto a la firma.
        return preg_replace('#\s*<p>\s*Atentamente,\s*</p>#u', '', $this->cuerpoSinLimpiar($g, $modelo));
    }

    private function cuerpoSinLimpiar(array $g, int $modelo): string
    {
        return match ($this->tipo) {
            'renuncia_inmediata'     => $this->cuerpoRenuncia($g, true, $modelo),
            'renuncia_15dias'        => $this->cuerpoRenuncia($g, false, $modelo),
            'solicitud_empleo'       => $this->cuerpoSolicitudEmpleo($g, $modelo),
            'recomendacion_laboral'  => $this->cuerpoRecomendacionLaboral($g, $modelo),
            'recomendacion_personal' => $this->cuerpoRecomendacionPersonal($g, $modelo),
            'presentacion'           => $this->cuerpoPresentacion($g, $modelo),
            default                  => '<p>Tipo de carta no reconocido.</p>',
        };
    }

    /** El título grande y subrayado que va arriba de cada carta. */
    private function tituloDocumento(): string
    {
        return match ($this->tipo) {
            'renuncia_inmediata' => 'CARTA DE RENUNCIA INMEDIATA',
            'renuncia_15dias'    => 'CARTA DE RENUNCIA',
            'solicitud_empleo'   => 'CARTA DE SOLICITUD DE EMPLEO',
            'presentacion'       => 'CARTA DE PRESENTACIÓN',
            'recomendacion_laboral', 'recomendacion_personal' => 'CARTA DE RECOMENDACIÓN',
            default => 'CARTA',
        };
    }

    /**
     * Datos de quien FIRMA la carta de recomendación (no es el cliente,
     * es quien recomienda). Si viene un cliente_firmante_id, se busca en
     * la base de datos (nombre, cédula, cel, correo). Si no, se usan los
     * datos escritos a mano (nombre + cel) y se deja espacio en la hoja
     * para que complete la cédula a mano.
     */
    private function datosFirmante(): ?array
    {
        if (!in_array($this->tipo, ['recomendacion_laboral', 'recomendacion_personal'], true)) {
            return null;
        }

        $firmanteId = $this->datos['firmante_cliente_id'] ?? null;
        if ($firmanteId) {
            $firmante = Cliente::find($firmanteId);
            if ($firmante) {
                return [
                    'nombre'  => $firmante->nombreCompleto(),
                    'doc'     => $firmante->tipoDocumentoLegible(),
                    'cedula'  => $firmante->documentoFormateado(),
                    'celular' => $firmante->telefono,
                    'correo'  => $firmante->correo,
                    'sexo'    => $firmante->sexo,
                    'deBase'  => true,
                ];
            }
        }

        return [
            'nombre'  => trim($this->datos['firmante_nombre'] ?? '') ?: null,
            'doc'     => 'Cédula',
            'cedula'  => null, // se deja en blanco en la hoja, para llenar a mano
            'celular' => trim($this->datos['firmante_celular'] ?? '') ?: null,
            'correo'  => null,
            'sexo'    => $this->datos['firmante_sexo'] ?? null,
            'deBase'  => false,
        ];
    }

    // =========================================================
    //  RENUNCIA (inmediata / 15 días) — 4 modelos cada una
    // =========================================================
    private function cuerpoRenuncia(array $g, bool $inmediata, int $modelo): string
    {
        $c = $this->cliente;
        $nombre = e($c->nombreCompleto());
        $cedula = e($c->documentoFormateado());
        $tieneCargo = filled($this->datos['cargo'] ?? null);
        $cargo = $tieneCargo ? e($this->datos['cargo']) : '';
        $alCargo = $tieneCargo ? "al cargo de <strong>{$cargo}</strong>" : 'al cargo que actualmente desempeño';
        $elCargo = $tieneCargo ? "el cargo de <strong>{$cargo}</strong>" : 'el cargo asignado';
        $empresa = e($this->datos['empresa'] ?? '__________________________');
        $encargado = trim($this->datos['encargado_nombre'] ?? '');
        $telefono = e($c->telefono ?: '__________________');

        $fechaEfectiva = null;
        if ($inmediata) {
            $fechaEfectiva = $this->fechaCarta()->format('d/m/Y');
        } else {
            $valor = $this->datos['fecha_renuncia'] ?? null;
            $fechaEfectiva = $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : null;
        }

        $destinatario = $encargado
            ? "Señor(a) <strong>" . e($encargado) . "</strong><br><strong>{$empresa}</strong><br>Su despacho."
            : "Señores<br><strong>{$empresa}</strong><br>Su despacho.";

        $leyNica = $this->ley();

        if ($inmediata) {
            $parrafos = match ($modelo) {
                2 => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Reciban un cordial saludo. Por medio de la presente, {$g['suscrito_min']} <strong>{$nombre}</strong>,
                    {$g['portador']} de {$this->docNombre} número <strong>{$cedula}</strong>, hago de su
                    conocimiento mi decisión de presentar renuncia {$alCargo}, con carácter
                    <strong>IRREVOCABLE E INMEDIATO</strong>, siendo efectiva a partir del <strong>{$fechaEfectiva}</strong>.</p>
                    <p>Agradezco la confianza y las oportunidades brindadas durante mi tiempo en la institución.</p>
                    <p>Sin más que agregar, quedo de ustedes.</p>",
                3 => "<p>{$destinatario}</p>
                    <p>Por este medio comunico formalmente mi renuncia {$alCargo} que he venido
                    desempeñando en esta empresa. Mi nombre es <strong>{$nombre}</strong>, identificad{$g['o_a']} con
                    {$this->docNombre} número <strong>{$cedula}</strong>.</p>
                    <p>Esta renuncia tiene carácter <strong>irrevocable</strong> y se hace efectiva de manera
                    <strong>inmediata</strong>, a partir del día <strong>{$fechaEfectiva}</strong>, {$leyNica}.</p>
                    <p>Agradezco el tiempo y la experiencia adquirida durante mi permanencia en la empresa.</p>
                    <p>Atentamente,</p>",
                4 => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Por medio de la presente hago constar mi decisión, tomada de forma libre y voluntaria, de
                    renunciar {$alCargo}. Mis datos son: <strong>{$nombre}</strong>, {$this->docNombre}
                    <strong>{$cedula}</strong>.</p>
                    <p>Dicha renuncia es <strong>irrevocable</strong> y entra en vigencia de forma
                    <strong>inmediata</strong> a partir del <strong>{$fechaEfectiva}</strong>.</p>
                    <p>Agradezco profundamente la oportunidad de haber formado parte de este equipo de trabajo.</p>
                    <p>Sin otro particular, me suscribo.</p>",
                default => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Por este medio, yo <strong>{$nombre}</strong>, mayor de edad, {$g['portador']} de
                    {$this->docNombre} número <strong>{$cedula}</strong>, quien labora en esta empresa desempeñando
                    {$elCargo}, comunico mi decisión de renunciar de manera <strong>IRREVOCABLE E INMEDIATA</strong>,
                    a partir del <strong>{$fechaEfectiva}</strong>.</p>
                    <p>Agradezco sinceramente la oportunidad que me fue brindada y el tiempo compartido en esta
                    institución.</p>
                    <p>Sin otro particular, me suscribo.</p>",
            };
        } else {
            if ($this->cliente->pais === 'CR') {
                $frasePlazo = $fechaEfectiva
                    ? "haciendo efectiva mi salida el día <strong>{$fechaEfectiva}</strong>, respetando el
                       <strong>preaviso</strong> que establece el artículo 28 del Código de Trabajo de la República de Costa Rica"
                    : "respetando el <strong>preaviso</strong> que establece el artículo 28 del Código de Trabajo de la
                       República de Costa Rica, contado a partir de la fecha de la presente";
            } elseif (in_array($this->cliente->pais, ['NI', null, ''], true)) {
                $frasePlazo = $fechaEfectiva
                    ? "haciendo efectiva mi salida el día <strong>{$fechaEfectiva}</strong>, respetando el plazo de
                       <strong>quince (15) días calendario</strong> {$leyNica}"
                    : "haciendo efectiva mi salida en un plazo de <strong>quince (15) días calendario</strong> a partir de
                       la fecha de la presente, {$leyNica}";
            } else {
                // Otros países: el plazo cambia según la ley y el contrato, así que no se pone un número fijo.
                $leyTexto = $this->pais['ley'] ? 'establece ' . $this->pais['ley'] : 'establece la legislación laboral vigente';
                $frasePlazo = $fechaEfectiva
                    ? "haciendo efectiva mi salida el día <strong>{$fechaEfectiva}</strong>, respetando el
                       <strong>preaviso</strong> que {$leyTexto}"
                    : "respetando el <strong>preaviso</strong> que {$leyTexto}, contado a partir de la fecha de la presente";
            }

            $parrafos = match ($modelo) {
                2 => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Reciban un cordial saludo. Por medio de la presente, {$g['suscrito_min']} <strong>{$nombre}</strong>,
                    {$g['portador']} de {$this->docNombre} número <strong>{$cedula}</strong>, comunico mi
                    decisión de renunciar {$alCargo}, {$frasePlazo}.</p>
                    <p>Quedo en la mejor disposición de entregar formalmente mis funciones y pendientes durante este
                    período, para garantizar una transición ordenada.</p>
                    <p>Agradezco la oportunidad y el tiempo compartido en esta institución.</p>",
                3 => "<p>{$destinatario}</p>
                    <p>Por este medio comunico formalmente mi renuncia {$alCargo}. Mi nombre es
                    <strong>{$nombre}</strong>, identificad{$g['o_a']} con {$this->docNombre} número <strong>{$cedula}</strong>.</p>
                    <p>Conforme a lo establecido por la ley laboral, {$frasePlazo}.</p>
                    <p>Quedo {$g['atento']} a cualquier indicación para el proceso de entrega de cargo.</p>
                    <p>Atentamente,</p>",
                4 => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Por medio de la presente hago constar mi decisión, tomada de forma libre y voluntaria, de
                    renunciar {$alCargo}. Mis datos son: <strong>{$nombre}</strong>, {$this->docNombre}
                    <strong>{$cedula}</strong>.</p>
                    <p>Conforme a la ley, {$frasePlazo}.</p>
                    <p>Agradezco profundamente la oportunidad de haber formado parte de este equipo de trabajo.</p>
                    <p>Sin otro particular, me suscribo.</p>",
                default => "<p>{$destinatario}</p>
                    <p>Estimados señores:</p>
                    <p>Por este medio, yo <strong>{$nombre}</strong>, mayor de edad, {$g['portador']} de
                    {$this->docNombre} número <strong>{$cedula}</strong>, quien labora en esta empresa desempeñando
                    {$elCargo}, comunico mi decisión de renunciar, {$frasePlazo}.</p>
                    <p>Agradezco sinceramente la oportunidad que me fue brindada y el tiempo compartido en esta
                    institución.</p>
                    <p>Sin otro particular, me suscribo.</p>",
            };
        }

        return $parrafos;
    }

    // =========================================================
    //  SOLICITUD DE EMPLEO — 4 modelos
    // =========================================================
    private function cuerpoSolicitudEmpleo(array $g, int $modelo): string
    {
        $c = $this->cliente;
        $nombre = e($c->nombreCompleto());
        $cedula = e($c->documentoFormateado());
        $empresa = e($this->datos['empresa'] ?? '__________________________');
        $area = e($this->datos['area'] ?? 'el área que ustedes consideren conveniente');
        $telefono = e($c->telefono ?: '__________________');
        $correo = e($c->correo ?: '__________________');
        $encargado = trim($this->datos['encargado_nombre'] ?? '');

        $destinatario = $encargado
            ? "Señor(a) <strong>" . e($encargado) . "</strong><br><strong>{$empresa}</strong><br>Su despacho."
            : "Señores<br><strong>{$empresa}</strong><br>Su despacho.";

        return match ($modelo) {
            2 => "<p>{$destinatario}</p>
                <p>Reciban un cordial saludo. Por medio de la presente, {$g['suscrito_min']} <strong>{$nombre}</strong>,
                {$g['portador']} de {$this->docNombre} <strong>{$cedula}</strong>, me dirijo a ustedes
                con el fin de solicitar {$g['un_una']} oportunidad laboral dentro de su distinguida empresa, en
                {$area}.</p>
                <p>Me considero {$g['un_una']} persona {$g['responsable']}, con disposición para aprender y
                aportar al equipo de trabajo.</p>
                <p>Quedo {$g['atento']} a su respuesta.</p>
                <p>Atentamente,</p>",
            3 => "<p>{$destinatario}</p>
                <p>Por medio de la presente, quien suscribe, <strong>{$nombre}</strong>, con {$this->docNombre}
                número <strong>{$cedula}</strong>, solicita respetuosamente ser considerad{$g['o_a']} como
                {$g['candidato']} para una vacante disponible en {$area}.</p>
                <p>Cuento con la disposición y el compromiso necesarios para desempeñar el cargo de la mejor
                manera posible.</p>
                <p>En espera de una respuesta favorable, me despido.</p>
                <p>Atentamente,</p>",
            4 => "<p>{$destinatario}</p>
                <p>Estimados señores:</p>
                <p>Me dirijo a ustedes con el propósito de solicitar formalmente mi ingreso como {$g['candidato']}
                a un puesto disponible en {$area} de su empresa. Mis datos son: <strong>{$nombre}</strong>, {$this->docNombre} <strong>{$cedula}</strong>.</p>
                <p>Estoy {$g['comprometido']} a desempeñar mis funciones con responsabilidad y dedicación.</p>
                <p>Sin otro particular, quedo a la espera de su valiosa respuesta.</p>",
            default => "<p>{$destinatario}</p>
                <p>Estimados señores:</p>
                <p>{$g['suscrito']}, <strong>{$nombre}</strong>, {$g['portador']} de {$this->docNombre}
                número <strong>{$cedula}</strong>, por este medio me dirijo a ustedes con el debido respeto para
                solicitar se me considere como {$g['candidato']} para optar a una plaza laboral dentro de
                su distinguida empresa, específicamente en {$area}.</p>
                <p>Cuento con la disposición, responsabilidad y el compromiso necesarios para desempeñar dicho cargo
                de la mejor manera, aportando mi esfuerzo a la institución.</p>
                <p>Quedo en espera de su favorable respuesta.</p>
                <p>Sin otro particular, me suscribo.</p>",
        };
    }

    // =========================================================
    //  RECOMENDACIÓN LABORAL — 4 modelos
    // =========================================================
    private function cuerpoRecomendacionLaboral(array $g, int $modelo): string
    {
        $c = $this->cliente;
        $nombre = e($c->nombreCompleto());
        $cedula = e($c->documentoFormateado());
        $empresa = e($this->datos['empresa'] ?? '__________________________');
        $cargo = e($this->datos['cargo'] ?? '__________________________');
        $tiempo = e($this->datos['tiempo_laborado'] ?? '__________________________');

        return match ($modelo) {
            2 => "<p>A quien pueda interesar:</p>
                <p>Hago constar por este medio que <strong>{$nombre}</strong>, {$g['portador']} de {$this->docNombre} <strong>{$cedula}</strong>, prestó sus servicios en <strong>{$empresa}</strong> en el
                puesto de <strong>{$cargo}</strong> durante <strong>{$tiempo}</strong>.</p>
                <p>En todo momento mostró responsabilidad, buena disposición y compromiso con las tareas
                asignadas, por lo cual {$g['lo_la']} recomiendo sin ninguna reserva.</p>
                <p>Extiendo la presente a solicitud de la parte interesada.</p>",
            3 => "<p>A quien interese:</p>
                <p>Por medio de la presente certifico que <strong>{$nombre}</strong> formó parte del personal de
                <strong>{$empresa}</strong>, desempeñando el cargo de <strong>{$cargo}</strong> por un período de
                <strong>{$tiempo}</strong>.</p>
                <p>Su desempeño fue satisfactorio, cumpliendo con sus responsabilidades de forma puntual y
                {$g['responsable']}. Por lo anterior, {$g['lo_la']} recomiendo ampliamente para futuras
                oportunidades laborales.</p>",
            4 => "<p>A quien corresponda:</p>
                <p>Hago constar que <strong>{$nombre}</strong>, identificad{$g['o_a']} con {$this->docNombre}
                número <strong>{$cedula}</strong>, laboró bajo mi supervisión en <strong>{$empresa}</strong>,
                ocupando el cargo de <strong>{$cargo}</strong> durante <strong>{$tiempo}</strong>.</p>
                <p>Puedo dar fe de que se desempeñó de manera {$g['responsable']} y {$g['comprometido']} en todas
                sus funciones. Recomiendo a {$g['lo_la']} sin reservas para cualquier oportunidad laboral futura.</p>",
            default => "<p>A quien corresponda:</p>
                <p>Por este medio hago constar que {$g['el_la']} {$g['mismo']} {$g['trabajador']} <strong>{$nombre}</strong>,
                {$g['portador']} de {$this->docNombre} número <strong>{$cedula}</strong>, laboró en
                <strong>{$empresa}</strong> desempeñando el cargo de <strong>{$cargo}</strong>, durante un período de
                <strong>{$tiempo}</strong>.</p>
                <p>Durante su tiempo con nosotros, demostró ser {$g['un_una']} {$g['trabajador']} {$g['responsable']},
                puntual y {$g['comprometido']} con sus funciones, por lo que {$g['lo_la']} recomendamos ampliamente
                para futuras oportunidades laborales.</p>
                <p>Extiendo la presente a solicitud de la parte interesada, para los fines que estime convenientes.</p>",
        };
    }

    // =========================================================
    //  RECOMENDACIÓN PERSONAL — 4 modelos
    // =========================================================
    private function cuerpoRecomendacionPersonal(array $g, int $modelo): string
    {
        $c = $this->cliente;
        $nombre = e($c->nombreCompleto());
        $cedula = e($c->documentoFormateado());
        $relacion = e($this->datos['relacion'] ?? ($g['conocido'] . ' desde hace varios años'));

        return match ($modelo) {
            2 => "<p>A quien pueda interesar:</p>
                <p>Hago constar por este medio que conozco a <strong>{$nombre}</strong>, {$g['portador']} de
                {$this->docNombre} <strong>{$cedula}</strong>, {$relacion}.</p>
                <p>Puedo dar fe de su buena conducta, honestidad y responsabilidad, cualidades que ha demostrado en
                todo momento. Por ello, {$g['lo_la']} recomiendo con toda confianza.</p>",
            3 => "<p>A quien interese:</p>
                <p>Por medio de la presente certifico que <strong>{$nombre}</strong> es {$relacion}, y que a lo largo
                de este tiempo ha mostrado ser una persona de bien, {$g['responsable']} y digna de confianza.</p>
                <p>Extiendo esta recomendación con toda tranquilidad, a solicitud de la parte interesada.</p>",
            4 => "<p>A quien corresponda:</p>
                <p>Hago constar que <strong>{$nombre}</strong>, identificad{$g['o_a']} con {$this->docNombre}
                <strong>{$cedula}</strong>, es {$relacion}. Durante este tiempo he podido conocer su buen carácter,
                honestidad y sentido de responsabilidad.</p>
                <p>Por lo anterior, {$g['lo_la']} recomiendo sin ninguna reserva para los fines que considere
                convenientes.</p>",
            default => "<p>A quien corresponda:</p>
                <p>Por este medio hago constar que conozco a <strong>{$nombre}</strong>, {$g['portador']} de
                {$this->docNombre} número <strong>{$cedula}</strong>, {$relacion}.</p>
                <p>Durante el tiempo que {$g['lo_la']} conozco, ha demostrado ser una persona honesta, responsable y
                de buenas costumbres, por lo que {$g['lo_la']} recomiendo sin ninguna reserva.</p>
                <p>Extiendo la presente a solicitud de la parte interesada, para los fines que estime convenientes.</p>",
        };
    }

    // =========================================================
    //  CARTA DE PRESENTACIÓN — se arma con los datos del CV
    // =========================================================
    private function cuerpoPresentacion(array $g, int $modelo): string
    {
        $c = $this->cliente->loadMissing(['experiencias', 'estudios', 'habilidades']);
        $nombre = e($c->nombreCompleto());
        $empresa = e($this->datos['empresa'] ?? '__________________________');
        $encargado = trim($this->datos['encargado_nombre'] ?? '');
        $puestoTxt = trim($this->datos['cargo'] ?? '') ?: trim((string) $c->profesion);
        $puesto = $puestoTxt !== '' ? 'el puesto de <strong>' . e($puestoTxt) . '</strong>' : 'el puesto que tengan disponible';
        $alPuesto = 'a' . substr($puesto, 1); // "al puesto de…"

        $destinatario = $encargado
            ? "Señor(a) <strong>" . e($encargado) . "</strong><br><strong>{$empresa}</strong><br>Su despacho."
            : "Señores<br><strong>{$empresa}</strong><br>Su despacho.";
        $saludo = $encargado ? 'Estimado(a) señor(a):' : 'Estimados señores:';

        // Experiencia: años desde el primer trabajo y el trabajo más reciente.
        $exps = $c->experiencias->filter(fn ($e) => filled($e->cargo) || filled($e->empresa));
        $inicio = $exps->pluck('fecha_inicio')->filter()->min();
        $anios = $inicio ? (int) floor($inicio->diffInMonths(now()) / 12) : 0;
        $ultima = $exps->sortByDesc(fn ($e) => $e->fecha_fin ? $e->fecha_fin->timestamp : PHP_INT_MAX)->first();
        $fraseExp = '';
        if ($ultima) {
            $donde = trim(($ultima->cargo ? 'como <strong>' . e($ultima->cargo) . '</strong>' : '') . ($ultima->empresa ? ' en ' . e($ultima->empresa) : ''));
            $tiempo = $anios >= 2 ? "más de {$anios} años de experiencia laboral" : ($anios === 1 ? 'más de un año de experiencia laboral' : 'experiencia laboral');
            $actual = $ultima->fecha_fin === null && $ultima->fecha_inicio !== null;
            $fraseExp = "Cuento con {$tiempo}; " . ($actual ? 'actualmente me desempeño' : 'mi experiencia más reciente fue') . ' ' . rtrim($donde, '.') . '.';
        }

        // Estudio más alto.
        $orden = array_flip(array_keys(\App\Models\ClienteEstudio::NIVELES));
        $est = $c->estudios->sortBy(fn ($e) => $orden[$e->nivel] ?? 99)->first();
        $fraseEst = $est ? 'En cuanto a mi formación, ' . ($est->titulo ? 'tengo <strong>' . e($est->titulo) . '</strong>' : 'curso estudios de ' . e(mb_strtolower($est->nivelLegible())))
            . ($est->institucion ? ' (' . e($est->institucion) . ')' : '') . '.' : '';

        // Habilidades (hasta 5) en una lista natural: "a, b y c".
        $habs = $c->habilidades->pluck('habilidad')->filter()->take(5)->map(function ($h) {
            $h = trim($h);
            // "Contabilidad general" → "contabilidad general"; "Microsoft Excel" se deja igual.
            return e(preg_match('/\p{Lu}/u', mb_substr($h, 1)) ? $h : mb_strtolower(mb_substr($h, 0, 1)) . mb_substr($h, 1));
        })->values()->all();
        $listaHabs = count($habs) > 1 ? implode(', ', array_slice($habs, 0, -1)) . ' y ' . end($habs) : ($habs[0] ?? '');
        $fraseHabs = $listaHabs ? "Entre mis principales habilidades están: {$listaHabs}." : '';

        $datosCv = trim(implode(' ', array_filter([$fraseExp, $fraseEst, $fraseHabs])));
        $parrafoCv = $datosCv !== '' ? "<p>{$datosCv}</p>" : '';

        return match ($modelo) {
            2 => "<p>{$destinatario}</p>
                <p>{$saludo}</p>
                <p>Mi nombre es <strong>{$nombre}</strong> y me presento ante ustedes con mucho interés en formar
                parte de su equipo de trabajo en {$puesto}.</p>
                {$parrafoCv}
                <p>Me considero {$g['un_una']} persona {$g['responsable']}, puntual y con muchas ganas de aprender. Estoy
                {$g['comprometido']} a dar lo mejor de mí para contribuir al crecimiento de la empresa.</p>
                <p>Adjunto mi currículum con el detalle de mi experiencia. Agradezco de antemano la oportunidad de una entrevista.</p>",
            3 => "<p>{$destinatario}</p>
                <p>{$saludo}</p>
                <p>Por medio de la presente les hago llegar mi currículum para ser considerad{$g['o_a']} en sus procesos
                de selección para {$puesto}.</p>
                {$parrafoCv}
                <p>Sé trabajar en equipo, cumplir metas y adaptarme con rapidez a nuevas tareas. Estaré {$g['atento']}
                a su llamada para ampliar cualquier información.</p>",
            4 => "<p>{$destinatario}</p>
                <p>{$saludo}</p>
                <p>Con el debido respeto me dirijo a ustedes para presentar mi candidatura {$alPuesto} en
                <strong>{$empresa}</strong>, empresa que admiro por su trayectoria.</p>
                {$parrafoCv}
                <p>Estoy convencid{$g['o_a']} de que puedo aportar responsabilidad, compromiso y buena actitud a su equipo.
                Quedo a su disposición para una entrevista en el momento que lo consideren oportuno.</p>",
            default => "<p>{$destinatario}</p>
                <p>{$saludo}</p>
                <p>{$g['suscrito']}, <strong>{$nombre}</strong>, me dirijo a ustedes para presentarme como
                {$g['candidato']} para {$puesto}, con el deseo de formar parte de su distinguida empresa.</p>
                {$parrafoCv}
                <p>Me caracterizo por ser {$g['un_una']} persona {$g['responsable']}, honesta y con buena disposición para el
                trabajo en equipo. Adjunto mi currículum vitae para su consideración.</p>
                <p>Agradezco de antemano su atención y quedo en espera de una respuesta favorable.</p>",
        };
    }

    /** HTML de la carta (para la vista previa o para el PDF). */
    public function html(bool $pantalla = false): string
    {
        return view('cartas.documento', $this->datosVista() + ['modoPantalla' => $pantalla])->render();
    }

    public function nombreArchivo(): string
    {
        $n = self::TIPOS[$this->tipo] . ' - ' . $this->cliente->nombreCompleto() . '.pdf';
        return preg_replace('/[^A-Za-z0-9 .()-]/', '', \Illuminate\Support\Str::ascii($n));
    }
}
