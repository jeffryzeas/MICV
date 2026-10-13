<x-layout titulo="SMS JZ · Mensajes y trabajos" activo="chat">
<link rel="stylesheet" href="{{ asset('css/chat-jz.css') }}">
@if(auth()->user()->esAdmin())<a href="{{ route('admin.chat.avisos') }}" class="inline-block mb-3 text-azul-800 font-semibold">🔔 Publicar notificación del sistema →</a>@endif
<section id="jz-chat" class="jzc" data-base="{{ url('mensajes') }}">
<aside class="jzc-sidebar"><header><b>SMS JZ</b><span id="jzc-total">Mensajes y trabajos</span></header><label class="jzc-search">Buscar contacto<input id="jzc-buscar" placeholder="Nombre del cliente o Cyber" autocomplete="off"></label><div id="jzc-contactos" hidden></div><nav id="jzc-lista" aria-label="Conversaciones"></nav></aside>
<div class="jzc-main"><header><button id="jzc-volver" type="button" aria-label="Volver a conversaciones">←</button><div><b id="jzc-nombre">Su espacio de atención</b><small id="jzc-rol">Elija un contacto para conversar y compartir trabajos.</small></div></header>
<div id="jzc-historial" class="jzc-historial" aria-label="Mensajes"><button id="jzc-anteriores" hidden type="button">Cargar anteriores</button><div id="jzc-items"></div><p id="jzc-vacio">Envíe documentos, fotos y mensajes a su Cyber o cliente.</p></div>
<form id="jzc-form" data-sin-cargando><div id="jzc-adjunto" hidden></div><div class="jzc-compose"><label title="Adjuntar archivo" class="jzc-attach">+<input id="jzc-file" type="file" disabled hidden accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx,.pptx"></label><textarea id="jzc-texto" disabled maxlength="6000" rows="1" placeholder="Escriba un mensaje…" aria-label="Mensaje"></textarea><button id="jzc-enviar" type="submit" disabled>Enviar ➤</button></div>
@if(auth()->user()->esAdmin())<label class="jzc-system"><input type="checkbox" id="jzc-sistema"> Enviar como aviso de Sistema JZ</label>@endif
<p id="jzc-estado" role="status">Archivos hasta 20 MB · fotos, PDF, Word, Excel y PowerPoint sin macros.</p></form></div>
</section><dialog id="jzc-viewer"><button id="jzc-cerrar" type="button">Cerrar ✕</button><img id="jzc-imagen" alt="Foto del mensaje"><a id="jzc-descargar" download>Descargar original</a></dialog>
@push('scripts')<script src="{{ asset('js/chat-jz.js') }}" defer></script>@endpush
</x-layout>
