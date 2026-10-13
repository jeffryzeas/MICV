<?php

namespace App\Support;

/**
 * Ayudas para escribir el CV: frases de perfil profesional y habilidades
 * sugeridas según el oficio. Todo en lenguaje neutro ("Persona…") para
 * que sirva igual a hombres y mujeres.
 */
class Sugerencias
{
    /** clave => [nombre, palabras que lo identifican, frases de perfil, habilidades] */
    public const OFICIOS = [
        'cajero' => ['Cajero(a)', ['cajer', 'caja'], [
            'Persona honesta, ordenada y amable, con experiencia en el manejo de caja, cobros en efectivo y con tarjeta, y cierres diarios sin faltantes.',
            'Persona responsable y de trato cordial, acostumbrada a atender filas con rapidez, dar vuelto con exactitud y mantener la caja en orden.',
            'Persona confiable y atenta a los detalles, con habilidad para los números, la atención al cliente y el trabajo bajo presión.',
        ], ['Manejo de caja', 'Cierres de caja', 'Cobro con tarjeta (datáfono)', 'Atención al cliente', 'Honestidad', 'Rapidez con los números', 'Facturación electrónica']],

        'bodeguero' => ['Bodeguero(a) / Auxiliar de bodega', ['bodeg', 'almac', 'inventari', 'despach'], [
            'Persona responsable y con buena condición física, con experiencia en carga y descarga, acomodo de mercadería y control de inventarios.',
            'Persona ordenada y puntual, acostumbrada a recibir y despachar mercadería, revisar facturas de entrada y mantener la bodega limpia y organizada.',
            'Persona trabajadora y cuidadosa con el producto, con experiencia en conteos físicos, rotación de inventario y preparación de pedidos.',
        ], ['Control de inventario', 'Carga y descarga', 'Acomodo de mercadería', 'Despacho de pedidos', 'Uso de montacargas', 'Orden y limpieza', 'Trabajo en equipo']],

        'limpieza' => ['Misceláneo(a) / Limpieza', ['miscel', 'limpi', 'conserj', 'aseo', 'domestic', 'doméstic'], [
            'Persona trabajadora, honrada y detallista, con experiencia en limpieza de oficinas, casas y áreas comunes, y en el uso correcto de productos de limpieza.',
            'Persona puntual y de confianza, acostumbrada a mantener los espacios limpios, ordenados y desinfectados, siguiendo horarios y rutinas.',
            'Persona responsable y discreta, con buena disposición para el trabajo y para apoyar en lo que se necesite.',
        ], ['Limpieza profunda', 'Uso de productos de limpieza', 'Lavado y planchado', 'Orden', 'Honradez', 'Puntualidad', 'Trabajo sin supervisión']],

        'seguridad' => ['Oficial de seguridad / Guarda', ['segurid', 'guarda', 'vigilan', 'custodi'], [
            'Persona disciplinada, alerta y responsable, con experiencia en control de accesos, rondas de vigilancia y reporte de incidentes.',
            'Persona de trato respetuoso y firme, acostumbrada a proteger personas, instalaciones y bienes, y a seguir protocolos de seguridad.',
            'Persona puntual y de total confianza, con disposición para trabajar por turnos y mantener la calma en situaciones difíciles.',
        ], ['Control de accesos', 'Rondas de vigilancia', 'Reporte de incidentes', 'Uso de radio', 'Primeros auxilios', 'Trabajo por turnos', 'Disciplina']],

        'chofer' => ['Chofer / Conductor(a)', ['chofer', 'conduct', 'motoriz', 'transport', 'repart', 'mensaj'], [
            'Persona responsable al volante, con licencia al día y amplio conocimiento de rutas, con experiencia en entrega de mercadería a tiempo.',
            'Persona puntual y prudente, acostumbrada a cuidar el vehículo, revisar sus condiciones antes de salir y atender con amabilidad a los clientes.',
            'Persona de confianza para el manejo de mercadería y dinero de entregas, con buen récord de manejo.',
        ], ['Licencia de conducir al día', 'Conocimiento de rutas', 'Manejo defensivo', 'Entrega de mercadería', 'Cuidado del vehículo', 'Puntualidad', 'Uso de GPS / Waze']],

        'salonero' => ['Salonero(a) / Mesero(a)', ['salon', 'meser', 'bartend', 'barist', 'restaur'], [
            'Persona amable y de buena presencia, con experiencia en atención de mesas, toma de órdenes y servicio al cliente en restaurantes.',
            'Persona rápida y atenta, acostumbrada a trabajar en horas de mucho movimiento, manejar cuentas y mantener las mesas en orden.',
            'Persona con vocación de servicio, que se adapta a horarios rotativos y trabaja muy bien en equipo con cocina y caja.',
        ], ['Atención de mesas', 'Toma de órdenes', 'Servicio al cliente', 'Manejo de cuentas', 'Trabajo bajo presión', 'Buena presencia', 'Inglés básico']],

        'cocina' => ['Cocinero(a) / Ayudante de cocina', ['cocin', 'chef', 'panader', 'pastel', 'reposter'], [
            'Persona creativa y ordenada en la cocina, con experiencia en preparación de alimentos, manejo de inventario de cocina y limpieza del área.',
            'Persona rápida y cuidadosa con la higiene, con conocimientos de manipulación de alimentos y preparación de platos según receta.',
            'Persona trabajadora y en constante aprendizaje, que se adapta al ritmo de la cocina y trabaja muy bien en equipo.',
        ], ['Manipulación de alimentos', 'Preparación de alimentos', 'Higiene y limpieza', 'Control de porciones', 'Trabajo bajo presión', 'Panadería y repostería', 'Trabajo en equipo']],

        'vendedor' => ['Vendedor(a) / Dependiente', ['vend', 'dependient', 'comerci', 'tienda', 'asesor de venta', 'promotor'], [
            'Persona entusiasta y de buen trato, con facilidad de palabra, experiencia en ventas y en lograr metas mensuales.',
            'Persona orientada al cliente, con experiencia en asesoría de productos, acomodo de exhibiciones y manejo de inventario de tienda.',
            'Persona proactiva y perseverante, que sabe escuchar al cliente y ofrecerle lo que necesita.',
        ], ['Ventas', 'Atención al cliente', 'Cumplimiento de metas', 'Exhibición de productos', 'Facilidad de palabra', 'Manejo de caja', 'Negociación']],

        'recepcion' => ['Recepcionista', ['recepci', 'anfitri'], [
            'Persona de excelente presentación y trato cordial, con experiencia en atención de llamadas, agenda de citas y recibimiento de visitantes.',
            'Persona organizada y discreta, acostumbrada a manejar documentos, correspondencia y la agenda de la oficina.',
            'Persona amable y resolutiva, que da una primera impresión positiva de la empresa.',
        ], ['Atención telefónica', 'Agenda de citas', 'Atención al público', 'Manejo de correo', 'Microsoft Office', 'Buena redacción', 'Discreción']],

        'secretaria' => ['Secretario(a) / Asistente administrativo(a)', ['secretar', 'asistente admin', 'administrativ', 'oficinista', 'digitador'], [
            'Persona organizada y eficiente, con experiencia en labores administrativas, archivo de documentos, redacción de correos y manejo de agenda.',
            'Persona responsable y discreta, con buen manejo de Microsoft Office y de la atención a clientes y proveedores.',
            'Persona proactiva y detallista, que apoya a la gerencia en el seguimiento de pendientes y el orden de la oficina.',
        ], ['Microsoft Word', 'Microsoft Excel', 'Archivo y control de documentos', 'Redacción', 'Agenda', 'Atención al cliente', 'Digitación rápida']],

        'contabilidad' => ['Contabilidad / Auxiliar contable', ['contab', 'contad', 'auxiliar contable', 'finanz', 'tesorer', 'factur'], [
            'Persona responsable y analítica, con conocimientos en registros contables, conciliaciones bancarias, cuentas por pagar y declaraciones de impuestos.',
            'Persona ordenada y precisa con los números, con experiencia en facturación electrónica, control de gastos y manejo de Excel.',
            'Persona ética y discreta con la información financiera, con capacidad para cumplir fechas de cierre.',
        ], ['Registros contables', 'Conciliaciones bancarias', 'Cuentas por pagar y cobrar', 'Declaraciones de impuestos', 'Facturación electrónica', 'Microsoft Excel', 'Planillas']],

        'enfermeria' => ['Enfermero(a) / Auxiliar de enfermería', ['enferm', 'auxiliar de salud', 'paramed', 'cuidador de pacient'], [
            'Persona con vocación de servicio y trato humano, con experiencia en el cuidado de pacientes, toma de signos vitales y administración de medicamentos.',
            'Persona responsable y calmada, acostumbrada a trabajar por turnos y a seguir protocolos de higiene y seguridad del paciente.',
            'Persona empática y comprometida con la salud, que trabaja muy bien en equipo con médicos y personal de apoyo.',
        ], ['Toma de signos vitales', 'Administración de medicamentos', 'Primeros auxilios', 'Curaciones', 'Atención al paciente', 'Trabajo por turnos', 'Empatía']],

        'docente' => ['Docente / Maestro(a)', ['docent', 'maestr', 'profes', 'educad', 'tutor'], [
            'Persona paciente y creativa, con experiencia en la enseñanza, planeamiento de clases y evaluación del aprendizaje.',
            'Persona comprometida con la educación, que motiva a sus estudiantes y mantiene buena comunicación con padres de familia.',
            'Persona organizada y en constante actualización, con manejo de herramientas digitales para la enseñanza.',
        ], ['Planeamiento didáctico', 'Manejo de grupo', 'Evaluación', 'Herramientas digitales', 'Paciencia', 'Comunicación con padres', 'Creatividad']],

        'construccion' => ['Construcción / Albañil / Peón', ['albañil', 'albanil', 'constru', 'peon', 'peón', 'maestro de obra', 'carpinter', 'pintor', 'fontaner', 'plomer'], [
            'Persona trabajadora y con buena condición física, con experiencia en obra gris, repellos, enchapes y lectura básica de planos.',
            'Persona responsable y cuidadosa con la seguridad en obra, acostumbrada a trabajar en equipo y cumplir con los tiempos de entrega.',
            'Persona con experiencia en distintas etapas de construcción, que cuida las herramientas y el material de trabajo.',
        ], ['Obra gris', 'Repello y enchape', 'Lectura de planos', 'Carpintería', 'Pintura', 'Fontanería', 'Seguridad en obra']],

        'electricista' => ['Electricista', ['electric'], [
            'Persona responsable y cuidadosa, con experiencia en instalaciones eléctricas residenciales y comerciales, y en reparación de averías.',
            'Persona con conocimientos de normas de seguridad eléctrica, lectura de planos y uso de herramientas de medición.',
            'Persona trabajadora y ordenada, que entrega trabajos de calidad y a tiempo.',
        ], ['Instalaciones residenciales', 'Instalaciones comerciales', 'Reparación de averías', 'Lectura de planos', 'Uso de multímetro', 'Normas de seguridad', 'Paneles eléctricos']],

        'mecanico' => ['Mecánico(a)', ['mecan', 'mecán', 'taller', 'automotr'], [
            'Persona con experiencia en mantenimiento preventivo y correctivo de vehículos, diagnóstico de fallas y cambio de repuestos.',
            'Persona responsable y ordenada en el taller, que cuida las herramientas y entrega los trabajos en el tiempo acordado.',
            'Persona en constante aprendizaje, con buena atención al cliente al explicar los trabajos realizados.',
        ], ['Mantenimiento preventivo', 'Diagnóstico de fallas', 'Frenos y suspensión', 'Cambio de aceite', 'Sistema eléctrico automotriz', 'Soldadura', 'Atención al cliente']],

        'operario' => ['Operario(a) de producción', ['operari', 'producc', 'maquila', 'ensambl', 'fabric', 'planta', 'empaque'], [
            'Persona trabajadora y puntual, con experiencia en líneas de producción, empaque y control de calidad.',
            'Persona atenta a las normas de seguridad e higiene, acostumbrada a cumplir metas de producción y trabajar por turnos.',
            'Persona con buena destreza manual, que aprende rápido nuevos procesos y trabaja muy bien en equipo.',
        ], ['Líneas de producción', 'Empaque', 'Control de calidad', 'Buenas prácticas de manufactura', 'Trabajo por turnos', 'Destreza manual', 'Seguridad industrial']],

        'callcenter' => ['Agente de call center / Servicio al cliente', ['call', 'servicio al cliente', 'agente', 'telemerc', 'soporte'], [
            'Persona con excelente comunicación verbal y escrita, con experiencia en atención telefónica, solución de quejas y venta de servicios.',
            'Persona paciente y amable, acostumbrada a trabajar con metas, sistemas de tickets y horarios rotativos.',
            'Persona bilingüe, con buena actitud y capacidad para mantener la calma con clientes difíciles.',
        ], ['Atención telefónica', 'Inglés conversacional', 'Solución de quejas', 'Manejo de CRM', 'Digitación', 'Ventas por teléfono', 'Paciencia']],

        'ninera' => ['Niñera / Cuidador(a)', ['niñer', 'niner', 'cuidad', 'adulto mayor', 'guarder'], [
            'Persona cariñosa, paciente y de total confianza, con experiencia en el cuidado de niños, preparación de alimentos y apoyo con tareas escolares.',
            'Persona responsable y atenta, con experiencia en el cuidado de adultos mayores, acompañamiento y control de medicamentos.',
            'Persona con valores, puntual y discreta, que crea un ambiente seguro y agradable en el hogar.',
        ], ['Cuidado de niños', 'Cuidado de adultos mayores', 'Primeros auxilios', 'Preparación de alimentos', 'Apoyo con tareas', 'Paciencia', 'Confianza']],

        'agricola' => ['Trabajo agrícola / Jardinería', ['agric', 'campo', 'jardin', 'jardín', 'finca', 'ganad', 'cosech', 'vaquer'], [
            'Persona trabajadora y resistente, con experiencia en labores de campo, siembra, cosecha y mantenimiento de cultivos.',
            'Persona responsable con el cuidado de animales y de las herramientas, acostumbrada a trabajar desde temprano.',
            'Persona con experiencia en mantenimiento de jardines, poda, chapia y uso de motoguadaña.',
        ], ['Siembra y cosecha', 'Mantenimiento de cultivos', 'Cuidado de ganado', 'Uso de motoguadaña', 'Poda y jardinería', 'Aplicación de fertilizantes', 'Resistencia física']],

        'computacion' => ['Técnico(a) en computación / Informática', ['comput', 'informat', 'inform', 'programad', 'desarroll', 'sistemas', 'redes', 'soporte técnico'], [
            'Persona con conocimientos en mantenimiento de computadoras, instalación de programas y solución de problemas técnicos.',
            'Persona analítica y en constante aprendizaje, con experiencia en redes, soporte a usuarios y atención de incidencias.',
            'Persona ordenada y paciente para explicar la tecnología de forma sencilla a cualquier usuario.',
        ], ['Mantenimiento de computadoras', 'Instalación de programas', 'Redes', 'Soporte a usuarios', 'Microsoft Office', 'Programación', 'Solución de problemas']],

        'estilista' => ['Estilista / Belleza', ['estilist', 'belleza', 'barber', 'manicur', 'uñas', 'peluquer', 'maquill'], [
            'Persona creativa y actualizada en tendencias, con experiencia en cortes, tintes, peinados y atención personalizada.',
            'Persona higiénica y detallista, que hace sentir a cada cliente cómodo y bien atendido.',
            'Persona con facilidad para crear clientela fiel gracias a su trato amable y trabajo de calidad.',
        ], ['Cortes de cabello', 'Tintes y mechas', 'Manicure y pedicure', 'Maquillaje', 'Barbería', 'Atención al cliente', 'Higiene']],

        'farmacia' => ['Farmacia / Dependiente de farmacia', ['farmac', 'botica', 'regente'], [
            'Persona responsable y atenta, con experiencia en despacho de medicamentos, acomodo por fechas de vencimiento y atención al cliente.',
            'Persona ordenada, con manejo de inventario y facturación, que sigue con cuidado las indicaciones de las recetas.',
            'Persona amable y discreta con la información de los clientes.',
        ], ['Despacho de medicamentos', 'Control de vencimientos', 'Inventario', 'Facturación', 'Atención al cliente', 'Discreción', 'Manejo de caja']],

        'soldador' => ['Soldador(a) / Metalmecánica', ['soldad', 'metal', 'tornero', 'herrer', 'estructur'], [
            'Persona con experiencia en soldadura eléctrica y autógena, fabricación de estructuras metálicas y lectura de planos.',
            'Persona responsable con la seguridad en el taller y el uso del equipo de protección.',
            'Persona trabajadora, precisa y con acabados de calidad.',
        ], ['Soldadura eléctrica', 'Soldadura MIG / TIG', 'Estructuras metálicas', 'Lectura de planos', 'Corte y esmerilado', 'Seguridad industrial', 'Precisión']],
    ];

