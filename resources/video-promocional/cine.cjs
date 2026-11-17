'use strict';
const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, letra => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[letra]);
const color = (v,d) => /^#[0-9a-f]{6}$/i.test(v) ? v : d;
const esPapel = escena => ['hero','vista','fin'].includes(escena.modo);
const TRANSICION = 0.4;
function fondo(escena, indice, total, marca) {
    const primario=color(marca.primario,'#0B3C74');
    const logo=/^data:image\/(png|jpe?g|webp);base64,/i.test(marca.logo||'') ? `<img src="${escapar(marca.logo)}">` : '<b>CV</b>';
    return `<!doctype html><html><meta charset="utf-8"><style>
    *{box-sizing:border-box}body{margin:0;width:1080px;height:1920px;overflow:hidden;font-family:'DejaVu Sans',sans-serif;color:white;background:radial-gradient(ellipse at 95% 5%,${primario}cf,transparent 54%),radial-gradient(ellipse at 5% 70%,#145b6f50,transparent 49%),linear-gradient(155deg,#050e18,#0a1e32 62%,#050d15)}
    .circulo{position:absolute;width:800px;height:800px;border:1px solid #8cc6dc13;border-radius:50%;left:-530px;top:620px;box-shadow:0 0 0 100px #8cc6dc05,0 0 0 220px #8cc6dc03}
    .linea{position:absolute;left:80px;right:155px;top:157px;height:1px;background:linear-gradient(90deg,#d4b16c77,#d4b16c00)}
    .marca{position:absolute;left:80px;right:155px;top:82px;display:flex;align-items:center;gap:15px;font-size:26px;font-weight:700}
    .marca b,.marca img{display:grid;place-items:center;width:52px;height:52px;border-radius:15px;background:#e3c48a;color:#091c30;object-fit:contain;padding:5px}
    .dominio{font-size:17px;margin-left:auto;color:#b2c5d8;font-weight:400}.numero{position:absolute;top:190px;right:146px;font-size:158px;line-height:1;color:#ffffff08;font-weight:700;letter-spacing:-10px}
    .subtitulos{position:absolute;left:80px;top:1510px;width:845px;height:170px;background:#030d16c9;border:1px solid #e0b86b26;border-radius:25px;box-shadow:0 16px 40px #0002}
    .voz{position:absolute;left:110px;top:1498px;background:#0a1e32;padding:0 12px;font-size:14px;letter-spacing:3px;font-weight:bold;color:#dfc38b}
    .pie{position:absolute;top:1715px;left:84px;right:158px;display:flex;justify-content:space-between;color:#94acbf;font-size:16px}.pista{position:absolute;top:1770px;left:84px;width:840px;height:3px;border-radius:2px;background:#ffffff16}.pista i{display:block;width:${(indice+1)/total*100}%;height:3px;background:#d8bc80;border-radius:2px}
    .cine{position:absolute;left:84px;top:1830px;color:#547083;font-size:11px;letter-spacing:3px}
    </style><body><div class="circulo"></div><div class="marca">${logo}<span>${escapar(marca.nombre)}</span><span class="dominio">${escapar(marca.dominio)}</span></div><div class="linea"></div><div class="numero">${String(indice+1).padStart(2,'0')}</div>
    <div class="subtitulos"></div><div class="voz">GUÍA PASO A PASO</div><div class="pie"><span>Perfil y foto ficticios · Demostración</span><span>${String(indice+1).padStart(2,'0')} / ${total}</span></div><div class="pista"><i></i></div><div class="cine">${escena.modo === 'fin' && marca.motorSeleccionado === 'pocket' ? 'NARRACIÓN SINTÉTICA · KYUTAI / POCKET TTS · CC BY 4.0' : 'UNA HISTORIA PROFESIONAL EMPIEZA CON USTED'}</div></body></html>`;
}
function dispositivo(escena, captura) {
    const papel=esPapel(escena);
    return `<!doctype html><html><meta charset="utf-8"><style>*{box-sizing:border-box}html,body{margin:0;width:900px;height:1100px;background:transparent;font-family:'DejaVu Sans',sans-serif}
    .marco{position:absolute;left:40px;top:35px;width:820px;height:1026px;padding:${papel?'13px':'12px'};border-radius:${papel?'14px':'42px'};background:${papel?'#f9fcff':'linear-gradient(140deg,#b4c8d5,#36536c 45%,#8ba5b8)'};box-shadow:0 24px 35px #0009,0 0 1px 1px #ffffff30}
    .barra{height:40px;background:#f4f7fa;border-radius:28px 28px 0 0;display:flex;align-items:center;justify-content:center;gap:8px;color:#61768a;font-size:14px}.barra:before{content:'●';color:#14a885;font-size:10px}
    img{display:block;width:794px;height:${papel?'1000px':'962px'};object-fit:${papel?'contain':'fill'};background:white;border-radius:${papel?'3px':'0 0 29px 29px'}}
    </style><body><div class="marco">${papel?'':'<div class="barra">Vista de demostración · Mi CV</div>'}<img src="data:image/png;base64,${Buffer.from(captura).toString('base64')}" alt=""></div></body></html>`;
}
function planMontaje(escenas, fundido=TRANSICION) {
    let cursor=0;
    return escenas.map((escena,i)=>{
        if (!(escena.duracion>fundido*2)) throw new Error('Una escena es demasiado corta para la transición.');
        const plan={ inicio:cursor, omitir:i?fundido:0, conservar:escena.duracion-(i?fundido:0)-(i<escenas.length-1?fundido:0) };
        cursor+=escena.duracion-(i<escenas.length-1?fundido:0);
        return plan;
    });
}
function filtroEscena(indice,duracion,papel=false) {
    const direccion=indice%2?-1:1;
    const x=`50+${direccion}*7*sin(t*0.7)+if(lt(t\,0.85)\,${direccion}*90*pow(1-t/0.85\,3)\,0)`;
    const y='427+8*sin(t*0.6)+if(lt(t\,0.85)\,95*pow(1-t/0.85\,3)\,0)';
    const camara=papel ? ",zoompan=z='1.025+0.012*sin(on/145)':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=1:s=900x1100:fps=30" : '';
    return `[0:v]fps=30,format=rgba[bg];[1:v]fps=30${camara},format=rgba,rotate=angle='0.004*sin(t*0.6)':fillcolor=none:ow=iw:oh=ih,fade=t=in:st=0:d=0.32:alpha=1[ui];[bg][ui]overlay=x='${x}':y='${y}':eval=frame:shortest=1,subtitles=subtitulos-${indice}.ass,format=yuv420p[v]`;
}
module.exports={ fondo,dispositivo,esPapel,planMontaje,filtroEscena,TRANSICION };
