'use strict';
const fs = require('node:fs/promises');
const path = require('node:path');
async function seleccionarVoz(datos) {
    const motor = datos.motorVoz || 'auto';
    if (!['auto','pocket','piper','espeak'].includes(motor)) throw new Error('Motor de voz no reconocido. Use auto, pocket, piper o espeak.');
    let pocket = false;
    if (datos.pocket) pocket = await fs.access(datos.pocket, fs.constants.X_OK).then(() => true, () => false);
    if (motor === 'pocket' || (motor === 'auto' && pocket)) {
        if (!pocket) throw new Error('Falta Pocket TTS. Ejecute bash bin/instalar-video-cine.sh para instalar y probar la voz neuronal.');
        return 'pocket';
    }
    if (motor === 'piper' || (motor === 'auto' && datos.modeloVoz)) {
        if (!datos.modeloVoz) throw new Error('Configure MICV_VIDEO_MODELO_VOZ para usar Piper.');
        await fs.access(datos.modeloVoz);
        return 'piper';
    }
    return 'espeak';
}
function argumentosVoz(motor, datos, texto, wav) {
    if (motor === 'pocket') return { programa: datos.pocket,
        args: ['generate','--language','spanish','--voice','lola','--text',texto,'--output-path',wav,'--quiet'],
        env: { HF_HOME: datos.cacheVoz || path.join(path.dirname(datos.carpeta), 'cache-voz') },
    };
    if (motor === 'piper') return { programa: datos.piper, args: ['-m',datos.modeloVoz,'--length-scale','1.04','-f',wav], entrada: texto+'\n' };
    return { programa: datos.espeak, args: ['-v','es-419+f3','-a','90','-s','152','-w',wav,'--stdin'], entrada: texto };
}
module.exports = { seleccionarVoz, argumentosVoz };
