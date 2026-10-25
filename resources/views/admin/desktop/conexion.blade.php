<x-layout titulo="Conexión de escritorio" activo="desktop">
<section class="panel mb-5">
    <h2 class="text-lg font-semibold text-azul-800 mb-3 flex items-center gap-2"><x-icono name="{{ $diagnostico['ready']?'check-circle-2':'settings' }}" class="size-5"/> {{ $diagnostico['ready']?'Firma preparada para la web':'La configuración de conexión necesita atención' }}</h2>
    <p class="text-sm mb-4">{{ $diagnostico['message'] }}</p>
    <p class="text-xs text-gray-500 mb-4">Esta comprobación corresponde al PHP que atiende el sitio. La terminal puede usar otro PHP o usuario. Código: <strong>{{ $diagnostico['code'] }}</strong></p>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div><dt class="label-campo">PHP de esta página</dt><dd>{{ $diagnostico['php_version'] }} · {{ $diagnostico['php_sapi'] }}</dd></div>
        <div><dt class="label-campo">Sodium en la web</dt><dd>{{ $diagnostico['sodium']?'Disponible':'No disponible' }}</dd></div>
        <div class="sm:col-span-2"><dt class="label-campo">Ruta que usa esta instalación</dt><dd class="break-all">{{ $diagnostico['key_path'] ?: 'Sin configurar' }}</dd></div>
        <div><dt class="label-campo">Archivo visible para PHP</dt><dd>{{ $diagnostico['key_visible']?'Sí':'No' }}</dd></div>
        <div><dt class="label-campo">Archivo legible para PHP</dt><dd>{{ $diagnostico['key_readable']?'Sí':'No' }}</dd></div>
        <div><dt class="label-campo">Permisos del archivo</dt><dd>{{ $diagnostico['key_permissions'] ?: 'No disponibles' }}</dd></div>
        <div><dt class="label-campo">ID de usuario PHP / propietario del archivo</dt><dd>{{ $diagnostico['web_user_id'] ?? 'No disponible' }} / {{ $diagnostico['key_owner_id'] ?? 'No disponible' }}</dd></div>
    </dl>
    <div class="flex flex-wrap gap-2 mt-5">
        @if($diagnostico['ready'])<x-ui.button :href="route('admin.desktop.profile')" icon="download">Descargar configuración</x-ui.button>@endif
        <x-ui.button :href="route('admin.desktop.profile',['diagnostico'=>1])" variant="secondary" icon="refresh-cw">Volver a comprobar</x-ui.button>
        <x-ui.button :href="route('admin.desktop.index')" variant="secondary" icon="arrow-left">Volver a licencias</x-ui.button>
    </div>
</section>
</x-layout>
