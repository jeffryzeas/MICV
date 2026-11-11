#!/usr/bin/env node
const fs=require('fs');
const ExcelJS=require('exceljs');
const JSZip=require('jszip');
async function generar(d,salida){
 const wb=new ExcelJS.Workbook();wb.creator=d.negocio;wb.title='Reporte del punto de venta';wb.created=new Date();wb.calcProperties.fullCalcOnLoad=true;
 const azul='123B63',oro='E7BC55',gris='66758A',money='"'+String(d.simbolo||'').replace(/"/g,'')+'" #,##0.00;[Red]("'+String(d.simbolo||'').replace(/"/g,'')+'" #,##0.00)';
 function hoja(nombre,cols,datos){
  const s=wb.addWorksheet(nombre,{views:[{state:'frozen',ySplit:5,showGridLines:false}],pageSetup:{paperSize:9,orientation:d.orientacion||'landscape',fitToPage:true,fitToWidth:1,fitToHeight:0,printTitlesRow:'1:5'},properties:{defaultRowHeight:21}});
  s.columns=cols.map(c=>({width:c.ancho||20}));
  for(let n=1;n<=3;n++)s.mergeCells(n,1,n,cols.length);
  s.getCell('A1').value=d.negocio;s.getCell('A1').font={name:'Calibri',size:19,bold:true,color:{argb:'FFFFFFFF'}};s.getRow(1).height=34;
  for(let i=1;i<=cols.length;i++)s.getRow(1).getCell(i).fill={type:'pattern',pattern:'solid',fgColor:{argb:azul}};
  s.getCell('A2').value=nombre+' · '+d.periodo;s.getCell('A2').font={name:'Calibri',size:12,bold:true,color:{argb:azul}};s.getRow(2).height=26;
  s.getCell('A3').value=nombre==='Inventario'?'Existencias actuales al generar el archivo; no son un saldo histórico del período.':nombre==='Ventas'?'Las ventas anuladas se muestran para consulta. El Resumen suma solo las pagadas.':'Moneda: '+d.moneda+' · Generado: '+d.generado;s.getRow(3).height=30;s.getCell('A3').alignment={wrapText:true,vertical:'middle'};s.getCell('A3').font={name:'Calibri',size:10,color:{argb:gris}};
  if(datos.length)s.addTable({name:'Tabla'+nombre.replace(/[^a-zA-Z]/g,''),ref:'A5',headerRow:true,style:{theme:'TableStyleMedium2',showRowStripes:true},totalsRow:false,columns:cols.map(c=>({name:c.nombre,filterButton:true})),rows:datos.map(row=>row.map(v=>v===''?null:v))});
  else {s.getRow(5).values=cols.map(c=>c.nombre);s.mergeCells(6,1,6,cols.length);s.getCell('A6').value='Sin registros para este período.';s.autoFilter={from:{row:5,column:1},to:{row:5,column:cols.length}};}
  s.getRow(5).height=30;s.getRow(5).eachCell(cell=>{cell.font={name:'Calibri',bold:true,color:{argb:'FFFFFFFF'}};cell.fill={type:'pattern',pattern:'solid',fgColor:{argb:azul}};cell.alignment={wrapText:true,vertical:'middle'};});
  for(let i=0;i<cols.length;i++){
   const col=s.getColumn(i+1);if(cols[i].tipo==='dinero')col.numFmt=money;if(cols[i].tipo==='fecha')col.numFmt='dd/mm/yyyy hh:mm';if(cols[i].tipo==='cantidad')col.numFmt='#,##0.00';if(!cols[i].tipo)col.numFmt='@';
  }
  s.eachRow((row,n)=>{if(n>=6){let alto=21;row.eachCell((cell,i)=>{cell.font={name:'Calibri',size:11,color:{argb:'24354B'}};cell.alignment={vertical:'middle',wrapText:true,horizontal:cols[i-1]?.tipo==='dinero'||cols[i-1]?.tipo==='cantidad'?'right':'left'};const value=cell.value;const texto=value instanceof Date?'00/00/0000 00:00':value&&typeof value==='object'&&'result' in value?String(value.result):String(value??'');alto=Math.max(alto,Math.ceil(texto.length/Math.max(8,(cols[i-1]?.ancho||20)-2))*15+6);});row.height=Math.min(96,alto);}});
  s.headerFooter.oddFooter='&L'+nombre+'&C'+d.negocio+'&RPágina &P de &N';
  return s;
 }
 const ventas=d.ventas||[];const pagadas=ventas.filter(v=>v.estado==='pagada');
 hoja('Ventas por hora', [
  {nombre:'Franja',ancho:25}, {nombre:'Ventas pagadas',ancho:23,tipo:'entero'}, {nombre:'Total',ancho:24,tipo:'dinero'}
 ], (d.horas||[]).map(h=>[h.franja,h.cantidad,h.total])).getColumn(2).numFmt='#,##0';
 const dt=s=>new Date(s.endsWith('Z')?s:s+'Z');
 hoja('Ventas',[['Comprobante',20],['Fecha',23,'fecha'],['Cliente',32],['Cajero',26],['Subtotal',19,'dinero'],['Descuento',18,'dinero'],['Impuesto',18,'dinero'],['Total',20,'dinero'],['Costo',19,'dinero'],['Ganancia sin impuesto',24,'dinero'],['Estado',16],['Motivo de anulación',38]].map(([nombre,ancho,tipo])=>({nombre,ancho,tipo})),ventas.map((v,i)=>[v.numero,dt(v.fecha),v.cliente,v.cajero,v.subtotal,v.descuento,v.impuesto,v.total,v.costo,{formula:`H${i+6}-G${i+6}-I${i+6}`,result:Math.round((v.total-v.impuesto-v.costo)*100)/100},v.estado,v.motivo||'']));
 const pagos=[];
 for(const v of ventas){let cambio=v.cambio;for(const p of v.pagos){const vuelto=p.clave==='efectivo'?Math.min(cambio,p.monto):0;cambio=Math.max(0,cambio-vuelto);const n=pagos.length+6;pagos.push([v.numero,dt(v.fecha),p.metodo,p.monto,vuelto,{formula:`D${n}-E${n}`,result:Math.round((p.monto-vuelto)*100)/100},p.referencia||'',v.estado]);}}
 hoja('Pagos',[['Comprobante',20],['Fecha',23,'fecha'],['Método',25],['Monto entregado',21,'dinero'],['Cambio',18,'dinero'],['Ingreso neto',21,'dinero'],['Referencia',32],['Estado',16]].map(([nombre,ancho,tipo])=>({nombre,ancho,tipo})),pagos);
 hoja('Inventario',[['Código',20],['Producto',38],['Categoría',26],['Existencia',18,'cantidad'],['Unidad',15],['Mínimo',16,'cantidad'],['Costo unitario',21,'dinero'],['Precio de venta',21,'dinero'],['Valor al costo',22,'dinero'],['Estado',18]].map(([nombre,ancho,tipo])=>({nombre,ancho,tipo})),(d.inventario||[]).map((p,i)=>[p.codigo||'',p.nombre,p.categoria||'',p.stock,p.unidad,p.minimo,p.costo,p.precio,{formula:`D${i+6}*G${i+6}`,result:Math.round(p.stock*p.costo*100)/100},p.activo?'Activo':'Inactivo']));
 hoja('Compras',[['Número interno',20],['Fecha',23,'fecha'],['Factura proveedor',25],['Proveedor',35],['Usuario',28],['Total',22,'dinero'],['Estado',18]].map(([nombre,ancho,tipo])=>({nombre,ancho,tipo})),(d.compras||[]).map(c=>[String(c.id),dt(c.fecha),c.factura||'',c.proveedor||'',c.usuario||'',c.total,c.estado]));
 const sum=k=>Math.round(pagadas.reduce((a,v)=>a+v[k],0)*100)/100;
 const rows=[['Ventas pagadas',pagadas.length],['Ventas anuladas',ventas.length-pagadas.length],['Total cobrado',sum('total')],['Impuestos',sum('impuesto')],['Costo de lo vendido',sum('costo')],['Ganancia sin impuesto',Math.round((sum('total')-sum('impuesto')-sum('costo'))*100)/100],['Descuentos',sum('descuento')]];
 const s=hoja('Resumen',[{nombre:'Indicador',ancho:40},{nombre:'Valor',ancho:30}],rows);s.getCell('B6').numFmt='#,##0';s.getCell('B7').numFmt='#,##0';
 for(let row=8;row<=12;row++)s.getCell(row,2).numFmt=money;
 if(ventas.length){const last=ventas.length+5;for(const [row,col,key] of [[8,'H','total'],[9,'G','impuesto'],[10,'I','costo'],[12,'F','descuento']])s.getCell(row,2).value={formula:`SUMIF(Ventas!K6:K${last},"pagada",Ventas!${col}6:${col}${last})`,result:sum(key)};s.getCell('B11').value={formula:'B8-B9-B10',result:rows[5][1]};}
 s.getCell('A14').value='Ingresos netos por método';s.getCell('A14').font={bold:true,color:{argb:azul}};
 const metodos={};for(const v of pagadas){let vuelto=v.cambio;for(const p of v.pagos){const resta=p.clave==='efectivo'?Math.min(vuelto,p.monto):0;vuelto-=resta;metodos[p.metodo]=(metodos[p.metodo]||0)+p.monto-resta;}}
 let n=15;for(const [metodo,monto] of Object.entries(metodos)){s.getCell(n,1).value=metodo;s.getCell(n,2).value=Math.round(monto*100)/100;s.getCell(n,2).numFmt=money;n++;}
 s.getCell(n+2,1).value='Período';s.getCell(n+2,2).value=d.periodo;s.getCell(n+3,1).value='Moneda';s.getCell(n+3,2).value=d.moneda;
 wb.worksheets.forEach((sheet,i)=>sheet.orderNo=i+1);wb.worksheets.forEach(sheet=>{if(sheet.name==='Resumen')sheet.orderNo=0;else sheet.orderNo+=1;});
 // ExcelJS 4.x escribe totalsRowShown=1 aun con totalsRow=false.
 // Se corrige el indicador para que ninguna fila de datos aparezca como total.
 const zip=await JSZip.loadAsync(await wb.xlsx.writeBuffer());
 for(const entry of Object.values(zip.files))if(/^xl\/tables\/table\d+\.xml$/.test(entry.name)){
  const xml=await entry.async('string');if(!xml.includes('totalsRowCount="1"'))zip.file(entry.name,xml.replace('totalsRowShown="1"','totalsRowShown="0"'));
 }
 fs.writeFileSync(salida,await zip.generateAsync({type:'nodebuffer',compression:'DEFLATE'}));
}
if(require.main===module)generar(JSON.parse(fs.readFileSync(0,'utf8')),process.argv[2]).catch(e=>{console.error(e.stack);process.exit(1)});
module.exports=generar;
