/* Mi CV — JS del panel (sin dependencias) */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  // Menú en celular
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-menu]')) { document.body.classList.toggle('menu-abierto'); return; }
    if (document.body.classList.contains('menu-abierto') && !e.target.closest('.side')) document.body.classList.remove('menu-abierto');
  });

  // Confirmación antes de enviar
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // ── Repetidores (estudios, experiencias, habilidades, referencias)
  document.querySelectorAll('[data-repetidor]').forEach(function (cont) {
    var nombre = cont.getAttribute('data-repetidor');
    var tpl = document.getElementById('tpl-' + nombre);
    var lista = cont.querySelector('[data-lista]');
    var contador = lista.children.length + 1000;
    cont.querySelector('[data-agregar]').addEventListener('click', function () {
      var html = tpl.innerHTML.replace(/__I__/g, String(contador++));
      var tmp = document.createElement('div');
      tmp.innerHTML = html.trim();
      var nodo = tmp.firstElementChild;
      lista.appendChild(nodo);
      var primero = nodo.querySelector('input,select,textarea');
      if (primero) primero.focus();
    });
    lista.addEventListener('click', function (e) {
      var b = e.target.closest('[data-quitar]');
      if (b) b.closest('[data-fila]').remove();
    });
  });

  // ── Consulta del documento en vivo
  var tipo = document.getElementById('tipo_documento');
  var num = document.getElementById('numero_documento');
  var estado = document.getElementById('estado-documento');
  if (tipo && num && estado) {
    var timer = null;
    var consultar = function () {
      var valor = num.value.trim();
      estado.className = 'campo-estado'; estado.textContent = '';
      if (!valor) return;
      estado.textContent = 'Verificando…';
      fetch(num.getAttribute('data-url'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ tipo: tipo.value, numero: valor, ignorar: num.getAttribute('data-ignorar') || '' })
      }).then(function (r) {
        if (r.status === 419 || r.status === 401) throw new Error('La sesión venció: recargue la página.');
        if (!r.ok) throw new Error('Error ' + r.status + ' al verificar.');
        return r.json();
      }).then(function (d) {
        if (d.existe) {
          estado.className = 'campo-estado mal';
          estado.innerHTML = 'Ya está registrado: <a href="' + d.existe.url + '">' + d.existe.nombre + '</a>';
          return;
        }
        if (!d.valida) { estado.className = 'campo-estado mal'; estado.textContent = (d.errores && d.errores[0]) || 'Documento no válido.'; return; }
        estado.className = 'campo-estado ok';
        estado.textContent = '✔ Documento válido';
        if (d.datos) {
          var info = [];
          Object.keys(d.datos).forEach(function (k) {
            var campo = document.getElementById(k);
            if (campo && d.datos[k] && !campo.value) { campo.value = d.datos[k]; info.push(k); }
          });
          if (d.datos.edad != null) estado.textContent += ' · ' + d.datos.edad + ' años';
          if (d.datos.departamento_nacimiento) estado.textContent += ' · ' + [d.datos.municipio_nacimiento, d.datos.departamento_nacimiento].filter(Boolean).join(', ');
        }
      }).catch(function (err) { estado.className = 'campo-estado mal'; estado.textContent = err.message; });
    };
    num.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(consultar, 500); });
    tipo.addEventListener('change', consultar);
  }

  // Vista previa de la foto antes de subirla
  var inFoto = document.getElementById('foto');
  var prev = document.getElementById('foto-previa');
  if (inFoto && prev) {
    inFoto.addEventListener('change', function () {
      var f = inFoto.files && inFoto.files[0];
      if (!f) return;
      var url = URL.createObjectURL(f);
      prev.innerHTML = '<img src="' + url + '" class="foto" alt="">';
    });
  }

  // ── Generador de CV (ficha del cliente)
  var gen = document.getElementById('generador');
  if (gen) {
    var visor = document.getElementById('visor');
    var bVer = document.getElementById('btn-ver-pdf');
    var bBajar = document.getElementById('btn-bajar-pdf');
    var bloquePaleta = document.getElementById('bloque-paleta');
    var actualizar = function () {
      var diseno = (gen.querySelector('input[name=diseno]:checked') || {}).value || 'pro';
      var paleta = (gen.querySelector('input[name=paleta]:checked') || {}).value || '';
      var foto = gen.querySelector('input[name=foto]').checked ? '1' : '0';
      gen.querySelectorAll('.op-diseno').forEach(function (l) { l.classList.toggle('sel', l.querySelector('input').checked); });
      bloquePaleta.style.display = diseno === 'pro' ? '' : 'none';
      var qs = '?diseno=' + diseno + '&foto=' + foto + (diseno === 'pro' && paleta ? '&paleta=' + paleta : '');
      visor.src = gen.getAttribute('data-vista') + qs;
      bVer.href = gen.getAttribute('data-pdf') + qs;
      bBajar.href = gen.getAttribute('data-pdf') + qs + '&descargar=1';
    };
    gen.addEventListener('change', actualizar);
    actualizar();
  }
})();
