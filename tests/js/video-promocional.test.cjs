'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { duracionWav, frases, relojAss, subtitulos } = require('../../resources/video-promocional/timeline.cjs');
function wav() {
    const bytes = Buffer.alloc(54 + 48000);
    bytes.write('RIFF'); bytes.writeUInt32LE(bytes.length - 8, 4); bytes.write('WAVE', 8);
    bytes.write('JUNK', 12); bytes.writeUInt32LE(1, 16); // Chunk impar con padding.
    bytes.write('fmt ', 22); bytes.writeUInt32LE(16, 26); bytes.writeUInt32LE(48000, 38);
    bytes.write('data', 46); bytes.writeUInt32LE(48000, 50);
    return bytes;
}
test('la duración real del WAV acepta metadatos y chunks impares', () => {
    assert.equal(duracionWav(wav()), 1);
    assert.throws(() => duracionWav(Buffer.alloc(44)), /WAV válido/);
    assert.throws(() => duracionWav(wav().subarray(0, 54)), /vacío/);
});
test('las frases cubren toda la voz sin superponerse y conservan el texto', () => {
    const texto = 'Entre a micv.sistemajz.com y cree su cuenta. Agregue su experiencia y descargue su currículum en PDF.';
    const eventos = frases(texto, 8.75);
    assert.equal(eventos.map(e => e.texto).join(' '), texto);
    assert.equal(eventos[0].inicio, 0.25);
    assert.ok(Math.abs(eventos.at(-1).fin - 9) < 0.0001);
    eventos.forEach((e, i) => {
        assert.ok(e.fin > e.inicio);
        assert.ok(e.texto.split(' ').length <= 6);
        if (i) assert.equal(e.inicio, eventos[i-1].fin);
    });
    assert.deepEqual(frases('', 2), []);
});
test('ASS redondea el cambio de minuto y neutraliza comandos del contenido', () => {
    assert.equal(relojAss(59.999), '0:01:00.00');
    const ass = subtitulos({ modo: 'datos', voz: 'Texto {\\pos(0,0)} malicioso' }, 4, 5, {x:200,y:100});
    assert.ok(!ass.includes('{\\pos(0,0)}'));
    assert.ok(ass.includes('\\move('));
    assert.ok(ass.includes('\\pos(520,1590)'));
});
