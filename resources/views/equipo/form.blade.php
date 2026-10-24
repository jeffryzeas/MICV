<x-layout :titulo="$usuario->exists ? 'Editar cajero' : 'Crear cajero'" activo="equipo">
<form method="POST" action="{{ $usuario->exists ? route('equipo.actualizar',['usuario'=>$usuario,'cyber_id'=>$cyber->id]) : route('equipo.guardar',['cyber_id'=>$cyber->id]) }}" class="bg-white rounded-xl shadow p-5 max-w-3xl">@csrf @if($usuario->exists) @method('PUT') @endif
<p class="mb-4 font-bold">Negocio: {{ $cyber->nombre }}</p><div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
@foreach(['name'=>'Nombre completo','usuario'=>'Usuario de acceso','email'=>'Correo'] as $campo=>$etiqueta)<label class="text-sm">{{ $etiqueta }}<input required type="{{ $campo==='email'?'email':'text' }}" name="{{ $campo }}" value="{{ old($campo,$usuario->$campo) }}" class="w-full border border-gray-300 rounded-lg p-2"></label>@endforeach
@if(auth()->user()->esAdmin())<label class="text-sm">Encargado<select required name="encargado_id" class="w-full border rounded-lg p-2"><option value="">Seleccione</option>@foreach($encargados as $enc)<option value="{{ $enc->id }}" @selected(old('encargado_id',$usuario->encargado_id)==$enc->id)>{{ $enc->name }}</option>@endforeach</select></label>@endif
<label class="text-sm">{{ $usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}<input type="password" name="password" minlength="8" autocomplete="new-password" @if(!$usuario->exists) required @endif class="w-full border rounded-lg p-2"></label>
<label class="text-sm">Repetir contraseña<input type="password" name="password_confirmation" autocomplete="new-password" class="w-full border rounded-lg p-2"></label>
<label class="text-sm"><input type="checkbox" name="activo" value="1" @checked(old('activo',$usuario->activo))> Cuenta activa</label></div>
@include('partials.permisos-selector',['esPersonal'=>true])
<div class="flex gap-3 mt-5"><button class="bg-azul-800 text-white rounded-lg px-4 py-2">Guardar cajero</button><a href="{{ route('equipo.index',['cyber_id'=>$cyber->id]) }}" class="border rounded-lg px-4 py-2">Volver</a></div></form>
</x-layout>
