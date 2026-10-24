#!/usr/bin/env node
/**
 * CV en Word (.docx) editable. Lee un JSON por la entrada estándar y escribe el .docx.
 * Librería: docx (MIT). Uso: node cv-word.cjs <salida.docx> < datos.json
 */
const fs = require('fs');
const {
    Document, Packer, Paragraph, TextRun, ImageRun, Table, TableRow, TableCell, WidthType,
    BorderStyle, AlignmentType, ShadingType, TabStopType, VerticalAlign, LevelFormat, PageOrientation,
} = require('docx');

async function generar() {
const salida = process.argv[2];
const d = JSON.parse(fs.readFileSync(0, 'utf8'));
if(d.ats){await require('./cv-ats-word.cjs')(d,salida);return;}
// Sharp ya es una dependencia del proyecto. Normaliza también WebP y EXIF.
if (d.foto) {
    const sharp = require('sharp');
    const { data, info } = await sharp(Buffer.from(d.foto.datos, 'base64'))
        .rotate()
        .resize({ width: 900, height: 900, fit: 'inside', withoutEnlargement: true })
        .flatten({ background: '#ffffff' })
        .jpeg({ quality: 90 })
        .toBuffer({ resolveWithObject: true });
    d.foto = { tipo: 'jpg', datos: data.toString('base64'), ancho: info.width, alto: info.height };
}
const C1 = (d.color || '#0B3C74').replace('#', '');      // color principal
const C2 = (d.acento || '#C9971F').replace('#', '');     // acento
const GRIS = '555B66', OSCURO = '1F2430';
const FUENTE = 'Calibri';
const sinBorde = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const sinBordes = { top: sinBorde, bottom: sinBorde, left: sinBorde, right: sinBorde, insideHorizontal: sinBorde, insideVertical: sinBorde };

const t = (texto, o = {}) => new TextRun({ text: String(texto ?? ''), font: FUENTE, size: o.size ?? 21, bold: o.bold, italics: o.italics, color: o.color ?? OSCURO, allCaps: o.caps, characterSpacing: o.spacing });
const p = (hijos, o = {}) => new Paragraph({ children: Array.isArray(hijos) ? hijos : [hijos], spacing: { before: o.before ?? 0, after: o.after ?? 60, line: o.line }, alignment: o.align, indent: o.indent, tabStops: o.tabs, keepNext: o.keepNext, numbering: o.numbering, border: o.border });

function titulo(texto) {
    return p([t(texto, { bold: true, size: 23, color: C1, caps: true, spacing: 30 })], {
        before: 260, after: 100, keepNext: true,
        border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: C2, space: 3 } },
    });
}

// Un elemento con fecha a la derecha (experiencia, estudios).
const TAB_DERECHA = [{ type: TabStopType.RIGHT, position: 9900 }];
function item(titular, fecha, sub, detalle) {
    const out = [p([t(titular, { bold: true, size: 22 }), ...(fecha ? [t('\t'), t(fecha, { size: 19, color: C1, bold: true })] : [])], { before: 100, after: 20, tabs: TAB_DERECHA, keepNext: true })];
    if (sub) out.push(p([t(sub, { size: 20, color: C1 })], { after: 30, keepNext: !!detalle }));
    if (detalle) String(detalle).split(/\n+/).filter(Boolean).forEach((linea) => out.push(p([t(linea, { size: 20, color: GRIS })], { after: 40 })));
    return out;
}

// Encabezado: foto a la izquierda (si hay) y nombre, puesto y contacto.
const cabeza = [];
cabeza.push(p([t(d.nombre, { bold: true, size: 44, color: C1 })], { after: 40 }));
if (d.puesto) cabeza.push(p([t(d.puesto, { bold: true, size: 22, color: C2, caps: true, spacing: 40 })], { after: 120 }));
(d.contacto || []).forEach((linea) => cabeza.push(p([t(linea, { size: 19, color: GRIS })], { after: 20 })));

let encabezado;
if (d.foto) {
    const lado = 125; // px ≈ 3,3 cm
    encabezado = new Table({
        width: { size: 100, type: WidthType.PERCENTAGE }, borders: sinBordes,
        rows: [new TableRow({ children: [
            new TableCell({ width: { size: 22, type: WidthType.PERCENTAGE }, borders: sinBordes, verticalAlign: VerticalAlign.CENTER,
                children: [new Paragraph({ children: [new ImageRun({ type: d.foto.tipo, data: Buffer.from(d.foto.datos, 'base64'), transformation: { width: lado, height: Math.round(lado * (d.foto.alto / d.foto.ancho)) } })] })] }),
            new TableCell({ width: { size: 78, type: WidthType.PERCENTAGE }, borders: sinBordes, verticalAlign: VerticalAlign.CENTER, margins: { left: 200 }, children: cabeza }),
        ] })],
    });
} else {
    encabezado = null;
}

