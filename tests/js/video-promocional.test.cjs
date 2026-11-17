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
    assert.ok(ass.includes('\\pos(502,1596)'));
});

const { planMontaje, TRANSICION, filtroEscena } = require('../../resources/video-promocional/cine.cjs');
const { seleccionarVoz, argumentosVoz } = require('../../resources/video-promocional/voz.cjs');
test('el montaje conserva la duración al solapar escenas y no corta la narración', () => {
    const escenas = [5, 8, 7].map(duracion => ({duracion}));
    const plan = planMontaje(escenas);
    assert.deepEqual(plan.map(p => p.inicio), [0,4.6,12.2]);
    const totalPiezas = plan.reduce((t,p)=>t+p.conservar,0) + TRANSICION*(plan.length-1);
    const final=plan.at(-1).inicio+escenas.at(-1).duracion;
    assert.ok(Math.abs(final-totalPiezas)<1e-8);
    assert.ok(Math.abs(final-(20-2*TRANSICION))<1e-8);
    for (let i=0;i<plan.length;i++) {
        assert.ok(plan[i].omitir<.55); // La voz comienza después de la parte solapada.
        assert.ok(plan[i].conservar>0);
    }
    assert.throws(()=>planMontaje([{duracion:.5}]),/demasiado corta/);
    assert.match(filtroEscena(0,5),/overlay=.*eval=frame/);
});
test('la voz automática respeta un motor explícito y no oculta un Pocket ausente', async () => {
    assert.equal(await seleccionarVoz({motorVoz:'espeak'}),'espeak');
    assert.equal(await seleccionarVoz({motorVoz:'auto',pocket:'/inexistente'}),'espeak');
    await assert.rejects(seleccionarVoz({motorVoz:'pocket',pocket:'/inexistente'}),/Falta Pocket/);
    await assert.rejects(seleccionarVoz({motorVoz:'desconocido'}),/no reconocido/);
    const texto='Una prueba $(sin ejecutar comandos)';
    const voz=argumentosVoz('pocket',{pocket:'/bin/pocket',cacheVoz:'/cache'},texto,'voz.wav');
    assert.equal(voz.args[voz.args.indexOf('--text')+1],texto);
    assert.equal(voz.env.HF_HOME,'/cache');
    assert.ok(voz.args.includes('spanish'));
    assert.ok(voz.args.includes('lola'));
});
