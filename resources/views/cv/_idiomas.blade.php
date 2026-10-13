{{-- Idiomas con nivel y 5 puntos. Uso: @include('cv._idiomas', ['on' => '#C9971F', 'off' => '#ddd', 'texto' => '#222', 'nivel' => '#666']) --}}
<div class="jz-idiomas" @if(!empty($columnas)) style="display:grid;grid-template-columns:repeat({{ (int) $columnas }},1fr);gap:2px 18px" @endif>
    @foreach ($idiomas as $x)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;break-inside:avoid">
            <div style="min-width:0">
                <div style="font-size:9pt;font-weight:600;color:{{ $texto }}">{{ $x->nombre($lang) }}</div>
                <div style="font-size:7.6pt;color:{{ $nivel }}">{{ $x->nivelTexto($lang) }}</div>
            </div>
            <div style="display:flex;gap:3px;flex-shrink:0">
                @for ($i = 1; $i <= 5; $i++)<span style="width:7px;height:7px;border-radius:50%;background:{{ $i <= $x->puntos() ? $on : $off }}"></span>@endfor
            </div>
        </div>
    @endforeach
</div>
