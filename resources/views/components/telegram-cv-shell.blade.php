@props(['titulo','tab'=>'envios'])
<x-layout :titulo="$titulo" activo="telegram-cv"><div class="gestion postulaciones tgcv">
<nav class="post-nav" aria-label="Cuenta personal de Telegram">
@foreach(['envios'=>['admin.telegram-cv.index','Envíos e historial','messages-square'],'nuevo'=>['admin.telegram-cv.crear','Nuevo mensaje o CV','send'],'cuenta'=>['admin.telegram-cv.cuenta','Mi número de Telegram','smartphone']] as $key=>$item)<a href="{{ route($item[0]) }}" @if($tab===$key) aria-current="page" @endif><x-icono :name="$item[2]" class="size-4"/>{{ $item[1] }}</a>@endforeach
</nav>{{ $slot }}</div>@push('scripts')<script src="{{ asset('js/telegram-cv.js') }}?v={{ filemtime(public_path('js/telegram-cv.js')) }}" defer></script>@endpush</x-layout>
