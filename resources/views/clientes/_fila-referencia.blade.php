<div class="fila-rep" data-fila>
    <button type="button" class="btn btn-peligro btn-sm quitar" data-quitar title="Quitar"><x-icono n="x"/></button>
    <div class="grid g4">
        <div><label>Tratamiento</label><input type="text" name="referencias[{{ $i }}][tratamiento]" value="{{ $r['tratamiento'] ?? '' }}" placeholder="Lic. / Sr. / Sra."></div>
        <div><label>Nombre y apellido</label><input type="text" name="referencias[{{ $i }}][nombre]" value="{{ $r['nombre'] ?? '' }}"></div>
        <div><label>Teléfono</label><input type="text" name="referencias[{{ $i }}][telefono]" value="{{ $r['telefono'] ?? '' }}"></div>
        <div><label>Correo</label><input type="email" name="referencias[{{ $i }}][correo]" value="{{ $r['correo'] ?? '' }}"></div>
    </div>
</div>
