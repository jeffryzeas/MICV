<div class="fila-rep" data-fila>
    <button type="button" class="btn btn-peligro btn-sm quitar" data-quitar title="Quitar"><x-icono n="x"/></button>
    <div class="grid g4">
        <div><label>Cargo</label><input type="text" name="experiencias[{{ $i }}][cargo]" value="{{ $e['cargo'] ?? '' }}" placeholder="Ej. Cajero"></div>
        <div><label>Empresa / lugar</label><input type="text" name="experiencias[{{ $i }}][empresa]" value="{{ $e['empresa'] ?? '' }}"></div>
        <div><label>Desde</label><input type="date" name="experiencias[{{ $i }}][fecha_inicio]" value="{{ $e['fecha_inicio'] ?? '' }}"></div>
        <div><label>Hasta <span class="muted">(vacío = actualidad)</span></label><input type="date" name="experiencias[{{ $i }}][fecha_fin]" value="{{ $e['fecha_fin'] ?? '' }}"></div>
        <div class="spanall"><label>Funciones / logros</label><input type="text" name="experiencias[{{ $i }}][descripcion]" value="{{ $e['descripcion'] ?? '' }}" maxlength="1000"></div>
    </div>
</div>
