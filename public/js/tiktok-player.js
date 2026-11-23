(() => {
 'use strict';const root=document.getElementById('tt-reproductor');if(!root)return;
 const video=root.querySelector('video'),play=root.querySelector('[data-tt-play]'),stop=root.querySelector('[data-tt-stop]'),message=root.querySelector('[data-tt-video-message]'),label=root.querySelector('[data-tt-player-status]');
 let hls=null,source=null,intent=false,info={},timer=null,failures=0,recovered=false,lastError='';
 function phase(value,text){root.dataset.playback=value;if(text)message.textContent=text;if(label)label.textContent={idle:'Señal pública',waiting:'En espera',loading:'Conectando',playing:'Reproduciendo',paused:'En pausa',error:'Señal no disponible'}[value]||value;}
 function buttons(){play.disabled=!info.disponible;play.hidden=intent&&Boolean(info.disponible);if(stop)stop.hidden=!intent;}
 function close(){if(timer){clearTimeout(timer);timer=null;}source=null;const previous=hls;hls=null;if(previous)previous.destroy();video.pause();video.removeAttribute('src');video.load();}
 function failure(text,retry=false){close();lastError=text;phase('error',text);if(retry&&intent&&info.disponible&&++failures<=3){timer=setTimeout(()=>{timer=null;if(intent&&info.disponible)start(info.url);},[0,3000,8000,15000][failures]);}else intent=false;buttons();}
 function start(url){if(!url||url===source)return;close();source=url;lastError='';phase('loading','Conectando la señal pública…');buttons();
  if(window.Hls?.isSupported()){
   const engine=new Hls({enableWorker:false,maxBufferLength:15,backBufferLength:15,manifestLoadingMaxRetry:2,levelLoadingMaxRetry:2,fragLoadingMaxRetry:2});hls=engine;engine.loadSource(url);engine.attachMedia(video);
   engine.on(Hls.Events.MANIFEST_PARSED,()=>{if(hls!==engine)return;video.play().catch(()=>phase('paused','Pulse Play en los controles del reproductor.'));});
   engine.on(Hls.Events.ERROR,(_,d)=>{if(hls!==engine||!d.fatal)return;const status=d.response?.code||d.networkDetails?.status;if([401,403].includes(status)){failure('La sesión no permite consultar esta señal. Vuelva a iniciar sesión.');return;}if(status===410){failure('El LIVE ya no tiene una captura activa. La vista se actualizará al detectar otra sala.');return;}if(d.type===Hls.ErrorTypes.MEDIA_ERROR&&!recovered){recovered=true;engine.recoverMediaError();return;}failure('No se pudo reproducir esta señal pública. Puede abrirla en TikTok; la captura de comentarios continúa.',d.type===Hls.ErrorTypes.NETWORK_ERROR);});
  }else if(video.canPlayType('application/vnd.apple.mpegurl')){video.src=url;video.play().catch(()=>phase('paused','Pulse Play en los controles del reproductor.'));}
  else failure('Este navegador no admite el formato HLS. Abra el LIVE en TikTok.');
 }
 function update(d){const changed=info.url!==d?.url;info=d||{};if(changed)lastError='';if(!info.disponible){if(source)close();phase('waiting',info.mensaje||'Esperando una señal pública compatible.');}else if(intent&&!timer)start(info.url);else if(!intent&&!lastError)phase('idle',info.mensaje||'Señal pública disponible. Pulse Reproducir LIVE.');buttons();}
 play.addEventListener('click',()=>{intent=true;failures=0;recovered=false;lastError='';start(info.url);});
 stop?.addEventListener('click',()=>{intent=false;lastError='';close();phase('idle','Vista detenida. El monitoreo y la captura de comentarios continúan.');buttons();});
 video.addEventListener('playing',()=>{if(!intent||!source)return;failures=0;recovered=false;phase('playing','Señal pública en reproducción. Puede activar el sonido en los controles.');});
 video.addEventListener('waiting',()=>{if(intent&&source)phase('loading','Cargando la señal…');});video.addEventListener('pause',()=>{if(intent&&source&&!video.ended)phase('paused','Reproducción en pausa. La captura de comentarios continúa.');});video.addEventListener('ended',()=>{if(intent)phase('waiting','La señal terminó. Se mostrará la siguiente sala pública cuando esté disponible.');});
 video.addEventListener('error',()=>{if(intent&&!hls&&source)failure('El navegador no pudo reproducir esta señal. Puede abrir el LIVE en TikTok.');});
 window.addEventListener('tiktok:video',e=>update(e.detail));update({disponible:root.dataset.disponible==='1',url:root.dataset.url,mensaje:message.textContent});window.addEventListener('pagehide',()=>{intent=false;close();});
})();
