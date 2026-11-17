'use strict';

const fs = require('node:fs/promises');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { duracionWav, subtitulos } = require('./timeline.cjs');
const puppeteer = require('puppeteer');
const { seleccionarVoz, argumentosVoz } = require('./voz.cjs');
const { fondo, dispositivo, esPapel, planMontaje, filtroEscena, TRANSICION } = require('./cine.cjs');

const MIME = {
    '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp',
    '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.json': 'application/json',
};

async function ejecutar(programa, argumentos, carpeta, entrada, progreso, entorno = {}) {
    return new Promise((resolver, rechazar) => {
        const proceso = spawn(programa, argumentos, { cwd: carpeta, env: { ...process.env, ...entorno }, stdio: ['pipe', 'pipe', 'pipe'] });
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
            motor_voz: datos.motorSeleccionado || (datos.modeloVoz ? 'piper' : 'espeak'),
        });
        cadena = cadena.then(async () => {
            const temporal = path.join(path.dirname(datos.carpeta), 'progreso.tmp');
            await fs.writeFile(temporal, contenido, { mode: 0o600 });
            await fs.rename(temporal, path.join(path.dirname(datos.carpeta), 'progreso.json'));
        });
        return cadena;
    };
}

async function captar(pagina, escena, origen, dominio, fotoArchivo) {
    await pagina.bringToFront();
    await pagina.setViewport(esPapel(escena)
        ? { width: 816, height: 1056, deviceScaleFactor: 1 }
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
    const seccion = { foto: 'Foto', datos: 'Perfil', estudios: 'Estudios', experiencia: 'Experiencia' }[escena.modo];
    if (seccion) {
        await pagina.evaluate(titulo => {
            const secciones = [...document.querySelectorAll('.jz-seccion[data-titulo]')];
            const indice = secciones.findIndex(elemento => elemento.dataset.titulo === titulo);
            const enlace = document.querySelector(`[data-indice] [data-i="${indice}"]`);
            if (!enlace) throw new Error(`No se encontró la sección ${titulo}.`);
            enlace.click();
        }, seccion);
    }

    if (escena.modo === 'foto') {
        await pagina.addStyleTag({ content: '.swal2-popup{padding:12px!important}.jz-recorte{max-height:210px!important}.jz-recorte img{max-height:210px!important}.swal2-title{font-size:19px!important;padding:5px!important}.swal2-html-container{margin:8px!important}' });
        const archivo = await pagina.$('#sec-foto input[type=file]');
        if (!archivo) throw new Error('No se encontró el selector de foto.');
        await archivo.uploadFile(fotoArchivo);
        await pagina.waitForSelector('.cropper-container', { visible: true });
    }
    if (escena.modo === 'descarga') {
        await pagina.click('#generar-cv button[onclick*="descargar"]');
        await pagina.waitForSelector('.swal2-popup', { visible: true });
        await pagina.waitForSelector('.swal2-popup input[name="sw_foto"][value="1"]');
    }

    const selector = {
        inicio: 'a[href$="/registro"]',
        foto: '.swal2-confirm',
        registro: '#regPais',
        crear: 'form button[data-cargando]',
        codigo: 'input[name="codigo"]',
        datos: '#sec-perfil textarea',
        estudios: '#bloque-estudios input[name*="[titulo]"]',
        experiencia: '#bloque-experiencias input[name*="[cargo]"]',
        galeria: '#cv-gallery .cv-choice:has([data-choose="pro"])',
        descarga: '.swal2-confirm',
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
                asignar('password', 'SoloUnaDemostracion2026');
                asignar('password_confirmation', 'SoloUnaDemostracion2026');
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
            window.__videoObjetivo = objetivo;
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
        pagina.on('dialog', dialog => dialog.accept());
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
                if (url.pathname === '/clientes/0/foto' && datos.foto) {
                    return await req.respond({ status: 200, contentType: 'image/png', body: Buffer.from(datos.foto.split(',')[1], 'base64') });
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
            await poster.bringToFront();
            await poster.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1 });
            await poster.setContent(fondo(escena, indice, datos.escenas.length, datos), { waitUntil: 'load' });
            await poster.screenshot({ path: path.join(datos.carpeta, `fondo-${indice}.png`) });
            escena.objetivo = await captar(pagina, escena, datos.origen, datos.dominio, datos.fotoArchivo);
            const cantidad = escena.modo === 'foto' ? 18 : ['registro', 'codigo', 'datos', 'estudios', 'experiencia'].includes(escena.modo) ? 18 : 1;
            escena.fotogramas = cantidad;
            for (let paso = 0; paso < cantidad; paso++) {
                await informar('capturas', 3 + (indice + paso / cantidad) / datos.escenas.length * 23, `Capturando: ${escena.titulo}`, indice + 1, false);
                await pagina.evaluate(({fraccion, modo}) => {
                    for (const { elemento, valor } of window.__videoCampos || []) {
                        const orden = window.__videoCampos.findIndex(campo => campo.elemento === elemento);
                        const avance = Math.min(1, Math.max(0, fraccion * window.__videoCampos.length - orden));
                        elemento.value = valor.slice(0, Math.round(valor.length * avance));
                    }
                    if (modo === 'foto' && fraccion >= .65 && !window.__videoFotoConfirmada) {
                        window.__videoFotoConfirmada = true;
                        document.querySelector('.swal2-confirm')?.click();
                    }
                }, {fraccion: cantidad === 1 ? 1 : paso / (cantidad - 1), modo: escena.modo});
                if (escena.modo === 'foto' && paso >= 12) await pagina.waitForSelector('[data-foto-vista] [data-foto-lista]', {visible:true});
                await pagina.bringToFront();
                const captura = await pagina.screenshot({ type: 'png' });
                await poster.bringToFront();
                await poster.setViewport({ width: 900, height: 1100, deviceScaleFactor: 1 });
                await poster.setContent(dispositivo(escena, captura), { waitUntil: 'load', timeout: 20_000 });
                await poster.evaluate(() => document.fonts.ready);
                await poster.screenshot({ path: path.join(datos.carpeta, `escena-${indice}-${paso}.png`), omitBackground: true });
            }
        }
    } finally {
        await navegador.close();
    }
}

