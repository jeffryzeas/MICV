<x-layout titulo="No se pudo generar el PDF" activo="clientes">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="size-6 text-red-500 shrink-0"></i>
            <div class="text-sm text-gray-700 space-y-2">
                <p>Chromium no pudo crear el PDF. Revise:</p>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Que Node esté instalado y que en la carpeta del proyecto se haya ejecutado <code class="bg-gray-100 px-1 rounded">npm install</code> y <code class="bg-gray-100 px-1 rounded">npx puppeteer browsers install chrome</code>.</li>
                    <li>En Windows: deje vacíos <code class="bg-gray-100 px-1 rounded">MICV_NODE_BINARY</code> y <code class="bg-gray-100 px-1 rounded">MICV_NPM_BINARY</code> en el <code>.env</code> y ejecute <code class="bg-gray-100 px-1 rounded">php artisan config:clear</code>.</li>
                    <li>En el VPS: que estén las librerías de Chrome (<code class="bg-gray-100 px-1 rounded">npx puppeteer browsers install chrome --install-deps</code>).</li>
                </ol>
                <p>Diagnóstico: <code class="bg-gray-100 px-1 rounded">php artisan micv:probar-pdf</code></p>
            </div>
        </div>
        @if (auth()->user()?->esAdmin())
            <details class="mt-4" open><summary class="text-xs font-semibold uppercase text-azul-800 cursor-pointer">Detalle técnico</summary>
                <pre class="mt-2 bg-gray-50 border rounded-lg p-3 text-xs whitespace-pre-wrap break-words">{{ \Illuminate\Support\Str::limit($error, 4000) }}</pre>
            </details>
        @endif
        <a href="{{ url()->previous() }}" class="mt-5 inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="arrow-left" class="size-4"></i> Volver</a>
    </div>
</x-layout>
