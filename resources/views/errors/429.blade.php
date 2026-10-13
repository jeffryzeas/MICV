@php
    $seg = (int) ($exception?->getHeaders()['Retry-After'] ?? 60);
    $espera = $seg >= 3600 ? ceil($seg / 3600) . ' hora(s)' : ($seg >= 60 ? ceil($seg / 60) . ' minuto(s)' : $seg . ' segundos');
@endphp
@include('errors._base', ['codigo' => 429, 'icono' => '✋', 'titulo' => 'Demasiados intentos', 'mensaje' => 'Por seguridad, pausamos esta acción un momento. Podrá intentarlo de nuevo en <b>' . e($espera) . '</b>.<br><small>Si usted no hizo tantos intentos, puede que alguien esté probando con su cuenta: cambie su contraseña.</small>'])
