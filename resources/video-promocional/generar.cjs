'use strict';

const fs = require('node:fs/promises');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { duracionWav, subtitulos } = require('./timeline.cjs');
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

async function ejecutar(programa, argumentos, carpeta, entrada, progreso) {
    return new Promise((resolver, rechazar) => {
        const proceso = spawn(programa, argumentos, { cwd: carpeta, stdio: ['pipe', 'pipe', 'pipe'] });
        let errores = '';
        let pendiente = '';
        const limite = setTimeout(() => proceso.kill('SIGKILL'), 120_000);
        proceso.stderr.on('data', bloque => { errores = (errores + bloque).slice(-12000); });
        proceso.stdout.on('data', bloque => {
            pendiente += bloque;
            const lineas = pendiente.split('\n');
            pendiente = lineas.pop();
            for (const linea of lineas) {
                const tiempo = linea.match(/^out_time_us=(\d+)/);
                if (tiempo && progreso) Promise.resolve(progreso(Number(tiempo[1]) / 1_000_000)).catch(error => {
                    errores = error.message;
                    proceso.kill('SIGTERM');
                });
            }
        });
        proceso.on('error', error => { clearTimeout(limite); rechazar(new Error(`${programa}: ${error.message}`)); });
        proceso.on('close', codigo => {
            clearTimeout(limite);
            if (codigo === 0) resolver();
            else rechazar(new Error(`${path.basename(programa)}: ${errores.trim() || 'el proceso se interrumpió o excedió el tiempo límite'}`));
        });
        proceso.stdin.on('error', () => {});
        proceso.stdin.end(entrada ?? '');
    });
}

function reportero(datos) {
    let cadena = Promise.resolve();
    let ultimo = 0;
    let anterior = 0;
    return async (etapa, porcentaje, mensaje, escena = 0, forzar = true) => {
        const ahora = Date.now();
        if (!forzar && ahora - anterior < 800) return;
        anterior = ahora;
        ultimo = Math.min(99, Math.max(ultimo, Math.floor(porcentaje)));
        const contenido = JSON.stringify({ etapa, porcentaje: ultimo, mensaje, escena,
            escenas: datos.escenas.length, actualizado: Math.floor(ahora / 1000),
            motor_voz: datos.modeloVoz ? 'piper' : 'espeak',
        });
        cadena = cadena.then(async () => {
            const temporal = path.join(path.dirname(datos.carpeta), 'progreso.tmp');
            await fs.writeFile(temporal, contenido, { mode: 0o600 });
            await fs.rename(temporal, path.join(path.dirname(datos.carpeta), 'progreso.json'));
        });
        return cadena;
    };
}

async function captar(pagina, escena, origen, dominio) {
    await pagina.bringToFront();
    await pagina.setViewport(escena.modo === 'fin'
        ? { width: 810, height: 1056, deviceScaleFactor: 1 }
        : { width: 420, height: 500, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
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

            objetivo.scrollIntoView({
                block: modo === 'registro' ? 'start' : modo === 'galeria' ? 'nearest' : 'center',
                behavior: 'instant',
            });
            objetivo.style.outline = '3px solid #f4b942';
            objetivo.style.outlineOffset = '2px';
        }, { selector, modo: escena.modo, dominio });
    }

    // Determinar los campos que se escribirán sin disparar envíos ni peticiones al servidor.
    return pagina.evaluate(({ selector, modo }) => {
        const objetivo = selector ? document.querySelector(selector) : null;
        const rect = objetivo?.getBoundingClientRect();
        const campos = modo === 'registro' ? ['[name="primer_nombre"]', '[name="primer_apellido"]', '[name="email"]']
            : modo === 'codigo' ? ['[name="codigo"]']
            : ['datos', 'estudios', 'experiencia'].includes(modo) ? [selector] : [];
        window.__videoCampos = campos.map(campo => {
            const elemento = document.querySelector(campo);
            return elemento ? { elemento, valor: elemento.value } : null;
        }).filter(Boolean);
        return rect ? { x: Math.min(385, Math.max(20, rect.x + Math.min(rect.width * .65, 180))),
            y: Math.min(470, Math.max(25, rect.y + Math.min(rect.height / 2, 30))) } : null;
    }, { selector, modo: escena.modo });
}

