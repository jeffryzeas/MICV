@props(['titulo', 'tab' => 'envios'])
<x-layout :titulo="$titulo" :activo="'postulaciones-'.($tab==='nuevo' ? 'nuevo' : $tab)">
    <div class="gestion postulaciones">
        <nav class="post-nav" aria-label="Postulaciones laborales">
            @foreach(['envios'=>['postulaciones.index','Envíos','mails'],'nuevo'=>['postulaciones.crear','Nuevo envío','send'],'empresas'=>['postulaciones.empresas','Empresas','building-2'],'gmail'=>['postulaciones.gmail','Gmail por cuenta','mail-check']] as $key=>$item)
                <a href="{{ route($item[0]) }}" @if($tab===$key) aria-current="page" @endif><x-icono :name="$item[2]" class="size-4"/>{{ $item[1] }}</a>
            @endforeach
        </nav>
        {{ $slot }}
    </div>
</x-layout>
