@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm';
    $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1';
    $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5';
    $v = fn ($k) => old($k, $cfg->{$k});
    $metodos = $cfg->metodos ?: \App\Support\PosPaises::metodos();
@endphp
<x-layout titulo="Configuración del punto de venta" activo="pos-config">
    @include('pos._barra')
    <form method="POST" action="{{ route('pos.config.actualizar') }}" class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        @csrf @method('PUT')
        <div class="xl:col-span-2 space-y-5">
            <section class="{{ $card }}">
                <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><x-icono name="globe" class="size-[18px] text-oro-500" /> País, moneda e impuesto</h2>
                <p class="text-xs text-gray-500 mb-4">Al cambiar el país se llenan la moneda, el impuesto y la identificación fiscal. Revise la tasa con su contador: las leyes cambian.</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="col-span-2"><label class="{{ $lb }}">País</label><select name="pais" id="cPais" class="{{ $in }}">
                        @foreach($paises as $cod => $p)<option value="{{ $cod }}" @selected($v('pais') === $cod)>{{ $p['nombre'] }}</option>@endforeach</select></div>
                    <div><label class="{{ $lb }}">Moneda</label><input name="moneda" maxlength="3" value="{{ $v('moneda') }}" class="{{ $in }} uppercase"></div>
                    <div><label class="{{ $lb }}">Símbolo</label><input name="simbolo" maxlength="6" value="{{ $v('simbolo') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Decimales</label><select name="decimales" class="{{ $in }}"><option value="2" @selected($v('decimales') == 2)>2 (ej. 10,50)</option><option value="0" @selected($v('decimales') == 0)>Sin decimales</option></select></div>
                    <div><label class="{{ $lb }}">Nombre del impuesto</label><input name="impuesto_nombre" maxlength="30" value="{{ $v('impuesto_nombre') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Tasa general (%)</label><input name="impuesto_tasa" type="number" min="0" max="100" step="any" value="{{ (float) $v('impuesto_tasa') }}" class="{{ $in }}"></div>
                    <input type="hidden" name="locale" value="{{ $v('locale') }}">
                    <div class="flex items-end"><p class="text-sm text-gray-500">Ejemplo: <b id="ejemplo" class="text-azul-950">{{ $cfg->dinero(1234.5) }}</b></p></div>
                </div>
                <label class="mt-4 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="precios_con_impuesto" value="1" @checked($v('precios_con_impuesto'))> Los precios ya incluyen el impuesto (lo normal en tiendas)</label>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="store" class="size-[18px] text-oro-500" /> Datos del negocio (salen en el comprobante)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="{{ $lb }}">Nombre comercial</label><input name="nombre_comercial" maxlength="120" value="{{ $v('nombre_comercial') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Razón social / dueño</label><input name="razon_social" maxlength="150" value="{{ $v('razon_social') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Tipo de identificación fiscal</label><input name="id_fiscal_tipo" id="cIdTipo" maxlength="40" value="{{ $v('id_fiscal_tipo') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Número</label><input name="id_fiscal" maxlength="40" value="{{ $v('id_fiscal') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Teléfono</label><input name="telefono" maxlength="40" value="{{ $v('telefono') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Correo</label><input name="correo" type="email" maxlength="150" value="{{ $v('correo') }}" class="{{ $in }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lb }}">Dirección</label><input name="direccion" maxlength="200" value="{{ $v('direccion') }}" class="{{ $in }}"></div>
                </div>
                <p class="text-xs text-gray-400 mt-2">El logo es el de <a href="{{ auth()->user()->esAdmin() ? route('admin.cybers.index') : route('marca.edit') }}" class="underline">la marca del Cyber</a>.</p>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="credit-card" class="size-[18px] text-oro-500" /> Formas de pago</h2>
                <div class="space-y-2">
                    @foreach($metodos as $m)
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="metodos[{{ $m['clave'] }}][activo]" value="0">
                            <input type="checkbox" name="metodos[{{ $m['clave'] }}][activo]" value="1" @checked($m['activo']) @disabled($m['clave'] === 'efectivo') class="size-4">
                            <input name="metodos[{{ $m['clave'] }}][nombre]" maxlength="30" value="{{ $m['nombre'] }}" class="{{ $in }} max-w-xs">
                            <span class="text-xs text-gray-400">{{ ['efectivo' => 'siempre activo, da cambio', 'tarjeta' => 'datáfono / POS', 'transferencia' => 'pago desde el celular o banco', 'otro' => 'cualquier otro'][$m['clave']] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="{{ $card }}">
                <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="receipt-text" class="size-[18px] text-oro-500" /> Comprobante</h2>
                <div class="space-y-4">
                    <div><label class="{{ $lb }}">Impresora</label><select name="ancho_tiquete" class="{{ $in }}"><option value="80" @selected($v('ancho_tiquete') == 80)>Térmica de 80 mm (la más común)</option><option value="58" @selected($v('ancho_tiquete') == 58)>Térmica de 58 mm (pequeña)</option></select></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $lb }}">Prefijo</label><input name="prefijo" maxlength="10" value="{{ $v('prefijo') }}" placeholder="Ej: T-" class="{{ $in }}"></div>
                        <div><label class="{{ $lb }}">Siguiente número</label><input name="siguiente_numero" type="number" min="1" value="{{ $v('siguiente_numero') }}" class="{{ $in }}"></div>
                    </div>
                    <div><label class="{{ $lb }}">Mensaje al final</label><input name="pie_tiquete" maxlength="300" value="{{ $v('pie_tiquete') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Aviso legal</label><textarea name="aviso_fiscal" rows="2" maxlength="200" class="{{ $in }}">{{ $v('aviso_fiscal') }}</textarea>
                        <p class="text-[11px] text-gray-400 mt-1">Este comprobante es interno. Donde la ley pide factura electrónica (por ejemplo Costa Rica, México, Guatemala…), hágala con su sistema autorizado.</p></div>
                </div>
            </section>
            <button class="w-full inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold py-3 rounded-xl"><x-icono name="check" class="size-4" /> Guardar configuración</button>
        </div>
    </form>
    @push('scripts')
    <script>
    (function () {
        const D = @json($defectos);
        const f = n => document.querySelector('[name="' + n + '"]');
        function ejemplo() { const dec = +f('decimales').value, loc = f('locale').value || 'es'; try { document.getElementById('ejemplo').textContent = f('simbolo').value + new Intl.NumberFormat(loc, { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(1234.5); } catch (e) {} }
        document.getElementById('cPais').addEventListener('change', async e => {
            const d = D[e.target.value]; if (!d) return;
            const r = await Swal.fire({ title: '¿Usar los valores de este país?', html: `Moneda <b>${d.moneda} (${d.simbolo})</b>, ${d.impuesto_nombre} <b>${d.impuesto_tasa} %</b>, identificación <b>${d.id_fiscal_tipo}</b>.`, showCancelButton: true, confirmButtonText: 'Sí, usarlos', cancelButtonText: 'Solo cambiar el país', confirmButtonColor: '#0B3C74' });
            if (!r.isConfirmed) return;
            f('moneda').value = d.moneda; f('simbolo').value = d.simbolo; f('decimales').value = d.decimales; f('impuesto_nombre').value = d.impuesto_nombre;
            f('impuesto_tasa').value = d.impuesto_tasa; f('locale').value = d.locale; document.getElementById('cIdTipo').value = d.id_fiscal_tipo;
            d.metodos.forEach(m => { const i = f('metodos[' + m.clave + '][nombre]'); if (i) i.value = m.nombre; });
            ejemplo();
        });
        ['simbolo', 'decimales'].forEach(n => f(n).addEventListener('input', ejemplo));
    })();
    </script>
    @endpush
</x-layout>
