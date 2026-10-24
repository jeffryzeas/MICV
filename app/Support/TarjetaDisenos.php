<?php

namespace App\Support;

/**
 * Catálogo de diseños de tarjetas de presentación (20, por categoría).
 * Cada diseño = un estilo (layout) + colores + íconos de su oficio.
 *   p = color principal · s = acento · f = fondo · t = texto
 */
class TarjetaDisenos
{
    public const CATALOGO = [
        'inversion' => ['Finanzas premium', 'Oficina y negocios', 'elegante', ['p'=>'#172554', 's'=>'#D4AF37', 'f'=>'#172554', 't'=>'#F8FAFC'], ['wallet', 'sparkles', 'map-pin']],
        'artesania' => ['Artesanía', 'Oficios y servicios', 'foto', ['p'=>'#9A3412', 's'=>'#E9C46A', 'f'=>'#FFF7ED', 't'=>'#431407'], ['hammer', 'sparkles', 'map-pin']],
        'marketing' => ['Marketing digital', 'Oficina y negocios', 'oscuro', ['p'=>'#A78BFA', 's'=>'#38BDF8', 'f'=>'#111827', 't'=>'#F9FAFB'], ['chart-line', 'sparkles', 'map-pin']],
        'logistica' => ['Logística', 'Transporte y mandados', 'diagonal', ['p'=>'#1E40AF', 's'=>'#FB923C', 'f'=>'#ffffff', 't'=>'#172554'], ['package', 'sparkles', 'map-pin']],
        'veterinaria' => ['Cuidado animal', 'Profesionales', 'lateral', ['p'=>'#047857', 's'=>'#A7F3D0', 'f'=>'#ffffff', 't'=>'#064E3B'], ['heart', 'sparkles', 'map-pin']],
        'panaderia' => ['Panadería artesanal', 'Belleza, comida y eventos', 'foto', ['p'=>'#92400E', 's'=>'#E8BC7A', 'f'=>'#FFFBEB', 't'=>'#512C16'], ['cake-slice', 'sparkles', 'map-pin']],
        'barberia' => ['Barbería clásica', 'Belleza, comida y eventos', 'elegante', ['p'=>'#292524', 's'=>'#D6AD60', 'f'=>'#292524', 't'=>'#FAF7F2'], ['scissors', 'sparkles', 'map-pin']],
        'odontologia' => ['Odontología', 'Profesionales', 'superior', ['p'=>'#137C8B', 's'=>'#A9E8E2', 'f'=>'#ffffff', 't'=>'#163F48'], ['heart-pulse', 'sparkles', 'map-pin']],
        'arquitectura' => ['Arquitectura', 'Profesionales', 'diagonal', ['p'=>'#263238', 's'=>'#C49A6C', 'f'=>'#FAFAF7', 't'=>'#263238'], ['ruler', 'sparkles', 'map-pin']],
        'consultoria' => ['Consultoría estratégica', 'Oficina y negocios', 'lateral', ['p'=>'#16324F', 's'=>'#D5B26C', 'f'=>'#ffffff', 't'=>'#16324F'], ['briefcase', 'sparkles', 'map-pin']],
        'corporativo'  => ['Corporativo', 'Oficina y negocios', 'lateral', ['p' => '#0B3C74', 's' => '#C9971F', 'f' => '#ffffff', 't' => '#14213d'], ['briefcase', 'building-2', 'chart-line']],
        'mandados'     => ['Mandados y delivery', 'Transporte y mandados', 'diagonal', ['p' => '#F97316', 's' => '#111827', 'f' => '#ffffff', 't' => '#1f2937'], ['motorbike', 'package', 'map-pin']],
        'taxi'         => ['Taxi y transporte', 'Transporte y mandados', 'superior', ['p' => '#FACC15', 's' => '#111111', 'f' => '#ffffff', 't' => '#111111'], ['car-taxi-front', 'car', 'map-pin']],
        'abogado'      => ['Abogado y notario', 'Profesionales', 'elegante', ['p' => '#5B1A2B', 's' => '#D4AF37', 'f' => '#5B1A2B', 't' => '#ffffff'], ['scale', 'gavel', 'landmark']],
        'salud'        => ['Salud', 'Profesionales', 'lateral', ['p' => '#0E9F9A', 's' => '#E0F7F5', 'f' => '#ffffff', 't' => '#0f3d3b'], ['stethoscope', 'heart-pulse', 'cross']],
        'belleza'      => ['Belleza y estética', 'Belleza, comida y eventos', 'foto', ['p' => '#DB2777', 's' => '#F5C2D9', 'f' => '#FFF5F9', 't' => '#4a1230'], ['scissors', 'sparkles', 'heart']],
        'construccion' => ['Construcción', 'Oficios y servicios', 'diagonal', ['p' => '#F5B700', 's' => '#2D2D2D', 'f' => '#ffffff', 't' => '#1c1c1c'], ['hard-hat', 'hammer', 'ruler']],
        'tecnologia'   => ['Tecnología', 'Profesionales', 'oscuro', ['p' => '#22D3EE', 's' => '#A78BFA', 'f' => '#0B1120', 't' => '#E2E8F0'], ['laptop', 'code', 'cpu']],
        'educacion'    => ['Educación y tutorías', 'Profesionales', 'superior', ['p' => '#15803D', 's' => '#FACC15', 'f' => '#ffffff', 't' => '#14301f'], ['graduation-cap', 'book-open', 'pencil']],
        'cocina'       => ['Comida y repostería', 'Belleza, comida y eventos', 'foto', ['p' => '#B91C1C', 's' => '#FDE68A', 'f' => '#FFFBF0', 't' => '#3b0d0d'], ['chef-hat', 'utensils', 'cake-slice']],
        'mecanica'     => ['Mecánica y taller', 'Oficios y servicios', 'oscuro', ['p' => '#EF4444', 's' => '#F59E0B', 'f' => '#18181B', 't' => '#F4F4F5'], ['wrench', 'cog', 'car-front']],
        'limpieza'     => ['Limpieza y hogar', 'Oficios y servicios', 'lateral', ['p' => '#0EA5E9', 's' => '#34D399', 'f' => '#ffffff', 't' => '#0c3550'], ['sparkles', 'spray-can', 'house']],
        'contabilidad' => ['Contabilidad y finanzas', 'Oficina y negocios', 'elegante', ['p' => '#0F4C4C', 's' => '#C9A227', 'f' => '#0F4C4C', 't' => '#ffffff'], ['calculator', 'chart-line', 'wallet']],
        'inmobiliaria' => ['Bienes raíces', 'Oficina y negocios', 'superior', ['p' => '#1E3A8A', 's' => '#D4A017', 'f' => '#ffffff', 't' => '#14213d'], ['house', 'key', 'building-2']],
        'fotografia'   => ['Fotografía y video', 'Belleza, comida y eventos', 'oscuro', ['p' => '#F8FAFC', 's' => '#F43F5E', 'f' => '#0A0A0A', 't' => '#F5F5F5'], ['camera', 'aperture', 'video']],
        'ventas'       => ['Ventas y emprendimiento', 'Oficina y negocios', 'diagonal', ['p' => '#7C3AED', 's' => '#F472B6', 'f' => '#ffffff', 't' => '#2e1065'], ['shopping-bag', 'store', 'tag']],
        'seguridad'    => ['Seguridad', 'Oficios y servicios', 'lateral', ['p' => '#1E293B', 's' => '#DC2626', 'f' => '#ffffff', 't' => '#0f172a'], ['shield-check', 'badge-check', 'key']],
        'agro'         => ['Agro y jardinería', 'Oficios y servicios', 'foto', ['p' => '#3F7D20', 's' => '#A3E635', 'f' => '#F7FBEF', 't' => '#1f3a10'], ['sprout', 'tractor', 'leaf']],
        'eventos'      => ['Música y eventos', 'Belleza, comida y eventos', 'oscuro', ['p' => '#C084FC', 's' => '#FBBF24', 'f' => '#1A0B2E', 't' => '#F3E8FF'], ['music', 'mic', 'party-popper']],
        'turismo'      => ['Turismo y viajes', 'Transporte y mandados', 'superior', ['p' => '#0284C7', 's' => '#FB923C', 'f' => '#ffffff', 't' => '#0c2d48'], ['plane', 'palmtree', 'luggage']],
    ];

