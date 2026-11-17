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
function partir(texto, limite = 22) {
    const lineas=[''];
    for (const palabra of String(texto).split(/\s+/)) {
        if (lineas.at(-1) && lineas.at(-1).length + palabra.length + 1 > limite) lineas.push('');
        lineas[lineas.length-1]+=(lineas.at(-1)?' ':'')+palabra;
    }
    return lineas.map(limpiarAss).join('\\N');
}
function subtitulos(escena, voz, duracion, objetivo) {
    const fin = relojAss(duracion);
    const eventos = [
        `Dialogue: 2,0:00:00.00,${fin},Capitulo,,0,0,0,,{\\an7\\move(82,212,82,190,0,650)\\fad(350,150)}${limpiarAss(escena.capitulo || 'GUÍA PASO A PASO')}`,
        `Dialogue: 2,0:00:00.00,${fin},Titulo,,0,0,0,,{\\an7\\move(78,270,78,233,0,700)\\fad(320,180)}${partir(escena.titulo)}`,
        `Dialogue: 2,0:00:00.18,${fin},Guia,,0,0,0,,{\\an7\\move(82,418,82,400,0,600)\\fad(350,180)}${partir(escena.subtitulo,56)}`,
        `Dialogue: 3,0:00:01.20,${relojAss(duracion-.3)},Detalle,,0,0,0,,{\\an5\\move(508,1460,508,1445,0,550)\\fad(350,180)}${limpiarAss(escena.detalle || '')}`,
    ];
    for(const frase of frases(escena.caption ?? escena.voz,voz,.55)) {
        // Resaltado editorial: los tiempos de cada frase se estiman a partir del WAV.
        const texto=limpiarAss(frase.texto).replace(/\b(foto|Lateral|Español|PDF|Perfil|currículum|Guardar|Descargar)\b/gi,'{\\c&H8BC3DF&}$1{\\c&HFFFFFF&}');
        eventos.push(`Dialogue: 4,${relojAss(frase.inicio)},${relojAss(frase.fin)},Voz,,0,0,0,,{\\an5\\pos(502,1596)\\fad(90,90)}${texto}`);
    }
    if(objetivo && !['hero','vista','fin'].includes(escena.modo)) {
        const x=Math.round(102+objetivo.x/420*794);
        const y=Math.round(514+objetivo.y/500*962);
        if(y>535 && y<1445) {
            eventos.push(`Dialogue: 3,0:00:00.70,0:00:03.40,Cursor,,0,0,0,,{\\an7\\move(${x+100},${y+65},${x},${y},0,650)\\fad(180,200)\\p1}m 0 0 l 0 38 11 29 19 46 28 42 19 25 34 25{\\p0}`);
            for(const inicio of [1.4,1.85]) eventos.push(`Dialogue: 2,${relojAss(inicio)},${relojAss(inicio+.7)},Halo,,0,0,0,,{\\an5\\pos(${x+4},${y+10})\\fscx65\\fscy65\\t(0,650,\\fscx180\\fscy180\\alpha&HFF&)\\p1}m -20 0 b -20 -27 20 -27 20 0 b 20 27 -20 27 -20 0{\\p0}`);
        }
    }
    return `[Script Info]\nScriptType: v4.00+\nPlayResX: 1080\nPlayResY: 1920\nWrapStyle: 0\nScaledBorderAndShadow: yes\n\n[V4+ Styles]\nFormat: Name,Fontname,Fontsize,PrimaryColour,SecondaryColour,OutlineColour,BackColour,Bold,Italic,Underline,StrikeOut,ScaleX,ScaleY,Spacing,Angle,BorderStyle,Outline,Shadow,Alignment,MarginL,MarginR,MarginV,Encoding\nStyle: Titulo,DejaVu Sans,74,&H00FFFFFF,&H008BC3DF,&H00201809,&H00000000,-1,0,0,0,100,100,-2,0,1,0,0,7,80,155,0,1\nStyle: Capitulo,DejaVu Sans,18,&H008BC3DF,&H008BC3DF,&H00201809,&H00000000,-1,0,0,0,100,100,2,0,1,0,0,7,80,155,0,1\nStyle: Guia,DejaVu Sans,25,&H00E0D2C1,&H008BC3DF,&H00201809,&H00000000,0,0,0,0,100,100,0,0,1,0,0,7,80,155,0,1\nStyle: Detalle,DejaVu Sans,17,&H008BC3DF,&H008BC3DF,&H00160D03,&H00160D03,-1,0,0,0,100,100,1,0,3,8,0,5,70,155,0,1\nStyle: Voz,DejaVu Sans,42,&H00FFFFFF,&H008BC3DF,&H00162336,&H00000000,-1,0,0,0,100,100,0,0,1,0,0,5,110,185,0,1\nStyle: Cursor,DejaVu Sans,30,&H00FFFFFF,&H00FFFFFF,&H00302415,&H00000000,0,0,0,0,100,100,0,0,1,2,1,7,0,0,0,1\nStyle: Halo,DejaVu Sans,30,&H988BC3DF,&H008BC3DF,&H008BC3DF,&H00000000,0,0,0,0,100,100,0,0,1,1,0,5,0,0,0,1\n\n[Events]\nFormat: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text\n${eventos.join('\n')}\n`;
}
module.exports = { duracionWav, relojAss, frases, subtitulos, limpiarAss, partir };