function tarjeta(escena, indice, total, marca, captura) {
    const primario = color(marca.primario, '#0B3C74');
    const secundario = color(marca.secundario, '#C9971F');
    const final = escena.modo === 'fin';
    const logo = /^data:image\/(png|jpe?g|webp);base64,/i.test(marca.logo ?? '')
        ? `<img class="logo" src="${escapar(marca.logo)}" alt="">`
        : '<span class="logo inicial">CV</span>';
    const capitulo = indice < 4 ? '01 / CREE SU CUENTA' : indice < 7 ? '02 / CUENTE SU HISTORIA' : '03 / COMPARTA SU TALENTO';
    const pasos = Array.from({ length: total }, (_, i) => `<i class="${i <= indice ? 'hecho' : ''}"></i>`).join('');
    return `<!doctype html><html lang="es"><meta charset="utf-8"><style>
    *{box-sizing:border-box}body{margin:0;width:1080px;height:1920px;overflow:hidden;color:#fff;font-family:'DejaVu Sans',Arial,sans-serif;
      background:radial-gradient(ellipse at 100% 6%,${primario}bd,transparent 55%),linear-gradient(156deg,#061522,#112c45 60%,#07121e)}
    body:before{content:'';position:absolute;left:-240px;top:660px;width:610px;height:610px;border:1px solid #78b7ff21;border-radius:50%;box-shadow:0 0 0 90px #78b7ff05,0 0 0 180px #78b7ff04}
    .marca{position:absolute;top:83px;left:76px;right:136px;display:flex;align-items:center;gap:16px;font-size:28px;font-weight:700;height:60px}
    .marca-texto{max-width:420px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.dominio{margin-left:auto;font-size:18px;color:#bdd0e2;font-weight:400}
    .logo{width:58px;height:58px;border-radius:17px;background:#fff;object-fit:contain;padding:5px}.inicial{display:grid;place-items:center;background:${secundario};color:#081b2b}
    .capitulo{position:absolute;left:76px;top:182px;color:#86e3f2;font-size:20px;letter-spacing:2px;font-weight:700}
    .numero{position:absolute;right:148px;top:162px;color:#ffffff10;font-size:160px;font-weight:700;letter-spacing:-12px;line-height:1}
    h1{position:absolute;left:72px;top:232px;margin:0;width:830px;font-size:70px;line-height:1.07;letter-spacing:-3px;max-height:155px;overflow:hidden}
    .guia{position:absolute;left:76px;top:390px;width:808px;margin:0;font-size:27px;line-height:1.35;color:#c0d4e6}
    .dispositivo{position:absolute;left:90px;top:482px;width:812px;height:996px;padding:10px 12px 12px;border-radius:38px;background:linear-gradient(135deg,#b2c6d7,#34536c 35%,#7994ac);box-shadow:0 24px 80px #0008}
    .navegador{height:40px;border-radius:24px 24px 0 0;background:#f5f8fc;color:#52667c;display:flex;align-items:center;justify-content:center;font-size:17px;letter-spacing:.4px}
    .navegador:before{content:'●';font-size:10px;color:#10a887;margin-right:8px}
    .ventana{overflow:hidden;width:788px;height:934px;background:#fff;border-radius:0 0 25px 25px}
    #captura{display:block;width:100%;height:100%;object-fit:fill}.final .ventana{background:#e6edf5} .final #captura{object-fit:contain}
    .voz-fondo{position:absolute;left:90px;top:1522px;width:812px;height:142px;border:1px solid #ffffff17;border-radius:24px;background:#061421d9}
    .voz-label{position:absolute;left:112px;top:1506px;padding:0 10px;background:#0e2336;color:#86e3f2;font-size:14px;letter-spacing:2px;font-weight:700}
    .pie{position:absolute;left:90px;right:178px;top:1702px;display:flex;align-items:center;justify-content:space-between;color:#9fb6cc;font-size:17px}
    .pasos{position:absolute;left:90px;top:1755px;display:flex;gap:9px;width:812px}.pasos i{height:4px;background:#ffffff20;flex:1;border-radius:5px}.pasos .hecho{background:#86e3f2}
    .cta{position:absolute;left:90px;top:1410px;width:812px;height:68px;border-radius:0 0 26px 26px;display:grid;place-items:center;background:${primario};font-size:26px;font-weight:700;color:#fff}
    </style><body class="${final ? 'final' : ''}">
      <div class="marca">${logo}<span class="marca-texto">${escapar(marca.nombre)}</span><span class="dominio">${escapar(marca.dominio)}</span></div>
      <div class="capitulo">${final ? 'SU SIGUIENTE OPORTUNIDAD' : capitulo}</div><div class="numero">${String(indice + 1).padStart(2, '0')}</div>
      <h1>${escapar(escena.titulo)}</h1><p class="guia">${escapar(escena.subtitulo)}</p>
      <div class="dispositivo"><div class="navegador">${escapar(marca.dominio)}</div><div class="ventana"><img id="captura" src="${captura ? 'data:image/png;base64,' + Buffer.from(captura).toString('base64') : ''}" alt=""></div></div>
      ${final ? `<div class="cta">Cree su cuenta · Haga su CV hoy</div>` : ''}
      <div class="voz-fondo"></div><div class="voz-label">${final ? 'DÉ EL PRIMER PASO' : 'PASO A PASO'}</div>
      <div class="pie"><span>Demostración · Datos ficticios</span><span>${final ? 'LISTO PARA COMPARTIR' : `${String(indice + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`}</span></div>
      <div class="pasos">${pasos}</div>
    </body></html>`;
}

async function capturas(datos, informar) {
    const navegador = await puppeteer.launch({
        executablePath: datos.chrome || undefined,
        headless: true,
        pipe: true,
        args: [
            '--disable-gpu', '--disable-dev-shm-usage', '--no-zygote',
            '--disable-background-networking', '--disable-renderer-backgrounding', '--disable-backgrounding-occluded-windows',
            ...(datos.sinSandbox ? ['--no-sandbox'] : []),
        ],
    });

    try {
        const pagina = await navegador.newPage();
        pagina.setDefaultTimeout(20_000);
        const poster = await navegador.newPage();
        poster.setDefaultTimeout(20_000);
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
            await informar('capturas', 3 + indice / datos.escenas.length * 23, `Preparando pantalla: ${escena.titulo}`, indice + 1);
            escena.objetivo = await captar(pagina, escena, datos.origen, datos.dominio);
            const cantidad = ['registro', 'codigo', 'datos', 'estudios', 'experiencia'].includes(escena.modo) ? 12 : 1;
            escena.fotogramas = cantidad;
            for (let paso = 0; paso < cantidad; paso++) {
                await informar('capturas', 3 + (indice + paso / cantidad) / datos.escenas.length * 23, `Capturando: ${escena.titulo}`, indice + 1, false);
                await pagina.evaluate(fraccion => {
                    for (const { elemento, valor } of window.__videoCampos || []) {
                        const orden = window.__videoCampos.findIndex(campo => campo.elemento === elemento);
                        const avance = Math.min(1, Math.max(0, fraccion * window.__videoCampos.length - orden));
                        elemento.value = valor.slice(0, Math.round(valor.length * avance));
                    }
                }, cantidad === 1 ? 1 : paso / (cantidad - 1));
                await pagina.bringToFront();
                const captura = await pagina.screenshot({ type: 'png' });
                await poster.bringToFront();
                await poster.setContent(tarjeta(escena, indice, datos.escenas.length, datos, captura), { waitUntil: 'load', timeout: 20_000 });
                await poster.evaluate(() => document.fonts.ready);
                await poster.screenshot({ path: path.join(datos.carpeta, `escena-${indice}-${paso}.png`) });
            }
        }
    } finally {
        await navegador.close();
    }
}

async function generar(datos) {
    if (!Array.isArray(datos.escenas) || !datos.escenas.length) throw new Error('No hay escenas para renderizar.');
    const informar = reportero(datos);
    await informar('preparacion', 2, 'Comprobando FFmpeg y el motor de voz.');
    await ejecutar(datos.ffmpeg, ['-version'], datos.carpeta);
    if (datos.modeloVoz) await fs.access(datos.modeloVoz);
    else await ejecutar(datos.espeak, ['--version'], datos.carpeta);
    await capturas(datos, informar);
    const manifest = [];
    for (const [indice, escena] of datos.escenas.entries()) {
        await informar('voz', 26 + indice / datos.escenas.length * 12, `Sintetizando narración: ${escena.titulo}`, indice + 1);
        const wav = `voz-${indice}.wav`;
        if (datos.modeloVoz) {
            await ejecutar(datos.piper, ['-m', datos.modeloVoz, '-f', wav], datos.carpeta, escena.voz + '\n');
        } else {
            await ejecutar(datos.espeak, ['-v', 'es-419+f3', '-a', '90', '-s', '152', '-w', wav, '--stdin'], datos.carpeta, escena.voz);
        }
        escena.duracionVoz = duracionWav(await fs.readFile(path.join(datos.carpeta, wav)));
        // Una pausa de entrada y otra de lectura final, cuantizadas a cuadros completos.
        escena.duracion = Math.ceil((escena.duracionVoz + 0.9) * 30) / 30;
    }
    for (const [indice, escena] of datos.escenas.entries()) {
        const avance = tiempo => informar('render', 38 + (indice + Math.min(1, tiempo / escena.duracion)) / datos.escenas.length * 56,
            `Animando escena ${indice + 1} de ${datos.escenas.length}: ${escena.titulo}`, indice + 1, false);
        await informar('render', 38 + indice / datos.escenas.length * 56, `Animando: ${escena.titulo}`, indice + 1);
        const lista = [];
        const escritura = Math.min(2.5, escena.duracion * .43);
        for (let paso = 0; paso < escena.fotogramas; paso++) {
            lista.push(`file 'escena-${indice}-${paso}.png'`);
            lista.push(`duration ${escena.fotogramas === 1 ? escena.duracion : paso === escena.fotogramas - 1
                ? escena.duracion - escritura : escritura / (escena.fotogramas - 1)}`);
        }
        lista.push(`file 'escena-${indice}-${escena.fotogramas - 1}.png'`);
        await fs.writeFile(path.join(datos.carpeta, `imagenes-${indice}.txt`), lista.join('\n') + '\n');
        await fs.writeFile(path.join(datos.carpeta, `subtitulos-${indice}.ass`), subtitulos(escena, escena.duracionVoz, escena.duracion, escena.objetivo));
        const filtros = [
            'fps=30',
            // Zoom suave: el movimiento se calcula en cada uno de los 30 cuadros, independiente de la escritura.
            "zoompan=z='1+0.012*(1-exp(-on/85))':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=1:s=1080x1920:fps=30",
            `subtitles=subtitulos-${indice}.ass`,
            'fade=t=in:st=0:d=0.22', `fade=t=out:st=${escena.duracion - .22}:d=0.22`, 'format=yuv420p',
        ].join(',');
        await ejecutar(datos.ffmpeg, [
            '-hide_banner', '-loglevel', 'error', '-y', '-filter_threads', '1', '-progress', 'pipe:1', '-nostats',
            '-f', 'concat', '-safe', '1', '-i', `imagenes-${indice}.txt`, '-i', `voz-${indice}.wav`,
            '-vf', filtros, '-af', 'highpass=f=75,lowpass=f=8500,loudnorm=I=-16:TP=-1.5:LRA=9,aresample=48000,adelay=250:all=1,apad',
            '-t', String(escena.duracion), '-r', '30', '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast',
            '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-colorspace', 'bt709', '-color_primaries', 'bt709', '-color_trc', 'bt709',
            '-c:a', 'aac', '-b:a', '192k', '-ar', '48000', '-ac', '2', `parte-${indice}.mp4`,
        ], datos.carpeta, null, avance);
        manifest.push(`file 'parte-${indice}.mp4'`);
    }
    await informar('exportacion', 95, 'Uniendo escenas y preparando el MP4 para reproducción web.', datos.escenas.length);
    await fs.writeFile(path.join(datos.carpeta, 'partes.txt'), manifest.join('\n') + '\n');
    const duracion = datos.escenas.reduce((total, escena) => total + escena.duracion, 0);
    const salida = ['-hide_banner', '-loglevel', 'error', '-y', '-filter_complex_threads', '1', '-progress', 'pipe:1', '-nostats',
        '-f', 'concat', '-safe', '1', '-i', 'partes.txt'];
    if (datos.musica) {
        // Ambiente original sintetizado localmente, sin descargas ni pistas de terceros.
        salida.push('-f', 'lavfi', '-i', 'aevalsrc=0.004*(sin(2*PI*130.81*t)+sin(2*PI*164.81*t)+sin(2*PI*196*t))*(0.7+0.3*sin(2*PI*0.12*t)):s=48000',
            '-filter_complex', `[1:a]afade=t=in:d=2,afade=t=out:st=${Math.max(0, duracion - 3)}:d=3[m];[0:a][m]amix=inputs=2:duration=first:normalize=0,alimiter=limit=0.95:level=false[a]`,
            '-map', '0:v:0', '-map', '[a]', '-c:a', 'aac', '-b:a', '192k', '-ac', '2');
    } else salida.push('-map', '0:v:0', '-map', '0:a:0', '-c:a', 'copy');
    salida.push('-c:v', 'copy', '-movflags', '+faststart', '-t', String(duracion), datos.salida);
    await ejecutar(datos.ffmpeg, salida, datos.carpeta, null, tiempo => informar('exportacion', 95 + Math.min(1, tiempo / duracion) * 4,
        'Finalizando audio y optimizando el MP4.', datos.escenas.length, false));
    await informar('exportacion', 99, 'MP4 generado. Publicando el archivo para descarga.', datos.escenas.length);
}

if (require.main === module) {
    (async () => {
        let entrada = '';
        process.stdin.setEncoding('utf8');
        for await (const bloque of process.stdin) entrada += bloque;
        await generar(JSON.parse(entrada));
    })().catch(error => {
        console.error(error.stack ?? error.message);
        process.exitCode = 1;
    });
}
module.exports = { generar, tarjeta, captar, reportero };
