@props(['titulo','tab'=>'envios'])
<x-layout :titulo="$titulo" activo="telegram-cv"><div class="gestion postulaciones tgcv">
<nav class="post-nav" aria-label="CV por Telegram">
@foreach(['envios'=>['admin.telegram-cv.index',auth()->user()->esAdmin()?'Envíos del sistema':'Mis envíos','messages-square'],'nuevo'=>['admin.telegram-cv.crear',auth()->user()->esAdmin()?'Nuevo mensaje o CV':'Enviar mi CV','send'],'cuenta'=>['admin.telegram-cv.cuenta','Telegram del sistema','smartphone']] as $key=>$item)@continue($key==='cuenta'&&!auth()->user()->esAdmin())<a href="{{ route($item[0]) }}" @if($tab===$key) aria-current="page" @endif><x-icono :name="$item[2]" class="size-4"/>{{ $item[1] }}</a>@endforeach
</nav>{{ $slot }}</div>@push('scripts')<script src="{{ asset('js/telegram-cv.js') }}?v={{ filemtime(public_path('js/telegram-cv.js')) }}" defer></script>@endpush</x-layout>
