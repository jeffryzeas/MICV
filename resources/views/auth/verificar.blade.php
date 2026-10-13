<x-publico titulo="Verificar correo">
    <div class="text-center mb-5">
        <div class="size-14 rounded-full bg-blue-50 text-[#0B3C74] flex items-center justify-center mx-auto mb-3"><i data-lucide="mail-check" class="size-7"></i></div>
        <h2 class="font-bold text-lg text-[#061a33]">Verifique su correo</h2>
        <p class="text-sm text-gray-500 mt-1">Escriba el código de 6 dígitos que enviamos a<br><b class="text-gray-700">{{ $user->email }}</b></p>
    </div>

    @if(session('aviso_correo'))
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            No se pudo enviar el correo todavía. {{ session('aviso_correo') }}
            @if(session('codigo_debug'))<div class="mt-2">Modo de prueba (APP_DEBUG): su código es <b class="font-mono text-base">{{ session('codigo_debug') }}</b></div>@endif
        </div>
    @endif

    <form method="POST" action="{{ route('verificar.guardar') }}" class="space-y-4">
        @csrf
        <input type="text" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="000000" pattern="\d{6}" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6); if(this.value.length===6) this.form.requestSubmit();"
               class="w-full text-center tracking-[0.6em] font-mono text-2xl border-2 border-gray-300 rounded-xl px-3 py-3 focus:outline-none focus:border-[#0B3C74]">
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;" data-cargando="Verificando…">Verificar y entrar</button>
    </form>

    <div class="flex items-center justify-between mt-5 text-sm">
        <form method="POST" action="{{ route('verificar.reenviar') }}">@csrf
            <button type="submit" id="btnReenviar" class="font-semibold text-[#0B3C74] underline disabled:text-gray-400 disabled:no-underline" @disabled($espera > 0)>
                Enviar otro código <span id="cuenta">{{ $espera > 0 ? "($espera s)" : '' }}</span>
            </button>
        </form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-gray-500 hover:text-red-600">Salir</button></form>
    </div>
    <p class="text-[11px] text-gray-400 mt-4 text-center">El código vence en 15 minutos. Revise también la carpeta de spam o "Promociones".</p>
    <script>
    (function(){ let s = {{ (int) $espera }}; const b = document.getElementById('btnReenviar'), c = document.getElementById('cuenta');
      if (s <= 0) return; const t = setInterval(function(){ s--; c.textContent = s > 0 ? '(' + s + ' s)' : ''; if (s <= 0) { b.disabled = false; clearInterval(t); } }, 1000); })();
    </script>
</x-publico>
