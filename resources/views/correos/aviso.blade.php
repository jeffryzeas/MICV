@extends('correos.base')
@section('preheader'){{ $parrafos[0] ?? '' }}@endsection
@section('cuerpo')
<p style="font-size:17px;margin:0 0 12px;color:#111827">Hola <b>{{ $nombre }}</b>,</p>
@foreach($parrafos as $p)<p style="margin:0 0 12px;color:#374151">{{ $p }}</p>@endforeach
@if(!empty($boton))@include('correos._boton', ['texto' => $boton[0], 'url' => $boton[1]])@endif
<p style="margin:18px 0 0;color:#374151">Saludos,<br><b>Equipo de {{ $marca }}</b></p>
@endsection
