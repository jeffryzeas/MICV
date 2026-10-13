<style>
.legal h1{font-size:1.5rem;font-weight:800;color:#061a33;margin:0 0 .5rem}
.legal h2{font-size:1.08rem;font-weight:700;color:{{ $cfg->color_primario }};margin:1.6rem 0 .5rem}
.legal p,.legal li{color:#374151;line-height:1.7;font-size:.95rem}
.legal p{margin:.5rem 0}
.legal ol,.legal ul{padding-left:1.4rem;margin:.4rem 0} .legal ol{list-style:decimal} .legal ul{list-style:disc}
.legal a{color:{{ $cfg->color_primario }};text-decoration:underline}
.legal table{width:100%;border-collapse:collapse;font-size:.88rem;margin:.8rem 0;display:block;overflow-x:auto}
.legal th,.legal td{border:1px solid #e5e7eb;padding:8px;text-align:left;vertical-align:top}.legal th{background:#f9fafb}
.legal strong{color:#111827}
.dark .legal h1,.dark .legal strong{color:#f1f5f9}.dark .legal p,.dark .legal li,.dark .legal td{color:#cbd5e1}.dark .legal th{background:#1e293b}
</style>
<nav class="flex gap-2 overflow-x-auto pb-1 mb-4 -mx-1 px-1">
    @foreach(\App\Support\Legal::DOCUMENTOS as $k => [$t, , $ico])
        <a href="{{ route('legal.ver', $k) }}" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-semibold border {{ $k === $doc ? 'bg-azul-800 text-white border-azul-800' : 'bg-white text-gray-600 border-gray-200 hover:border-azul-800' }}">
            <i data-lucide="{{ $ico }}" class="size-3.5"></i> {{ \Illuminate\Support\Str::before($t, ' (') }}
        </a>
    @endforeach
</nav>
<article class="legal bg-white rounded-xl {{ $conMarco ? 'shadow p-5 sm:p-10' : '' }}">{!! $html !!}</article>
<p class="text-xs {{ $conMarco ? 'text-gray-400' : 'text-gray-400' }} mt-4 text-center">Versión {{ $cfg->legal_version }} · <a href="{{ route('eliminar.mostrar') }}" class="underline">Cómo eliminar mi cuenta</a></p>
