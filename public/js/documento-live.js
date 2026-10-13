/**
 * Validación en tiempo real de documento + cédula (versión Laravel).
 * Requiere: #tipoDoc, inputs de cédula o #numeroDoc, #liveStatus,
 * y un <meta name="csrf-token" content="{{ csrf_token() }}"> en el <head>.
 */
(function () {
  const apiUrl = document.body.dataset.apiDocumento || '/api/consultar-documento';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let timer = null;

  function $(sel) { return document.querySelector(sel); }

  function status(el, tipo, html) {
    if (!el) return;
    el.className = 'live-status show ' + tipo;
    el.innerHTML = html;
  }

  async function consultar() {
    return consultarInmediato();
  }

  async function consultarInmediato() {
    const tipo = ($('#tipoDoc') && $('#tipoDoc').value) || 'cedula';
    let numero = '';
    if (tipo === 'cedula' && $('#cedP1')) {
      numero = [$('#cedP1'), $('#cedP2'), $('#cedP3'), $('#cedP4')]
        .map(i => (i && i.value) || '').join('');
    } else {
      numero = ($('#numeroDoc') && $('#numeroDoc').value) || '';
    }
    const live = $('#liveStatus');
    if (!numero) {
      if (live) { live.className = 'live-status'; live.innerHTML = ''; }
      window.__ultimaConsulta = { ok: true, vacio: true };
      return window.__ultimaConsulta;
    }

    status(live, 'info', 'Consultando…');

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);

      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ tipo_documento: tipo, numero_documento: numero }),
        credentials: 'same-origin',
        signal: controller.signal,
      });
      clearTimeout(timeoutId);

      if (!res.ok) {
        const fallo = { ok: false, error: 'Error del servidor (HTTP ' + res.status + '). Revisa que hayas iniciado sesión.' };
        status(live, 'err', fallo.error);
        window.__ultimaConsulta = fallo;
        return fallo;
      }

      const data = await res.json();
      if (!data.ok) {
        status(live, 'err', data.error || 'Error de consulta');
        window.__ultimaConsulta = data;
        return data;
      }
      if (data.vacio) { window.__ultimaConsulta = data; return data; }

      let html = '';
      if (data.cedula) {
        if (data.cedula.valida) {
          html += '<strong>Cédula válida</strong> · '
            + (data.cedula.municipio || '') + ' / ' + (data.cedula.departamento || '')
            + ' · Nac: ' + (data.cedula.fecha_nacimiento || '') + ' · Edad: ' + (data.cedula.edad ?? '—');
        } else {
          html += '<strong>Error: Cédula inválida</strong>: ' + (data.cedula.errores || []).join(' · ');
        }
      }
      if (data.cr) {
        html += data.cr.valida
          ? '<strong>Número válido</strong> · ' + (data.numero_ui || '') + (data.cr.resumen ? ' · ' + data.cr.resumen : '') + (data.cr.aviso ? '<br>Aviso: ' + data.cr.aviso : '')
          : '<strong>Error: Número inválido</strong>: ' + (data.cr.errores || []).join(' · ');
      }
      if (data.existe && data.cliente) {
        html += (html ? '<br>' : '') + '<strong>Aviso: Ya existe en el sistema:</strong> '
          + (data.cliente.nombre_completo || '') + (data.cliente.id ? ' (ID ' + data.cliente.id + ')' : '') + '.';
        status(live, 'warn', html);
      } else if ((data.cedula && !data.cedula.valida) || (data.cr && !data.cr.valida)) {
        status(live, 'err', html);
      } else {
        status(live, (data.cedula && data.cedula.valida) || (data.cr && data.cr.valida) ? 'ok' : 'info', html || 'Documento disponible para registrar.');
      }
      window.__ultimaConsulta = data;
      return data;
    } catch (e) {
      console.error('JZ: fetch falló', e);
      const mensaje = e && e.name === 'AbortError'
        ? 'El servidor tardó demasiado en responder (más de 10s).'
        : 'No se pudo consultar. Revisa la conexión o si la ruta /api/consultar-documento existe.';
      status(live, 'err', mensaje);
      const fallo = { ok: false, error: mensaje };
      window.__ultimaConsulta = fallo;
      return fallo;
    }
  }

  function debounceConsultar() {
    clearTimeout(timer);
    timer = setTimeout(consultar, 400);
  }

  function bindCedulaParts() {
    ['cedP1', 'cedP2', 'cedP3', 'cedP4'].forEach((id, idx) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', () => {
        el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        const max = [3, 6, 4, 1][idx];
        if (el.value.length >= max && idx < 3) {
          const next = document.getElementById(['cedP1', 'cedP2', 'cedP3', 'cedP4'][idx + 1]);
          if (next) next.focus();
        }
        debounceConsultar();
      });
    });
  }

  function bindNumero() {
    const el = document.getElementById('numeroDoc');
    if (!el) return;
    el.addEventListener('input', debounceConsultar);
  }

  function bindTipo() {
    const el = document.getElementById('tipoDoc');
    if (!el) return;
    el.addEventListener('change', () => {
      const cedBox = document.getElementById('cedulaInputs');
      const otroBox = document.getElementById('otroDocInput');
      const extra = document.getElementById('camposExtra');
      if (el.value === 'cedula') {
        if (cedBox) cedBox.style.display = '';
        if (otroBox) otroBox.style.display = 'none';
        if (extra) extra.classList.add('hidden');
      } else {
        if (cedBox) cedBox.style.display = 'none';
        if (otroBox) otroBox.style.display = '';
        if (extra) extra.classList.remove('hidden');
      }
      debounceConsultar();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindCedulaParts();
    bindNumero();
    bindTipo();
  });

  window.JZDocumento = { consultar, consultarInmediato };
})();
