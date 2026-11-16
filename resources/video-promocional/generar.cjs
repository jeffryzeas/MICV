'use strict';

const fs = require('node:fs/promises');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const puppeteer = require('puppeteer');

const MIME = {
    '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp',
    '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.json': 'application/json',
};

const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, letra => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[letra]);
const color = (valor, predeterminado) => /^#[0-9a-f]{6}$/i.test(valor) ? valor : predeterminado;

function ejecutar(programa, argumentos, carpeta, entrada) {
    const resultado = spawnSync(programa, argumentos, {
        cwd: carpeta, input: entrada, encoding: 'utf8', timeout: 65_000, maxBuffer: 512 * 1024,
    });

    if (resultado.error || resultado.status !== 0) {
        throw new Error(`${path.basename(programa)}: ${resultado.error?.message ?? resultado.stderr?.trim() ?? resultado.signal}`);
    }
}

async function captar(pagina, escena, origen, dominio) {
    await pagina.setViewport(escena.modo === 'fin'
        ? { width: 810, height: 1056, deviceScaleFactor: 1 }
        : { width: 420, height: 530, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    await pagina.goto(`${origen}/__escena/${escena.archivo}`, { waitUntil: 'load', timeout: 20_000 });
    await pagina.evaluate(() => document.fonts.ready);
    await pagina.evaluate(() => {
        document.querySelector('#jzCookies button')?.click();
        document.activeElement?.blur();
    });

    if (escena.modo === 'galeria') {
        await pagina.click('#cv-open-gallery');
        await pagina.waitForSelector('#cv-gallery[open]', { visible: true });
    }
    const seccion = { datos: 'Perfil', estudios: 'Estudios', experiencia: 'Experiencia' }[escena.modo];
    if (seccion) {
        await pagina.evaluate(titulo => {
            const enlace = [...document.querySelectorAll('[data-indice] button, [data-indice] a')]
                .find(elemento => elemento.textContent.trim() === titulo);
            if (!enlace) throw new Error(`No se encontró la sección ${titulo}.`);
            enlace.click();
        }, seccion);
    }

    const selector = {
        inicio: 'a[href$="/registro"]',
        registro: '#regPais',
        crear: 'form button[data-cargando]',
        codigo: 'input[name="codigo"]',
        datos: '#sec-perfil textarea',
        estudios: '#bloque-estudios input[name*="[titulo]"]',
        experiencia: '#bloque-experiencias input[name*="[cargo]"]',
        galeria: '#cv-gallery .cv-choice:has([data-choose="esencial"])',
        descarga: '#generar-cv button[onclick*="descargar"]',
    }[escena.modo];

    if (selector) {
        await pagina.evaluate(({ selector, modo, dominio }) => {
            const objetivo = document.querySelector(selector);
            if (!objetivo) throw new Error(`No se encontró el control de la escena ${modo}.`);
            const asignar = (nombre, valor) => {
                const campo = document.querySelector(`[name="${nombre}"]`);
                if (campo) campo.value = valor;
            };

            if (modo === 'registro' || modo === 'crear') {
                asignar('primer_nombre', 'Valeria');
                asignar('primer_apellido', 'Mendoza');
                asignar('email', 'valeria.cv@example.com');
            }
            if (modo === 'crear') {
                asignar('password', 'SoloUnaDemostracion2026');
                asignar('password_confirmation', 'SoloUnaDemostracion2026');
                for (const campo of document.querySelectorAll('#regPass, #regPass2')) {
                    campo.dispatchEvent(new Event('input', { bubbles: true }));
                }
                document.querySelector('input[name="acepto"]').checked = true;
            }
            if (modo === 'codigo') asignar('codigo', '000000');

            if (modo === 'inicio') {
                const direccion = document.createElement('div');
                direccion.textContent = dominio;
                direccion.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:100;background:#f5f7fa;color:#14283e;padding:11px 16px;font:600 13px Arial;text-align:center;border-bottom:1px solid #dce4ec';
                document.body.append(direccion);
            }

            objetivo.scrollIntoView({
                block: modo === 'registro' ? 'start' : modo === 'galeria' ? 'nearest' : 'center',
                behavior: 'instant',
            });
            objetivo.style.outline = '3px solid #f4b942';
            objetivo.style.outlineOffset = '2px';
        }, { selector, modo: escena.modo, dominio });
    }

    return pagina.screenshot({ type: 'png' });
}

function tarjeta(escena, indice, total, marca, captura) {
    const primario = color(marca.primario, '#0B3C74');
    const secundario = color(marca.secundario, '#C9971F');
    const final = escena.modo === 'fin';
    const logo = /^data:image\/(png|jpe?g|webp);base64,/i.test(marca.logo ?? '')
        ? `<img class="logo" src="${escapar(marca.logo)}" alt="">`
        : '<span class="logo inicial">CV</span>';
    const pasos = Array.from({ length: total - 1 }, (_, i) =>
        `<span class="${i <= indice ? 'completo' : ''}"></span>`).join('');
    const encabezado = final ? 'EMPIECE HOY' : `PASO ${indice + 1} DE ${total - 1}`;
    return `<!doctype html><html lang="es"><meta charset="utf-8"><style>
    *{box-sizing:border-box}body{margin:0;width:1080px;height:1920px;color:#fff;font-family:Arial,sans-serif;background:
      radial-gradient(ellipse at 90% 11%,${primario} 1%,transparent 56%),
      linear-gradient(154deg,#071526,#0b2440 53%,#081729);}
    .marco{padding:78px 126px 180px 70px;height:100%;position:relative}
    .marca{display:flex;align-items:center;gap:18px;min-width:0;height:64px;font-size:29px;font-weight:700}
    .marca-texto{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .logo{display:block;width:64px;height:64px;border-radius:15px;background:#fff;object-fit:contain;padding:5px}
    .inicial{background:${secundario};color:#081a2e;text-align:center;padding:17px 4px}
    .progreso{display:flex;gap:8px;margin-top:28px}.progreso span{width:43px;height:5px;border-radius:4px;background:#ffffff34}.progreso .completo{background:#f1c65d}
    .paso{margin:27px 0 9px;color:#f1c65d;font-size:24px;font-weight:700;letter-spacing:3px}
    h1{margin:0;font-size:68px;letter-spacing:-3px;line-height:1.03}
    .guia{margin:15px 0 24px;color:#d9e6f4;font-size:32px;line-height:1.2;min-height:76px}
    .pantalla{display:block;width:820px;height:1036px;padding:0;border:8px solid #e6edf6;border-radius:34px;object-fit:cover;background:#fff;box-shadow:0 24px 66px #0007}
    .cv{width:760px;height:990px;object-fit:contain;border-radius:15px}
    .voz{position:absolute;top:1535px;left:70px;right:126px;min-height:126px;padding:21px 24px 20px;border-left:5px solid #f1c65d;
      background:#071423;border-radius:10px;color:#fff;font-size:30px;line-height:1.23;font-weight:600}
    .demo{position:absolute;top:1710px;left:70px;color:#bccbe0;font-size:22px;font-weight:600;letter-spacing:.7px}
    </style><main class="marco">
      <div class="marca">${logo}<span class="marca-texto">${escapar(marca.nombre)}</span></div>
      <div class="progreso">${pasos}</div>
      <p class="paso">${encabezado}</p><h1>${escapar(escena.titulo)}</h1>
      <p class="guia">${escapar(escena.subtitulo)}</p>
      <img class="pantalla ${final ? 'cv' : ''}" src="data:image/png;base64,${Buffer.from(captura).toString('base64')}" alt="">
      <div class="voz">${escapar(escena.caption ?? escena.voz)}</div>
      <div class="demo">Demostración con datos ficticios · Nicaragua</div>
    </main></html>`;
}

async function capturas(datos) {
    const navegador = await puppeteer.launch({
        executablePath: datos.chrome || undefined,
        headless: true,
        pipe: true,
        args: [
            '--disable-gpu', '--disable-dev-shm-usage', '--no-zygote',
            '--disable-background-networking',
            ...(datos.sinSandbox ? ['--no-sandbox'] : []),
        ],
    });

    try {
        const pagina = await navegador.newPage();
        const poster = await navegador.newPage();
        await pagina.setBypassServiceWorker(true);
        await pagina.setRequestInterception(true);
        const publicoReal = await fs.realpath(datos.publico);
        const escenasValidas = new Set(datos.escenas.map(escena => escena.archivo));

        // Chromium solo recibe las escenas y los recursos del proyecto. Nunca navega al sitio público.
        pagina.on('request', async req => {
            try {
                const url = new URL(req.url());
                if (['data:', 'blob:'].includes(url.protocol)) return await req.continue();
                if (url.origin !== datos.origen || req.method() !== 'GET') return await req.abort();

                const nombre = url.pathname.match(/^\/__escena\/([a-z]+)$/)?.[1];
                if (nombre && escenasValidas.has(nombre)) {
                    const body = await fs.readFile(path.join(datos.carpeta, nombre + '.html'));
                    return await req.respond({ status: 200, contentType: 'text/html; charset=utf-8', body });
                }
                if (url.pathname === '/clientes/0/cv/vista') {
                    const body = await fs.readFile(path.join(datos.carpeta, 'vista-curriculum.html'));
                    return await req.respond({ status: 200, contentType: 'text/html; charset=utf-8', body });
                }
                if (url.pathname === '/marca/logo' && /^data:image\/(png|jpe?g|webp);base64,/i.test(datos.logo ?? '')) {
                    const [, contentType, bytes] = datos.logo.match(/^data:([^;]+);base64,(.+)$/);
                    return await req.respond({ status: 200, contentType, body: Buffer.from(bytes, 'base64') });
                }

                const candidato = path.resolve(publicoReal, '.' + decodeURIComponent(url.pathname));
                const real = await fs.realpath(candidato);
                const mime = MIME[path.extname(real).toLowerCase()];
                if (!real.startsWith(publicoReal + path.sep) || !mime) return await req.abort();
                await req.respond({ status: 200, contentType: mime, body: await fs.readFile(real) });
            } catch {
                if (!req.isInterceptResolutionHandled()) await req.abort().catch(() => {});
            }
        });

        await poster.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1 });
        for (const [indice, escena] of datos.escenas.entries()) {
            const captura = await captar(pagina, escena, datos.origen, datos.dominio);
            await poster.setContent(tarjeta(escena, indice, datos.escenas.length, datos, captura), { waitUntil: 'load' });
            await poster.screenshot({ path: path.join(datos.carpeta, `escena-${indice}.png`) });
        }
    } finally {
        await navegador.close();
    }
}

