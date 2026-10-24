@props(['ruta','parametros'=>[]])
<form action="{{ $ruta }}" method="GET" class="export-tools">
@foreach(array_merge(request()->except(['page','formato','orientacion']),$parametros) as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
<label><span>Orientación del reporte</span><select name="orientacion" class="campo"><option value="auto">Automática</option><option value="vertical" @selected(request('orientacion')==='vertical')>Vertical</option><option value="horizontal" @selected(request('orientacion')==='horizontal')>Horizontal</option></select></label>
<button class="btn btn-pri" name="formato" value="pdf"><x-icono name="file-down"/>Exportar PDF</button><button class="btn btn-sec" name="formato" value="excel"><x-icono name="file-spreadsheet"/>Exportar Excel</button>
</form>