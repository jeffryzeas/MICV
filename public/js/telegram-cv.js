(() => {
 'use strict';
 const f=document.getElementById('tgcv-compose');
 if(f){
  const cliente=document.getElementById('tgcv-cliente'),visor=document.getElementById('tgcv-preview'),status=document.getElementById('tgcv-preview-status'),texto=document.getElementById('tgcv-mensaje'),cv=document.getElementById('tgcv-cv'),foto=document.getElementById('tgcv-foto'),paletas=document.getElementById('tgcv-paletas');let timer;
  function actualizar(){
   const adjunto=f.querySelector('[name=tipo]:checked')?.value==='cv';cv.hidden=!adjunto;cliente.required=adjunto;
   texto.maxLength=adjunto?1024:4096;document.getElementById('tgcv-count').textContent=`${texto.value.length} / ${texto.maxLength} caracteres${adjunto?' · mensaje que acompaña al PDF':''}`;texto.setCustomValidity(texto.value.length>texto.maxLength?'El mensaje supera el límite permitido.':'');
   clearTimeout(timer);if(!adjunto){visor.hidden=true;visor.removeAttribute('src');delete visor.dataset.actual;return;}
   timer=setTimeout(()=>{const ruta=cliente.selectedOptions[0]?.dataset.vista;if(!ruta){visor.hidden=true;visor.removeAttribute('src');delete visor.dataset.actual;status.textContent='Seleccione una persona para ver su CV.';return;}
    const diseno=f.querySelector('[name=diseno]:checked')?.value||'esencial',fija=['clasico','carta','ats'].includes(diseno);paletas.disabled=fija;foto.disabled=diseno==='ats';const url=new URL(ruta,location.origin);url.search=new URLSearchParams({diseno,paleta:fija?'':(f.querySelector('[name=paleta]:checked')?.value||'cyber'),idioma:f.querySelector('[name=idioma]:checked')?.value||'es',foto:foto.checked&&!foto.disabled?'1':'0'});if(visor.dataset.actual===url.href){visor.hidden=false;return;}visor.dataset.actual=url.href;visor.hidden=false;status.textContent='Actualizando la vista previa…';visor.onload=()=>status.textContent='Vista previa con los datos actuales.';visor.src=url.href;
   },180);
  }
  f.addEventListener('change',actualizar);texto.addEventListener('input',()=>{texto.setCustomValidity('');document.getElementById('tgcv-count').textContent=`${texto.value.length} / ${texto.maxLength} caracteres`;});actualizar();
 }
 const p=document.getElementById('tgcv-delivery');if(!p||!['en_cola','enviando'].includes(p.dataset.estado))return;let ocupado=false,fin=false;
 async function consultar(){if(ocupado||fin||document.hidden)return;ocupado=true;try{const r=await fetch(p.dataset.url,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});if(r.redirected||[401,403,404,419].includes(r.status)){fin=true;throw Error();}if(!r.ok)throw Error();const d=await r.json();p.dataset.estado=d.estado;const b=document.getElementById('tgcv-status');b.textContent=d.etiqueta;b.dataset.estado=d.estado;document.getElementById('tgcv-detail').textContent=d.detalle||({en_cola:d.espera?'El envío sigue en cola. Revise el servicio micv-telegram-cv del VPS.':'Esperando al trabajador de Telegram. Puede salir y volver.',enviando:'Enviando desde su cuenta personal de Telegram.'}[d.estado]||'');document.getElementById('tgcv-date').textContent=d.fecha||'';fin=!['en_cola','enviando'].includes(d.estado);}catch{document.getElementById('tgcv-detail').textContent='No se pudo actualizar el estado. Revise su sesión; no confirme otro envío.';}finally{ocupado=false;}}
 const t=setInterval(()=>fin?clearInterval(t):consultar(),5000);document.addEventListener('visibilitychange',consultar);consultar();
})();