async function generar(datos) {
    await capturas(datos);
    const manifest = [];

    for (const [indice, escena] of datos.escenas.entries()) {
        const wav = `voz-${indice}.wav`;
        const mp4 = `parte-${indice}.mp4`;

        if (datos.modeloVoz) {
            ejecutar(datos.piper, ['-m', datos.modeloVoz, '-f', wav], datos.carpeta, escena.voz + '\n');
        } else {
            ejecutar(datos.espeak, ['-v', 'es-419+f3', '-a', '78', '-s', '155', '-w', wav, '--stdin'], datos.carpeta, escena.voz);
        }
        ejecutar(datos.ffmpeg, [
            '-hide_banner', '-loglevel', 'error', '-y', '-filter_threads', '1',
            '-i', `escena-${indice}.png`, '-i', wav,
            '-vf', "zoompan=z='1+0.018*(1-exp(-on/110))':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=2400:s=1080x1920:fps=30",
            '-af', 'volume=0.83,alimiter=limit=0.88:level=false,apad=pad_dur=0.55', '-shortest', '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast',
            '-crf', '21', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', mp4,
        ], datos.carpeta);
        manifest.push(`file '${mp4}'`);
    }

    await fs.writeFile(path.join(datos.carpeta, 'partes.txt'), manifest.join('\n') + '\n');
    ejecutar(datos.ffmpeg, [
        '-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '1', '-i', 'partes.txt',
        '-c', 'copy', '-movflags', '+faststart', datos.salida,
    ], datos.carpeta);
}

(async () => {
    let entrada = '';
    process.stdin.setEncoding('utf8');
    for await (const bloque of process.stdin) entrada += bloque;
    await generar(JSON.parse(entrada));
})().catch(error => {
    console.error(error.stack ?? error.message);
    process.exitCode = 1;
});
