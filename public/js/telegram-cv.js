(() => {
 'use strict';
 const qrPanel=document.getElementById('tgcv-qr-login');
 if(qrPanel){
  const imagen=document.getElementById('tgcv-qr-image'),estado=document.getElementById('tgcv-qr-status'),tiempo=document.getElementById('tgcv-qr-time'),volver=document.getElementById('tgcv-qr-retry');let token='',limite=0,fin=false,ocupado=false,version=0;
  function limpiar(){token='';limite=0;version++;imagen.replaceChildren();tiempo.textContent='';}
  async function consultarQr(){if(fin||ocupado||document.hidden)return;ocupado=true;try{
   const r=await fetch(qrPanel.dataset.url,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});if(r.redirected||[401,403,404,419].includes(r.status)){fin=true;limpiar();estado.textContent='Vuelva a iniciar sesión para continuar.';return;}if(!r.ok)throw Error();const d=await r.json();
   if(['conectada','clave'].includes(d.estado)){fin=true;limpiar();estado.textContent='Vinculación aprobada. Actualizando…';location.reload();return;}
   if(['error','cancelada','caducada'].includes(d.estado)){fin=true;limpiar();estado.textContent=d.detalle||'La vinculación terminó. Genere un nuevo QR.';volver.hidden=false;return;}
   if(d.qr_url&&/^tg:\/\/login\?token=[A-Za-z0-9_-]{16,512}$/.test(d.qr_url)){
    limite=performance.now()+Math.max(0,Number(d.restante))*1000;estado.textContent='Escanee el QR y apruebe la conexión desde Telegram.';
    if(token!==d.qr_url){token=d.qr_url;const actual=++version,qr=new QRCodeStyling({width:260,height:260,type:'canvas',data:token,margin:16,qrOptions:{errorCorrectionLevel:'M'},dotsOptions:{type:'square',color:'#08233f'},backgroundOptions:{color:'#ffffff'}});const blob=await qr.getRawData('png');if(actual===version&&!fin){const img=new Image();const url=URL.createObjectURL(blob);img.alt='QR temporal para vincular Telegram del sistema';img.onload=()=>URL.revokeObjectURL(url);img.src=url;imagen.replaceChildren(img);}}
   }else{limpiar();estado.textContent=d.espera?'Esperando al servicio de vinculación. Revise micv-telegram-login en el VPS.':d.estado==='generando'?'Conectando con Telegram para generar el QR…':'Esperando al servicio de vinculación…';}
  }catch{limpiar();estado.textContent='No se pudo actualizar la vinculación. Se volverá a consultar.';}finally{ocupado=false;}}
  setInterval(()=>{if(limite){const n=Math.max(0,Math.ceil((limite-performance.now())/1000));tiempo.textContent=n?'QR válido durante '+n+' s':'Renovando QR…';if(!n)limpiar();}},1000);
  const t=setInterval(()=>fin?clearInterval(t):consultarQr(),2000);document.addEventListener('visibilitychange',consultarQr);consultarQr();
 }
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
 async function consultar(){if(ocupado||fin||document.hidden)return;ocupado=true;try{const r=await fetch(p.dataset.url,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});if(r.redirected||[401,403,404,419].includes(r.status)){fin=true;throw Error();}if(!r.ok)throw Error();const d=await r.json();p.dataset.estado=d.estado;const b=document.getElementById('tgcv-status');b.textContent=d.etiqueta;b.dataset.estado=d.estado;document.getElementById('tgcv-detail').textContent=d.detalle||({en_cola:d.espera?'El envío sigue en cola. Revise el servicio micv-telegram-cv del VPS.':'Esperando al trabajador de Telegram. Puede salir y volver.',enviando:'Enviando desde Telegram del sistema.'}[d.estado]||'');document.getElementById('tgcv-date').textContent=d.fecha||'';fin=!['en_cola','enviando'].includes(d.estado);}catch{document.getElementById('tgcv-detail').textContent='No se pudo actualizar el estado. Revise su sesión; no confirme otro envío.';}finally{ocupado=false;}}
 const t=setInterval(()=>fin?clearInterval(t):consultar(),5000);document.addEventListener('visibilitychange',consultar);consultar();
})();
