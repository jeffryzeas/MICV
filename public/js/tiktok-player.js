(() => {
 'use strict';const root=document.getElementById('tt-reproductor');if(!root)return;
 const video=root.querySelector('video'),play=root.querySelector('[data-tt-play]'),message=root.querySelector('[data-tt-video-message]');let hls=null,source=null,wants=false,fallos=0,disponible=false;
 function detener(){if(hls){hls.destroy();hls=null;}video.pause();video.removeAttribute('src');video.load();source=null;}
 function iniciar(url){if(!url||source===url)return;detener();source=url;
  if(window.Hls?.isSupported()){
   hls=new Hls({enableWorker:false,maxBufferLength:15,backBufferLength:15,manifestLoadingMaxRetry:2,levelLoadingMaxRetry:2,fragLoadingMaxRetry:2});hls.loadSource(url);hls.attachMedia(video);
   hls.on(Hls.Events.MANIFEST_PARSED,()=>{message.textContent='Señal pública conectada. Puede activar el sonido en el reproductor.';video.play().catch(()=>{message.textContent='Pulse Play en el reproductor para iniciar la transmisión.';});});
   hls.on(Hls.Events.ERROR,(_,data)=>{if(!data.fatal)return;const retry=++fallos<=2&&data.type===Hls.ErrorTypes.NETWORK_ERROR;detener();message.textContent='No se pudo reproducir esta señal. La captura de comentarios continúa; puede abrir el LIVE en TikTok.';if(retry)setTimeout(()=>{if(wants&&disponible)iniciar(url)},5000);else{wants=false;play.hidden=!disponible;}});
  }else if(video.canPlayType('application/vnd.apple.mpegurl')){video.src=url;video.play().catch(()=>{});}else{message.textContent='Este navegador no admite la señal HLS. Abra el LIVE en TikTok.';wants=false;source=null;}
 }
 video.addEventListener('playing',()=>{fallos=0;});
 video.addEventListener('error',()=>{if(!hls){wants=false;play.hidden=!disponible;message.textContent='La señal no se pudo reproducir. Puede abrir el LIVE en TikTok; la captura continúa.';}});
 function estado(d){disponible=Boolean(d?.disponible);root.dataset.url=d?.url||'';play.hidden=!disponible||wants;if(!disponible){if(source)detener();wants=false;message.textContent=d?.mensaje||'Esperando una señal pública compatible.';}else if(wants)iniciar(d.url);else message.textContent=d.mensaje;}
 play.addEventListener('click',()=>{wants=true;fallos=0;play.hidden=true;message.textContent='Conectando la señal pública…';iniciar(root.dataset.url);});
 window.addEventListener('tiktok:video',e=>estado(e.detail));estado({disponible:root.dataset.disponible==='1',url:root.dataset.url,mensaje:message.textContent});window.addEventListener('pagehide',detener);
})();
