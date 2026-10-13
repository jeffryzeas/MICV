{{--
    Campo de foto con vista previa, ícono cuando no hay foto y recorte antes de guardar.
    Uso: @include('partials.foto-campo', ['cliente' => $cliente /* o null */])
--}}
@php
    $cliente = $cliente ?? null;
    $tiene = $cliente?->tieneFoto();
@endphp
<div class="flex flex-wrap items-center gap-4" data-foto-campo>
    <div class="relative shrink-0">
        <div data-foto-vista class="size-24 rounded-xl overflow-hidden border-2 {{ $tiene ? 'border-oro-500' : 'border-dashed border-gray-300' }} bg-gray-50 flex items-center justify-center">
            @if($tiene)
                <img src="{{ route('clientes.foto', $cliente) }}" class="w-full h-full object-cover" alt="Foto actual" data-foto-lista>
            @else
                <div class="text-center text-gray-400" data-foto-vacia>
                    <x-icono name="user-round" class="size-10 mx-auto" />
                    <span class="block text-[10px] font-semibold uppercase mt-0.5">Sin foto</span>
                </div>
            @endif
        </div>
    </div>
    <div class="flex-1 min-w-[200px]">
        <p class="text-xs font-semibold uppercase text-azul-800 mb-1.5">Foto tipo carné (opcional)</p>
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 cursor-pointer bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                <x-icono name="image-plus" class="size-4" /> <span>{{ $tiene ? 'Cambiar foto' : 'Elegir foto' }}</span>
                <input type="file" name="foto" accept="image/*" class="sr-only" data-recortar>
            </label>
            @if($tiene)
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 border border-gray-200 rounded-lg px-3 py-2"><input type="checkbox" name="quitar_foto" value="1"> Quitar foto</label>
            @endif
        </div>
        <p class="text-[11px] text-gray-500 mt-1.5">Al elegirla puede encuadrar la cara. Mejor con fondo claro y buena luz.</p>
    </div>
</div>

@once
@push('scripts')
<link rel="stylesheet" href="{{ asset('vendor/cropper.min.css') }}">
<script src="{{ asset('vendor/cropper.min.js') }}"></script>
<style>
.jz-recorte { max-height: 60vh; } .jz-recorte img { display: block; max-width: 100%; }
.jz-recorte .cropper-view-box, .jz-recorte .cropper-face { border-radius: 50%; }
.jz-recorte .cropper-view-box { outline: 2px solid #fff; outline-color: rgba(255,255,255,.9); }
</style>
<script>
(function () {
    // Al elegir una foto se abre un recuadro para encuadrar la cara; la foto
    // recortada reemplaza a la original en el mismo campo (no cambia nada en el servidor).
    document.addEventListener('change', function (e) {
        const input = e.target.closest('input[type=file][data-recortar]');
        if (!input || !input.files || !input.files[0]) return;
        const archivo = input.files[0];
        if (!/^image\//.test(archivo.type)) return;
        const url = URL.createObjectURL(archivo);
        const campo = input.closest('[data-foto-campo]');
        let cropper = null;

        Swal.fire({
            title: 'Encuadre su foto',
            html: '<p class="text-xs text-gray-500 mb-2">Mueva y acerque la imagen para que la cara quede en el centro.</p>'
                + '<div class="jz-recorte"><img id="jzImgRecorte" src="' + url + '" alt=""></div>'
                + '<div class="flex justify-center gap-2 mt-3">'
                + '<button type="button" data-acc="izq" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">⟲ Girar</button>'
                + '<button type="button" data-acc="der" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">Girar ⟳</button>'
                + '<button type="button" data-acc="mas" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">＋</button>'
                + '<button type="button" data-acc="menos" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">－</button></div>',
            width: 600, showCancelButton: true, confirmButtonText: 'Usar esta foto', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0B3C74', allowOutsideClick: false,
            didOpen: function (pop) {
                const img = pop.querySelector('#jzImgRecorte');
                const iniciar = function () {
                    cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 0.9, background: false, responsive: true });
                };
                if (img.complete && img.naturalWidth) iniciar(); else { img.onload = iniciar; img.onerror = function () { Swal.close(); }; }
                pop.addEventListener('click', function (ev) {
                    const b = ev.target.closest('[data-acc]'); if (!b || !cropper) return;
                    ({ izq: () => cropper.rotate(-90), der: () => cropper.rotate(90), mas: () => cropper.zoom(0.1), menos: () => cropper.zoom(-0.1) })[b.dataset.acc]();
                });
            },
            preConfirm: function () {
                if (!cropper) return true;
                return new Promise(function (ok) {
                    cropper.getCroppedCanvas({ width: 900, height: 900, fillColor: '#ffffff', imageSmoothingQuality: 'high' })
                        .toBlob(function (blob) { ok(blob); }, 'image/jpeg', 0.9);
                });
            }
        }).then(function (r) {
            if (cropper) cropper.destroy();
            URL.revokeObjectURL(url);
            if (!r.isConfirmed) { input.value = ''; return; }
            if (r.value instanceof Blob) {
                try {
                    const dt = new DataTransfer();
                    dt.items.add(new File([r.value], 'foto.jpg', { type: 'image/jpeg' }));
                    input.files = dt.files;
                } catch (err) { /* navegador viejo: se envía la original y el servidor la ajusta */ }
            }
            const vista = campo && campo.querySelector('[data-foto-vista]');
            if (vista) {
                vista.classList.remove('border-dashed', 'border-gray-300'); vista.classList.add('border-oro-500');
                vista.innerHTML = '<img class="w-full h-full object-cover" alt="Foto nueva" data-foto-lista src="' + URL.createObjectURL(input.files[0]) + '">';
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
})();
</script>
@endpush
@endonce