async function generar(datos) {
    if (!Array.isArray(datos.escenas) || !datos.escenas.length) throw new Error('No hay escenas para renderizar.');
    datos.motorSeleccionado = await seleccionarVoz(datos);
    const informar = reportero(datos);
    await informar('preparacion', 2, 'Comprobando el motor de voz y las herramientas de video.');
    await ejecutar(datos.ffmpeg, ['-version'], datos.carpeta);
    if (datos.motorSeleccionado === 'espeak') await ejecutar(datos.espeak, ['--version'], datos.carpeta);
    await capturas(datos, informar);
    for (const [indice, escena] of datos.escenas.entries()) {
        await informar('voz', 26 + indice / datos.escenas.length * 16, `Narrando: ${escena.titulo}`, indice+1);
        const wav = `voz-${indice}.wav`;
        const voz = argumentosVoz(datos.motorSeleccionado, datos, escena.voz, wav);
        await ejecutar(voz.programa, voz.args, datos.carpeta, voz.entrada, null, voz.env);
        escena.duracionVoz = duracionWav(await fs.readFile(path.join(datos.carpeta, wav)));
        escena.duracion = Math.ceil((escena.duracionVoz + 1.2) * 30) / 30;
    }
    const codificar = ['-c:v','libx264','-threads','2','-preset','veryfast','-bf','0','-crf','18','-profile:v','high','-pix_fmt','yuv420p',
        '-colorspace','bt709','-color_primaries','bt709','-color_trc','bt709','-r','30','-video_track_timescale','15360'];
    for (const [indice, escena] of datos.escenas.entries()) {
        await informar('render',42+indice/datos.escenas.length*43,`Componiendo escena ${indice+1}: ${escena.titulo}`,indice+1);
        const lista = [];
        const escritura = Math.min(escena.modo === 'foto' ? 5 : 3, escena.duracion * .6);
        for (let paso=0;paso<escena.fotogramas;paso++) {
            lista.push(`file 'escena-${indice}-${paso}.png'`);
            lista.push(`duration ${escena.fotogramas === 1 ? escena.duracion : paso === escena.fotogramas-1 ? escena.duracion-escritura : escritura/(escena.fotogramas-1)}`);
        }
        lista.push(`file 'escena-${indice}-${escena.fotogramas-1}.png'`);
        await fs.writeFile(path.join(datos.carpeta,`imagenes-${indice}.txt`),lista.join('\n')+'\n');
        await fs.writeFile(path.join(datos.carpeta,`subtitulos-${indice}.ass`),subtitulos(escena,escena.duracionVoz,escena.duracion,escena.objetivo));
        await ejecutar(datos.ffmpeg,[
            '-hide_banner','-loglevel','error','-y','-filter_complex_threads','1','-progress','pipe:1','-nostats',
            '-loop','1','-framerate','30','-i',`fondo-${indice}.png`,
            '-f','concat','-safe','1','-i',`imagenes-${indice}.txt`,
            '-filter_complex',filtroEscena(indice,escena.duracion,esPapel(escena)),'-map','[v]','-an','-t',String(escena.duracion),
            '-force_key_frames',`${TRANSICION},${escena.duracion-TRANSICION}`,...codificar,`parte-${indice}.mp4`,
        ],datos.carpeta,null,t=>informar('render',42+(indice+Math.min(1,t/escena.duracion))/datos.escenas.length*43,
            `Animando escena ${indice+1} de ${datos.escenas.length}`,indice+1,false));
    }
    await montar(datos, informar);
}

