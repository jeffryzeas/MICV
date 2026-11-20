'use strict';
const {test,before,after}=require('node:test');
const assert=require('node:assert/strict');
const path=require('node:path');
const puppeteer=require('puppeteer');
let browser;
before(async()=>{browser=await puppeteer.launch({executablePath:process.env.MICV_CHROME_PATH||undefined,headless:true,args:['--disable-dev-shm-usage',...(process.env.MICV_CHROME_NO_SANDBOX==='false'?[]:['--no-sandbox'])]});});
after(async()=>{await browser?.close();});
async function pagina() {
    const page=await browser.newPage();
    await page.setViewport({width:390,height:844});
    await page.setContent(`<form id="form-mi-cv">
      <select name="pais"><option value="NI">Nicaragua</option><option value="CR">Costa Rica</option></select>
      <select id="nac"><option value="Nicaragüense">Nicaragüense</option><option value="Costarricense">Costarricense</option><option value="Española">Española</option></select>
      <input name="nacionalidad" type="hidden" value="Nicaragüense">
      <div data-situacion-laboral>
        <select name="pais_residencia"><option value="">Elija</option><option value="NI" selected>Nicaragua</option><option value="CR">Costa Rica</option><option value="ES">España</option></select>
        <div data-extranjero-laboral><label data-permiso-laboral><input name="autorizacion_laboral" value="Permiso vigente"></label><input name="disponibilidad" value="Inmediata"><input name="cualidades" value="Puntual, responsable"></div>
        <script type="application/json" data-nacionalidades>[{"c":"NI","p":"Nicaragua","m":"Nicaragüense","f":"Nicaragüense"},{"c":"CR","p":"Costa Rica","m":"Costarricense","f":"Costarricense"},{"c":"ES","p":"España","m":"Español","f":"Española"}]</script>
      </div>
    </form>`);
    await page.evaluate(()=>document.getElementById('nac').addEventListener('change',e=>document.querySelector('[name=nacionalidad]').value=e.target.value));
    await page.addScriptTag({path:path.resolve(__dirname,'../../public/js/situacion-laboral.js')});
    return page;
}
async function estado(page) {
    return page.evaluate(()=>({oculto:document.querySelector('[data-extranjero-laboral]').hidden,campos:[...document.querySelectorAll('[data-extranjero-laboral] input')].map(e=>({nombre:e.name,disabled:e.disabled,required:e.required,valor:e.value})),datos:Object.fromEntries(new FormData(document.getElementById('form-mi-cv')))}));
}
test('mismo país oculta y excluye campos; otro país los recupera sin borrar el borrador',async()=>{
    const page=await pagina();try{
        let e=await estado(page);assert.equal(e.oculto,true);assert.ok(e.campos.every(c=>c.disabled));assert.ok(!('disponibilidad' in e.datos));assert.ok(!('cualidades' in e.datos));
        await page.select('[name=pais]','CR');assert.equal((await estado(page)).oculto,true);
        await page.select('[name=pais_residencia]','CR');e=await estado(page);assert.equal(e.oculto,false);assert.ok(e.campos.every(c=>!c.disabled));assert.equal(e.campos[0].required,true);assert.ok(e.campos.slice(1).every(c=>!c.required));assert.equal(e.datos.disponibilidad,'Inmediata');
        await page.select('#nac','Costarricense');e=await estado(page);assert.equal(e.oculto,true);assert.ok(e.campos.every(c=>c.disabled));
        await page.select('[name=pais_residencia]','NI');e=await estado(page);assert.equal(e.oculto,false);assert.equal(e.datos.cualidades,'Puntual, responsable');
        await page.select('#nac','Española');await page.select('[name=pais_residencia]','ES');assert.equal((await estado(page)).oculto,true);
        await page.$eval('[name=nacionalidad]',e=>{e.value=' NICARAGUENSE ';e.dispatchEvent(new Event('input',{bubbles:true}));});await page.select('[name=pais_residencia]','NI');assert.equal((await estado(page)).oculto,true);
    }finally{await page.close();}
});
test('la visibilidad no habilita campos restringidos por los permisos del portal',async()=>{
    const page=await pagina();try{
        await page.evaluate(()=>{const script=document.createElement('script');script.id='portal-edicion';script.type='application/json';script.textContent=JSON.stringify({autorizacion_laboral:false,disponibilidad:false,cualidades:false});document.body.append(script);});
        await page.addScriptTag({path:path.resolve(__dirname,'../../public/js/portal-edicion.js')});
        await page.select('[name=pais_residencia]','CR');const e=await estado(page);assert.equal(e.oculto,false);assert.ok(e.campos.every(c=>c.disabled&&!c.required));assert.ok(!('disponibilidad' in e.datos));
    }finally{await page.close();}
});