const cuerpo = [];
if (encabezado) cuerpo.push(encabezado); else cuerpo.push(...cabeza);
cuerpo.push(p([], { after: 0, border: { bottom: { style: BorderStyle.SINGLE, size: 18, color: C1, space: 6 } } }));

const S = d.secciones;
if (d.perfil) {
    cuerpo.push(titulo(S.perfil));
    cuerpo.push(p([t(d.perfil, { size: 21, color: GRIS })], { align: AlignmentType.JUSTIFIED, after: 60, line: 276 }));
}
if ((d.experiencias || []).length) {
    cuerpo.push(titulo(S.experiencia));
    d.experiencias.forEach((e) => cuerpo.push(...item(e.cargo, e.fecha, e.empresa, e.descripcion)));
}
if ((d.estudios || []).length) {
    cuerpo.push(titulo(S.estudios));
    d.estudios.forEach((e) => cuerpo.push(...item(e.titulo, e.anio, e.sub, e.detalle)));
}
if ((d.habilidades || []).length) {
    cuerpo.push(titulo(S.habilidades));
    // Dos columnas con viñeta, en una tabla sin bordes (se edita fácil).
    const mitad = Math.ceil(d.habilidades.length / 2);
    const col = (lista) => new TableCell({ borders: sinBordes, width: { size: 50, type: WidthType.PERCENTAGE },
        children: lista.length ? lista.map((h) => p([t('■  ', { size: 14, color: C2 }), t(h, { size: 20 })], { after: 40 })) : [p([])] });
    cuerpo.push(new Table({ width: { size: 100, type: WidthType.PERCENTAGE }, borders: sinBordes,
        rows: [new TableRow({ children: [col(d.habilidades.slice(0, mitad)), col(d.habilidades.slice(mitad))] })] }));
}
if ((d.idiomas || []).length) {
    cuerpo.push(titulo(S.idiomas));
    d.idiomas.forEach((x) => cuerpo.push(p([
        t(x.nombre, { bold: true, size: 20 }), t('  —  ' + x.nivel + '   ', { size: 20, color: GRIS }),
        t('●'.repeat(x.puntos), { size: 18, color: C2 }), t('●'.repeat(5 - x.puntos), { size: 18, color: 'D5D9E0' }),
    ], { after: 40 })));
}
if ((d.personales || []).length) {
    cuerpo.push(titulo(S.personales));
    cuerpo.push(new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: { ...sinBordes, insideHorizontal: { style: BorderStyle.SINGLE, size: 2, color: 'E5E7EB' } },
        rows: d.personales.map(([k, v], i) => new TableRow({ children: [
            new TableCell({ width: { size: 34, type: WidthType.PERCENTAGE }, shading: i % 2 ? undefined : { type: ShadingType.CLEAR, fill: 'F5F7FA', color: 'auto' },
                margins: { top: 50, bottom: 50, left: 100 }, children: [p([t(k, { bold: true, size: 19, color: C1 })], { after: 0 })] }),
            new TableCell({ width: { size: 66, type: WidthType.PERCENTAGE }, shading: i % 2 ? undefined : { type: ShadingType.CLEAR, fill: 'F5F7FA', color: 'auto' },
                margins: { top: 50, bottom: 50, left: 100 }, children: [p([t(v, { size: 20 })], { after: 0 })] }),
        ] })),
    }));
}
if ((d.referencias || []).length) {
    cuerpo.push(titulo(S.referencias));
    d.referencias.forEach((r) => {
        cuerpo.push(p([t(r.nombre, { bold: true, size: 21 })], { before: 60, after: 10, keepNext: true }));
        if (r.detalle) cuerpo.push(p([t(r.detalle, { size: 19, color: GRIS })], { after: 40 }));
    });
}
if ((d.familia || []).length) {
    cuerpo.push(titulo(S.familia));
    d.familia.forEach(([k, v]) => cuerpo.push(p([t(k + ': ', { bold: true, size: 20 }), t(v, { size: 20, color: GRIS })], { after: 30 })));
}

const doc = new Document({
    creator: d.autor || 'Mi CV', title: d.titulo || 'CV', description: 'Currículum',
    styles: { default: { document: { run: { font: FUENTE, size: 21 } } } },
    sections: [{
        properties: { page: { size: { width: 12240, height: 15840, orientation: PageOrientation.PORTRAIT }, margin: { top: 1000, bottom: 900, left: 1100, right: 1100 } } },
        children: cuerpo,
    }],
});

const buf = await Packer.toBuffer(doc);
fs.writeFileSync(salida, buf);
console.log(JSON.stringify({ ok: true, bytes: buf.length }));
}

generar().catch((e) => { console.error(e && e.stack || String(e)); process.exit(1); });