    public const LAYOUTS = [
        'lateral'  => 'Franja lateral',
        'superior' => 'Encabezado de color',
        'diagonal' => 'Corte diagonal',
        'elegante' => 'Elegante con marco',
        'oscuro'   => 'Oscuro moderno',
        'foto'     => 'Foto grande',
    ];

    /** Sugerencia de diseño según el oficio que la persona escribió. */
    public const PALABRAS = [
        'mandados' => ['mandad', 'deliver', 'mensaj', 'repart', 'moto', 'encomiend'],
        'taxi' => ['taxi', 'chofer', 'conduct', 'transport', 'uber', 'viaje'],
        'abogado' => ['abogad', 'notari', 'legal', 'jurid', 'licenciad en derecho'],
        'salud' => ['enferm', 'medic', 'salud', 'doctor', 'odont', 'fisio', 'farmac'],
        'belleza' => ['belleza', 'estilis', 'barber', 'uñas', 'manicur', 'maquill', 'peluq'],
        'construccion' => ['constru', 'albañil', 'albanil', 'maestro de obra', 'carpint', 'pintor', 'electric', 'fontaner', 'soldad'],
        'tecnologia' => ['program', 'tecnolog', 'comput', 'sistemas', 'desarroll', 'soporte', 'redes', 'informat'],
        'educacion' => ['docent', 'maestr', 'profes', 'tutor', 'educa'],
        'cocina' => ['cocin', 'chef', 'reposter', 'panader', 'comida', 'pastel'],
        'mecanica' => ['mecan', 'mecán', 'taller', 'automotr'],
        'limpieza' => ['limpie', 'miscel', 'conserj', 'domest', 'aseo'],
        'contabilidad' => ['contab', 'contad', 'finanz', 'auditor', 'factur'],
        'inmobiliaria' => ['bienes raíces', 'bienes raices', 'inmobil', 'corredor'],
        'fotografia' => ['fotogra', 'video', 'camar', 'audiovis'],
        'ventas' => ['vend', 'ventas', 'emprend', 'comerci', 'tienda'],
        'seguridad' => ['segurid', 'guarda', 'vigilan', 'oficial'],
        'agro' => ['agric', 'jardin', 'campo', 'finca', 'ganad'],
        'eventos' => ['music', 'dj', 'event', 'cantant', 'animad'],
        'turismo' => ['turis', 'guía', 'guia', 'hotel', 'agencia de viaj'],
    ];

    public static function sugerido(?string $oficio): string
    {
        $o = mb_strtolower((string) $oficio);
        foreach (self::PALABRAS as $k => $palabras) {
            foreach ($palabras as $p) {
                if ($o !== '' && str_contains($o, $p)) {
                    return $k;
                }
            }
        }
        return 'corporativo';
    }

    /** SVG de un ícono de Lucide (resources/tarjetas/iconos), listo para incrustar. */
    public static function icono(string $nombre): string
    {
        static $cache = [];
        if (!isset($cache[$nombre])) {
            $f = resource_path('tarjetas/iconos/' . basename($nombre) . '.svg');
            $svg = is_file($f) ? file_get_contents($f) : '';
            $svg = preg_replace('/<!--.*?-->/s', '', $svg);
            $svg = preg_replace_callback('/<svg\b[^>]*>/', fn ($m) => preg_replace('/\s(width|height|class)="[^"]*"/', '', $m[0]), $svg, 1);
            $cache[$nombre] = trim($svg);
        }
        return $cache[$nombre];
    }

    public static function categorias(): array
    {
        return array_values(array_unique(array_map(fn ($d) => $d[1], self::CATALOGO)));
    }
}
