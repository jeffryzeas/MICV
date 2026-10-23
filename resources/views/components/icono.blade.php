@props(['name', 'class' => ''])

@php
    $iconName = str_replace('lucide-', '', $name);
    $path = resource_path("svg/{$iconName}.svg");
    
    $svgContent = '';
    if (file_exists($path)) {
        $svgContent = file_get_contents($path);
        $classes = 'jz-icon ' . $class;
        $svgContent = preg_replace('/(<svg\b[^>]*)/i', '$1 class="' . $classes . '" aria-hidden="true" focusable="false"', $svgContent);
    }
@endphp

{!! $svgContent ?: '<!-- Icono no encontrado: ' . $name . ' -->' !!}