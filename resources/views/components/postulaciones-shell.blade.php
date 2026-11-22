@props(['titulo', 'tab' => 'envios'])
<x-layout :titulo="$titulo" :activo="'postulaciones-'.($tab==='nuevo' ? 'nuevo' : $tab)">
    <div class="gestion postulaciones">
        <nav class="post-nav" aria-label="Postulaciones laborales">
            @foreach(['envios'=>['postulaciones.index','Envíos'],'nuevo'=>['postulaciones.crear','Nuevo envío'],'empresas'=>['postulaciones.empresas','Empresas'],'gmail'=>['postulaciones.gmail','Gmail por cuenta']] as $key=>$item)
                <a href="{{ route($item[0]) }}" @if($tab===$key) aria-current="page" @endif>{{ $item[1] }}</a>
            @endforeach
        </nav>
        {{ $slot }}
    </div>
</x-layout>
