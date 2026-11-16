'use strict';

// El tiempo del video siempre se deriva del audio producido, nunca de una duración fija.
function duracionWav(bytes) {
    if (bytes.length < 44 || bytes.toString('ascii', 0, 4) !== 'RIFF' || bytes.toString('ascii', 8, 12) !== 'WAVE') {
        throw new Error('La voz no produjo un WAV válido.');
    }
    let tasa = 0;
    let longitud = 0;
    for (let pos = 12; pos + 8 <= bytes.length;) {
        const tipo = bytes.toString('ascii', pos, pos + 4);
        const tamano = bytes.readUInt32LE(pos + 4);
        if (tipo === 'fmt ' && tamano >= 16 && pos + 24 <= bytes.length) tasa = bytes.readUInt32LE(pos + 16);
        if (tipo === 'data') longitud += Math.min(tamano, bytes.length - pos - 8);
        pos += 8 + tamano + (tamano % 2);
    }
    if (!tasa || !longitud) throw new Error('El WAV de la narración está vacío.');
    return longitud / tasa;
}
const limpiarAss = texto => String(texto).replace(/\\/g, '／').replace(/[{}]/g, '').replace(/[\r\n]+/g, ' ');
function relojAss(segundos) {
    const centesimas = Math.max(0, Math.round(segundos * 100));
    return `${Math.floor(centesimas / 360000)}:${String(Math.floor(centesimas / 6000) % 60).padStart(2, '0')}:${String(Math.floor(centesimas / 100) % 60).padStart(2, '0')}.${String(centesimas % 100).padStart(2, '0')}`;
}
function frases(texto, duracion, inicio = 0.25) {
    const grupos = [];
    let grupo = '';
    for (const palabra of String(texto).trim().split(/\s+/).filter(Boolean)) {
        if (grupo && (grupo.length + palabra.length > 39 || grupo.split(' ').length >= 6)) {
            grupos.push(grupo); grupo = '';
        }
        grupo += (grupo ? ' ' : '') + palabra;
        if (/[.!?;,:]$/.test(palabra)) { grupos.push(grupo); grupo = ''; }
    }
    if (grupo) grupos.push(grupo);
    const peso = grupos.reduce((suma, frase) => suma + frase.length, 0);
    let cursor = inicio;
    return grupos.map(frase => {
        const fin = cursor + duracion * frase.length / peso;
        const dato = { texto: frase, inicio: cursor, fin };
        cursor = fin;
        return dato;
    });
}
function subtitulos(escena, voz, duracion, objetivo) {
    const eventos = frases(escena.caption ?? escena.voz, voz).map(frase =>
        `Dialogue: 2,${relojAss(frase.inicio)},${relojAss(frase.fin)},Voz,,0,0,0,,{\\fad(90,80)}${limpiarAss(frase.texto)}`);
    // Una guía animada sobre la interfaz; no hace clic ni envía formularios.
    if (objetivo && escena.modo !== 'fin') {
        const x = Math.round(102 + objetivo.x / 420 * 784);
        const y = Math.round(532 + objetivo.y / 500 * 934);
        if (y > 525 && y < 1420) {
            eventos.push(`Dialogue: 1,0:00:00.35,${relojAss(duracion - 0.3)},Cursor,,0,0,0,,{\\an7\\move(${x + 110},${y + 90},${x},${y},350,1100)\\fad(250,200)\\p1}m 0 0 l 0 38 11 29 19 46 28 42 19 25 34 25{\\p0}`);
            eventos.push(`Dialogue: 0,0:00:01.25,0:00:02.25,Cursor,,0,0,0,,{\\an5\\pos(${x + 6},${y + 12})\\bord3\\1a&HFF&\\3c&H69E8FF&\\fscx70\\fscy70\\t(0,950,\\fscx150\\fscy150\\3a&HFF&)\\p1}m -22 0 b -22 -30 22 -30 22 0 b 22 30 -22 30 -22 0{\\p0}`);
        }
    }
    return `[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\nWrapStyle: 0\nScaledBorderAndShadow: yes\n\n[V4+ Styles]\nFormat: Name,Fontname,Fontsize,PrimaryColour,SecondaryColour,OutlineColour,BackColour,Bold,Italic,Underline,StrikeOut,ScaleX,ScaleY,Spacing,Angle,BorderStyle,Outline,Shadow,Alignment,MarginL,MarginR,MarginV,Encoding\nStyle: Voz,DejaVu Sans,43,&H00FFFFFF,&H0069E8FF,&H00162336,&H80000000,-1,0,0,0,100,100,0,0,1,1,0,5,125,165,0,1\nStyle: Cursor,DejaVu Sans,30,&H00FFFFFF,&H00FFFFFF,&H00302415,&H00000000,0,0,0,0,100,100,0,0,1,2,2,7,0,0,0,1\n\n[Events]\nFormat: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text\n${eventos.map(evento => evento.includes(',Voz,') ? evento.replace('{\\fad', '{\\pos(520,1590)\\fad') : evento).join('\n')}\n`;
}
module.exports = { duracionWav, relojAss, frases, subtitulos, limpiarAss };
