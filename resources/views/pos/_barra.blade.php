{{-- Barra superior del punto de venta: el admin elige el Negocio; atajos a las secciones. --}}
@if(auth()->user()->esAdmin() && $cybersPos->count() > 1)
    <form method="GET" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-gray-500">Punto de venta de:</span>
        <select name="cyber" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-semibold text-azul-800">
            @foreach($cybersPos as $cy)<option value="{{ $cy->id }}" @selected($cy->id === $cyberPos->id)>{{ $cy->nombre }}</option>@endforeach
        </select>
    </form>
@endif
