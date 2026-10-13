{{-- reCAPTCHA v3: pone el token en el campo oculto justo antes de enviar. Uso: @include('partials.recaptcha', ['accion' => 'login']) --}}
@php $siteKey = \App\Services\Recaptcha::siteKey(); @endphp
@if($siteKey)
<input type="hidden" name="recaptcha_token" class="jz-recaptcha-token">
<script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
<script>
(function () {
    const campo = document.currentScript.previousElementSibling.previousElementSibling;
    const form = campo.closest('form');
    form.addEventListener('submit', function (e) {
        if (campo.value) return;
        e.preventDefault();
        if (!window.grecaptcha) { form.submit(); return; }
        grecaptcha.ready(function () {
            grecaptcha.execute(@json($siteKey), { action: @json($accion) })
                .then(function (t) { campo.value = t; form.submit(); })
                .catch(function () { form.submit(); });
        });
    });
})();
</script>
@endif