    public const HABILIDADES_GENERALES = [
        'Responsabilidad', 'Puntualidad', 'Trabajo en equipo', 'Atención al cliente', 'Honradez',
        'Disposición para aprender', 'Comunicación', 'Trabajo bajo presión', 'Microsoft Office', 'Inglés básico',
        'Licencia de conducir', 'Disponibilidad de horario',
    ];

    public const PERFIL_GENERAL = [
        'Persona responsable, puntual y con muchas ganas de trabajar, con facilidad para aprender rápido y adaptarse a nuevas tareas.',
        'Persona honesta y de buen trato, que trabaja muy bien en equipo y se compromete con los objetivos de la empresa.',
        'Persona organizada y proactiva, con buena actitud ante los retos y deseos de crecer profesionalmente.',
    ];

    /** Datos para el navegador (los botones de "Ideas"). */
    public static function paraJs(): array
    {
        $oficios = [];
        foreach (self::OFICIOS as $k => [$nombre, $claves, $perfiles, $habs]) {
            $oficios[] = ['k' => $k, 'n' => $nombre, 'c' => $claves, 'p' => $perfiles, 'h' => $habs];
        }
        return ['oficios' => $oficios, 'generales' => self::HABILIDADES_GENERALES, 'perfil' => self::PERFIL_GENERAL];
    }
}
