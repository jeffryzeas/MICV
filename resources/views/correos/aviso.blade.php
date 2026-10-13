@extends('correos.base')
@section('cuerpo')
<p style="font-size:16px;margin:0 0 12px">Hola <b>{{ $nombre }}</b>,</p>
@foreach($parrafos as $p)<p style="font-size:15px;margin:0 0 12px">{{ $p }}</p>@endforeach
@endsection
