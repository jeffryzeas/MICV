(function(root,factory){if(typeof module==='object'&&module.exports)module.exports=factory();else root.DocumentosCore=factory();})(typeof globalThis!=='undefined'?globalThis:this,function(){
'use strict';
function valido(p){
 if(!p||p.length!==4||p.some(q=>!Number.isFinite(q.x)||!Number.isFinite(q.y)))return false;
 const cross=p.map((q,i)=>{const b=p[(i+1)%4],c=p[(i+2)%4];return (b.x-q.x)*(c.y-b.y)-(b.y-q.y)*(c.x-b.x)});
 return cross.every(v=>v>1e-6)&&Math.abs(p.reduce((a,q,i)=>a+q.x*p[(i+1)%4].y-q.y*p[(i+1)%4].x,0))>100;
}
function homografia(p){
 if(!valido(p))throw Error('Las cuatro esquinas deben rodear el documento sin cruzarse.');
 const uv=[[0,0],[1,0],[1,1],[0,1]],a=[];
 for(let i=0;i<4;i++){const [u,v]=uv[i],{x,y}=p[i];a.push([u,v,1,0,0,0,-x*u,-x*v,x],[0,0,0,u,v,1,-y*u,-y*v,y]);}
 for(let c=0;c<8;c++){let pivot=c;for(let r=c+1;r<8;r++)if(Math.abs(a[r][c])>Math.abs(a[pivot][c]))pivot=r;[a[c],a[pivot]]=[a[pivot],a[c]];const div=a[c][c];if(Math.abs(div)<1e-10)throw Error('El recorte es demasiado pequeño. Separe las esquinas.');for(let k=c;k<=8;k++)a[c][k]/=div;for(let r=0;r<8;r++)if(r!==c){const f=a[r][c];for(let k=c;k<=8;k++)a[r][k]-=f*a[c][k];}}
 return a.map(r=>r[8]);
}
function transformar(src,p,w,h){
 if(!Number.isInteger(w)||!Number.isInteger(h)||w<2||h<2||w>4000||h>4000||w*h>10000000)throw Error('El tamaño de salida no es válido.');
 const m=homografia(p),out=new Uint8ClampedArray(w*h*4),s=src.data,sw=src.width,sh=src.height;
 for(let y=0;y<h;y++){const v=y/(h-1);for(let x=0;x<w;x++){const u=x/(w-1),d=m[6]*u+m[7]*v+1;const sx=Math.min(sw-1,Math.max(0,(m[0]*u+m[1]*v+m[2])/d)),sy=Math.min(sh-1,Math.max(0,(m[3]*u+m[4]*v+m[5])/d)),ix=Math.floor(sx),iy=Math.floor(sy),fx=sx-ix,fy=sy-iy;const i=(iy*sw+ix)*4,j=(iy*sw+Math.min(sw-1,ix+1))*4,k=(Math.min(sh-1,iy+1)*sw+ix)*4,l=(Math.min(sh-1,iy+1)*sw+Math.min(sw-1,ix+1))*4,o=(y*w+x)*4;
 for(let c=0;c<3;c++){const pixel=(s[i+c]*(1-fx)+s[j+c]*fx)*(1-fy)+(s[k+c]*(1-fx)+s[l+c]*fx)*fy;const alpha=(s[i+3]*(1-fx)+s[j+3]*fx)*(1-fy)+(s[k+3]*(1-fx)+s[l+3]*fx)*fy;out[o+c]=pixel*alpha/255+255*(1-alpha/255);}out[o+3]=255;}}
 return {data:out,width:w,height:h};
}
function filtrar(src,{modo='color',brillo=100,contraste=100,umbral=150,nitidez=0}={}){
 const out=new Uint8ClampedArray(src.data),f=contraste/100,b=brillo/100,w=src.width,h=src.height;
 let integral=null,gris=null;
 if(modo==='documento'){gris=new Float32Array(w*h);integral=new Float64Array((w+1)*(h+1));for(let y=0;y<h;y++){let fila=0;for(let x=0;x<w;x++){const i=(y*w+x)*4;gris[y*w+x]=.299*out[i]+.587*out[i+1]+.114*out[i+2];fila+=gris[y*w+x];integral[(y+1)*(w+1)+x+1]=integral[y*(w+1)+x+1]+fila;}}}
 for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;let g=.299*out[i]+.587*out[i+1]+.114*out[i+2];
  if(modo==='documento'){const radio=Math.max(12,Math.round(w/40)),x0=Math.max(0,x-radio),y0=Math.max(0,y-radio),x1=Math.min(w,x+radio+1),y1=Math.min(h,y+radio+1);const mean=(integral[y1*(w+1)+x1]-integral[y0*(w+1)+x1]-integral[y1*(w+1)+x0]+integral[y0*(w+1)+x0])/((x1-x0)*(y1-y0));g=Math.min(255,g*245/Math.max(1,mean));}
  for(let c=0;c<3;c++){let v=modo==='color'?out[i+c]:g;v=((v-128)*f+128)*b;out[i+c]=modo==='bn'?(v>=umbral?255:0):v;}out[i+3]=255;
 }
 if(nitidez>0 && modo!=='bn'){
  const original=out.slice(),amount=Math.min(100,Math.max(0,nitidez))/100;
  for(let y=1;y<h-1;y++)for(let x=1;x<w-1;x++)for(let c=0;c<3;c++){
   const i=(y*w+x)*4+c;
   const around=(original[i-4]+original[i+4]+original[i-w*4]+original[i+w*4])/4;
   out[i]=original[i]+amount*(original[i]-around);
  }
 }
 return {data:out,width:w,height:h};
}
function medidas({papel='carta',ancho=85.6,alto=54,margen=20,separacion=12,cantidad=2,ajustar=false,copias=1,orientacion='vertical',distribucion='vertical'}){
 if(!['carta','a4'].includes(papel)||!['vertical','horizontal'].includes(orientacion)||!['vertical','horizontal'].includes(distribucion))throw Error('Seleccione papel, orientación y distribución válidos.');
 let pw=papel==='a4'?210:215.9,ph=papel==='a4'?297:279.4;
 if(orientacion==='horizontal')[pw,ph]=[ph,pw];
 if(![ancho,alto,margen,separacion].every(Number.isFinite)||ancho<10||alto<10||ancho>500||alto>500||margen<0||margen>50||separacion<0||separacion>60||![1,2].includes(cantidad)||!Number.isInteger(copias)||copias<1||copias>10)throw Error('Revise medidas, márgenes, separación y copias (1 a 10).');
 const total=cantidad*copias,cols=Math.min(total,distribucion==='horizontal'?2:1),rows=Math.ceil(total/cols);
 const availableW=pw-2*margen-(cols-1)*separacion,availableH=ph-2*margen-(rows-1)*separacion;
 if(availableW<=0||availableH<=0)throw Error('Reduzca los márgenes, separación o número de copias.');
 const escala=ajustar?Math.min(1,availableW/(ancho*cols),availableH/(alto*rows)):1;
 const aw=ancho*escala,ah=alto*escala;
 if(aw*cols>availableW+.001||ah*rows>availableH+.001)throw Error('Las copias no caben. Reduzca tamaño, copias o separación, o active Reducir proporcionalmente.');
 const posiciones=Array.from({length:total},(_,i)=>{
  const row=Math.floor(i/cols),items=Math.min(cols,total-row*cols),left=(pw-(items*aw+(items-1)*separacion))/2;
  return {x:left+(i%cols)*(aw+separacion),y:margen+row*(ah+separacion),foto:i%cantidad};
 });
 return {pw,ph,ancho:aw,alto:ah,escala,posiciones};
}
return {valido,homografia,transformar,filtrar,medidas};
});