async function montar(datos, informar) {
    const planes = planMontaje(datos.escenas);
    const codificar = ['-c:v','libx264','-threads','2','-preset','veryfast','-bf','0','-crf','18','-profile:v','high','-pix_fmt','yuv420p',
        '-colorspace','bt709','-color_primaries','bt709','-color_trc','bt709','-r','30','-video_track_timescale','15360'];
    const manifest=[];
    for (const [indice, escena] of datos.escenas.entries()) {
        await informar('exportacion',85+indice/datos.escenas.length*9,`Montando transiciones: ${indice+1} de ${datos.escenas.length}`,indice+1);
        const plan=planes[indice];
        await ejecutar(datos.ffmpeg,['-hide_banner','-loglevel','error','-y','-ss',String(plan.omitir),'-i',`parte-${indice}.mp4`,
            '-t',String(plan.conservar),'-map','0:v:0','-an','-c:v','copy','-avoid_negative_ts','make_zero',`corte-${indice}.mp4`],datos.carpeta);
        manifest.push(`file 'corte-${indice}.mp4'`);
        if (indice<datos.escenas.length-1) {
            const transicion=datos.escenas[indice+1].capitulo === escena.capitulo ? 'fade' : 'smoothleft';
            await ejecutar(datos.ffmpeg,['-hide_banner','-loglevel','error','-y','-filter_complex_threads','1',
                '-ss',String(escena.duracion-TRANSICION),'-i',`parte-${indice}.mp4`,'-i',`parte-${indice+1}.mp4`,
                '-filter_complex',`[0:v]setpts=PTS-STARTPTS,settb=AVTB,fps=30,format=yuv420p[a];[1:v]setpts=PTS-STARTPTS,settb=AVTB,fps=30,format=yuv420p[b];[a][b]xfade=transition=${transicion}:duration=${TRANSICION}:offset=0,format=yuv420p[v]`,
                '-map','[v]','-an','-frames:v',String(TRANSICION*30),...codificar,`transicion-${indice}.mp4`],datos.carpeta);
            manifest.push(`file 'transicion-${indice}.mp4'`);
        }
    }
    await fs.writeFile(path.join(datos.carpeta,'partes.txt'),manifest.join('\n')+'\n');
    const duracion=planes.at(-1).inicio+datos.escenas.at(-1).duracion;
    const salida=['-hide_banner','-loglevel','error','-y','-filter_complex_threads','1','-progress','pipe:1','-nostats','-f','concat','-safe','1','-i','partes.txt'];
    const filtros=[];
    const mezcla=[];
    for (const [indice, escena] of datos.escenas.entries()) {
        salida.push('-i',`voz-${indice}.wav`);
        const delay=Math.round((planes[indice].inicio+.55)*1000);
        filtros.push(`[${indice+1}:a]highpass=f=65,loudnorm=I=-16:TP=-1.5:LRA=9,aresample=48000,adelay=${delay}:all=1[a${indice}]`);
        mezcla.push(`[a${indice}]`);
    }
    if(datos.musica) {
        // Una cama original de acordes y arpegios, sin música descargada ni grabaciones de terceros.
        const indice=datos.escenas.length+1;
        salida.push('-f','lavfi','-i',"aevalsrc=0.004*(sin(2*PI*130.81*t)+0.65*sin(2*PI*196*t)+0.45*sin(2*PI*261.63*t))*(0.65+0.35*sin(2*PI*0.08*t))+0.006*sin(2*PI*(261.63+mod(floor(t*2)\\,4)*65.4)*mod(t\\,0.5))*exp(-7*mod(t\\,0.5))*min(1\\,mod(t\\,0.5)/0.01):s=48000");
        filtros.push(`[${indice}:a]afade=t=in:d=2,afade=t=out:st=${duracion-3}:d=3[m]`);mezcla.push('[m]');
    }
    filtros.push(`${mezcla.join('')}amix=inputs=${mezcla.length}:duration=longest:normalize=0,alimiter=limit=0.95:level=false,afade=t=in:d=0.2,afade=t=out:st=${duracion-.6}:d=0.6[audio]`);
    salida.push('-filter_complex',filtros.join(';'),'-map','0:v:0','-map','[audio]','-c:v','copy','-c:a','aac','-b:a','192k','-ar','48000','-ac','2','-t',String(duracion),'-movflags','+faststart',datos.salida);
    await informar('exportacion',95,'Mezclando narración y ambiente. Preparando el MP4.',datos.escenas.length);
    await ejecutar(datos.ffmpeg,salida,datos.carpeta,null,t=>informar('exportacion',95+Math.min(1,t/duracion)*4,'Finalizando el montaje y el audio.',datos.escenas.length,false));
    await informar('exportacion',99,'Video terminado. Publicando la descarga.',datos.escenas.length);
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
module.exports = { generar, captar, reportero, montar };
