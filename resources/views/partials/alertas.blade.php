@if (session('ok'))
    <div class="alerta ok">{{ session('ok') }}</div>
@endif
@if ($errors->any())
    <div class="alerta mal">
        <b>Revise lo siguiente:</b>
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
