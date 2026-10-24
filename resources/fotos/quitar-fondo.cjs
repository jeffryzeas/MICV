#!/usr/bin/env node
/**
 * Quita el fondo de una foto de persona y la pone sobre un color liso.
 * Modelo: U²-Net human segmentation (rembg, licencia Apache-2.0), corrido con
 * onnxruntime-web (WebAssembly): no necesita GPU ni binarios nativos.
 *
 * Uso: node quitar-fondo.cjs <entrada> <salida.png> <modelo.onnx> [#color|transparente]
 */
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const ort = require('onnxruntime-web');

const [entrada, salida, modelo, colorArg = '#ffffff'] = process.argv.slice(2);
if (!entrada || !salida || !modelo) {
    console.error('Uso: quitar-fondo.cjs <entrada> <salida.png> <modelo.onnx> [#color|transparente]');
    process.exit(2);
}
// Parámetros de cada modelo de rembg (tamaño de entrada y normalización).
const nombreModelo = path.basename(modelo).toLowerCase();
const CONF = nombreModelo.includes('birefnet')
    ? { lado: 1024, media: [0.485, 0.456, 0.406], desv: [0.229, 0.224, 0.225], porMax: false, sigmoide: true }
    : nombreModelo.includes('isnet')
        ? { lado: 1024, media: [0.5, 0.5, 0.5], desv: [1, 1, 1], porMax: false, sigmoide: false }
        : { lado: 320, media: [0.485, 0.456, 0.406], desv: [0.229, 0.224, 0.225], porMax: true, sigmoide: false };
const LADO = CONF.lado;
ort.env.wasm.numThreads = Math.max(1, Math.min(4, require('os').cpus().length));
ort.env.wasm.wasmPaths = path.join(path.dirname(require.resolve('onnxruntime-web')), path.sep);

(async () => {
    const img = sharp(entrada, { limitInputPixels: 80_000_000 }).rotate().toColourspace('srgb'); // respeta la orientación EXIF del celular
    const { info: { width: ancho, height: alto } } = await img.clone().toBuffer({ resolveWithObject: true });

    // 1. Entrada del modelo: 320×320, normalizada como en el entrenamiento.
    const { data } = await img.clone().resize(LADO, LADO, { fit: 'fill' }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    let max = 0;
    for (let i = 0; i < data.length; i++) if (data[i] > max) max = data[i];
    max = CONF.porMax ? (max || 1) : 255;
    const { media, desv } = CONF;
    const tensor = new Float32Array(3 * LADO * LADO);
    for (let p = 0; p < LADO * LADO; p++) {
        for (let c = 0; c < 3; c++) {
            tensor[c * LADO * LADO + p] = (data[p * 3 + c] / max - media[c]) / desv[c];
        }
    }

    // 2. Inferencia.
    const sesion = await ort.InferenceSession.create(fs.readFileSync(modelo), { executionProviders: ['wasm'] });
    const res = await sesion.run({ [sesion.inputNames[0]]: new ort.Tensor('float32', tensor, [1, 3, LADO, LADO]) });
    const salidaModelo = res[sesion.outputNames[0]];
    const pred = salidaModelo.data;
    const mh = salidaModelo.dims.at(-2), mw = salidaModelo.dims.at(-1);
    if (!mh || !mw || pred.length !== mh * mw) throw new Error('El modelo no devolvió una máscara de persona válida.');

    // 3. Máscara 0–255 (normalizada) al tamaño original, con borde suave.
    let mn = Infinity, mx = -Infinity;
    if (CONF.sigmoide) {
        for (let i = 0; i < pred.length; i++) pred[i] = 1 / (1 + Math.exp(-pred[i]));
        mn = 0; mx = 1;
    } else {
        for (const v of pred) { if (v < mn) mn = v; if (v > mx) mx = v; }
    }
    const mascara = Buffer.alloc(mw * mh);
    for (let i = 0; i < pred.length; i++) mascara[i] = Math.round(((pred[i] - mn) / ((mx - mn) || 1)) * 255);
    const alfa = await sharp(mascara, { raw: { width: mw, height: mh, channels: 1 } })
        .resize(ancho, alto, { fit: 'fill', kernel: 'cubic' }).blur(0.6).extractChannel(0).raw().toBuffer();

    const rgb = await img.clone().removeAlpha().toColourspace('srgb').raw().toBuffer();
    const rgba = Buffer.alloc(ancho * alto * 4);
    for (let i = 0; i < ancho * alto; i++) {
        rgba[i * 4] = rgb[i * 3]; rgba[i * 4 + 1] = rgb[i * 3 + 1]; rgba[i * 4 + 2] = rgb[i * 3 + 2];
        // Curva: lo casi transparente desaparece, lo casi opaco queda sólido (bordes limpios).
        // Preserve semi-transparent hair edges instead of discarding everything below 30%.
        const t = Math.min(1, Math.max(0, (alfa[i] / 255 - 0.05) / 0.90));
        rgba[i * 4 + 3] = Math.round(255 * t * t * (3 - 2 * t)); // smoothstep
    }
    let out = sharp(rgba, { raw: { width: ancho, height: alto, channels: 4 } });
    if (colorArg !== 'transparente') {
        out = out.flatten({ background: colorArg });
    }
    await out.png({ compressionLevel: 8 }).toFile(salida);
    console.log(JSON.stringify({ ok: true, ancho, alto }));
})().catch((e) => { console.error(e && e.stack || String(e)); process.exit(1); });
