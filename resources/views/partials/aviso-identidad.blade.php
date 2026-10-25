@if($cliente->revisarIdentidad())
<aside class="panel mb-5 text-sm" role="note">
    <p class="font-semibold text-azul-800 flex items-center gap-2"><x-icono name="info" class="size-5 shrink-0"/> Revise su nacionalidad y residencia</p>
    <p class="mt-2 text-gray-600">Son datos distintos del país emisor del documento. Por ejemplo: nacionalidad nicaragüense, documento DIMEX de Costa Rica y residencia actual en Costa Rica. Los registros anteriores pueden contener una nacionalidad sugerida automáticamente.</p>
    <p class="mt-2 text-gray-600">Confirme los datos reales en «Nacimiento y nacionalidad» y «Datos laborales y cualidades». Si son correctos, consérvelos. Si no tiene permiso de edición, solicite la corrección al encargado.</p>
    @if(auth()->user()->esCliente() && \App\Support\EdicionCliente::puede(auth()->user(),'datos'))
        <x-ui.button :href="route('mi-cv.editar').'#sec-nacimiento'" variant="secondary" icon="user-round-pen" class="mt-3">Revisar mis datos</x-ui.button>
    @elseif(!auth()->user()->esCliente() && auth()->user()->tienePermiso('clientes.editar'))
        <x-ui.button :href="route('clientes.edit',$cliente).'#sec-nacimiento'" variant="secondary" icon="user-round-pen" class="mt-3">Revisar datos del cliente</x-ui.button>
    @endif
</aside>
@endif
