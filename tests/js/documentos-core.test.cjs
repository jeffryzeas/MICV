const {test}=require('node:test');
const assert=require('node:assert/strict');
const coreModule={exports:{}};
require('node:vm').runInNewContext(require('node:fs').readFileSync(require('node:path').join(__dirname,'../../public/js/documentos-core.js'),'utf8'),{module:coreModule,Uint8ClampedArray,Float32Array,Float64Array});
const C=coreModule.exports;

test('La perspectiva conserva los bordes y no modifica la imagen fuente',()=>{
    const w=20,h=12,data=new Uint8ClampedArray(w*h*4);
    for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;data[i]=x*10;data[i+1]=y*20;data[i+2]=70;data[i+3]=255;}
    const original=data.slice();
    const out=C.transformar({width:w,height:h,data},[{x:0,y:0},{x:w-1,y:0},{x:w-1,y:h-1},{x:0,y:h-1}],w,h);
    assert.deepEqual(out.data,original);assert.deepEqual(data,original);
});

test('Rechaza esquinas cruzadas y tamaños de salida excesivos',()=>{
    assert.equal(C.valido([{x:0,y:0},{x:99,y:99},{x:99,y:0},{x:0,y:99}]),false);
    assert.throws(()=>C.transformar({width:1,height:1,data:new Uint8ClampedArray(4)},[],10000,10000));
});

test('Nitidez mejora bordes sin modificar la fuente y no colorea el blanco y negro',()=>{
    const width=5,height=5,data=new Uint8ClampedArray(width*height*4).fill(100);
    for(let i=3;i<data.length;i+=4)data[i]=255;
    for(let c=0;c<3;c++)data[(2*width+2)*4+c]=180;
    const src={width,height,data};const old=data.slice();
    const sharp=C.filtrar(src,{nitidez:100});assert(sharp.data[(2*width+2)*4]>180);assert.deepEqual(data,old);
    const bw=C.filtrar(src,{modo:'bn',nitidez:100});
    for(let i=0;i<bw.data.length;i+=4){assert([0,255].includes(bw.data[i]));assert.equal(bw.data[i],bw.data[i+1]);}
});

test('Múltiples copias repiten frente y reverso en orden, dentro de márgenes y sin superposición',()=>{
    for(const papel of ['carta','a4'])for(const orientacion of ['vertical','horizontal'])for(const distribucion of ['vertical','horizontal'])for(const cantidad of [1,2])for(const copias of [1,2,3,5,10]){
        const m=C.medidas({papel,orientacion,distribucion,cantidad,copias,separacion:2,margen:10,ajustar:true});
        assert.equal(m.posiciones.length,cantidad*copias);assert(m.escala>0&&m.escala<=1);
        assert.equal(m.pw>m.ph,orientacion==='horizontal');
        m.posiciones.forEach((p,i)=>{
            assert.equal(p.foto,i%cantidad);assert(p.x>=10-1e-6&&p.y>=10-1e-6);
            assert(p.x+m.ancho<=m.pw-10+1e-6);assert(p.y+m.alto<=m.ph-10+1e-6);
            for(const q of m.posiciones.slice(i+1))assert(p.x+m.ancho<=q.x+1e-6||q.x+m.ancho<=p.x+1e-6||p.y+m.alto<=q.y+1e-6||q.y+m.alto<=p.y+1e-6);
        });
    }
});

test('No reduce tamaño real sin autorización y rechaza medidas o cantidades inválidas',()=>{
    assert.throws(()=>C.medidas({ancho:300,ajustar:false}));
    assert.throws(()=>C.medidas({copias:2.5}));assert.throws(()=>C.medidas({copias:11}));
    assert.throws(()=>C.medidas({copias:10,cantidad:2,separacion:60,ajustar:true}));
    assert.throws(()=>C.medidas({ancho:NaN}));assert.throws(()=>C.medidas({orientacion:'otra'}));
    const m=C.medidas({ancho:85.6,alto:54,cantidad:2,copias:1});assert.equal(m.escala,1);assert.equal(m.ancho,85.6);assert.equal(m.alto,54);
});
