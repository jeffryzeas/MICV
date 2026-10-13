<div class="fila-rep" data-fila>
    <button type="button" class="btn btn-peligro btn-sm quitar" data-quitar title="Quitar"><x-icono n="x"/></button>
    <div class="grid g4">
        <div><label>Nivel</label>
            <select name="estudios[{{ $i }}][nivel]"><option value="">—</option>
                @foreach (\App\Models\ClienteEstudio::NIVELES as $k => $t)<option value="{{ $k }}" @selected(($e['nivel'] ?? '') === $k)>{{ $t }}</option>@endforeach
            </select></div>
        <div class="span2"><label>Título obtenido</label><input type="text" name="estudios[{{ $i }}][titulo]" value="{{ $e['titulo'] ?? '' }}" placeholder="Ej. Bachiller en Ciencias y Letras"></div>
        <div><label>Año</label><input type="number" name="estudios[{{ $i }}][anio]" value="{{ $e['anio'] ?? '' }}" min="1940" max="2100"></div>
        <div class="span2"><label>Institución</label><input type="text" name="estudios[{{ $i }}][institucion]" value="{{ $e['institucion'] ?? '' }}"></div>
        <div class="span2"><label>Detalle (opcional)</label><input type="text" name="estudios[{{ $i }}][detalle]" value="{{ $e['detalle'] ?? '' }}"></div>
    </div>
</div>
