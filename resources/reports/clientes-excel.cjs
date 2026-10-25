const fs = require('fs');
const ExcelJS = require('exceljs');
async function generar(d, salida) {
 const wb = new ExcelJS.Workbook(); wb.creator = d.negocio;
 const s = wb.addWorksheet('Clientes', {views:[{state:'frozen',ySplit:5,showGridLines:false}],pageSetup:{orientation:d.orientacion||'landscape',fitToPage:true,fitToWidth:1,fitToHeight:0,printTitlesRow:'1:5'}});
 s.columns = [34,25,24,40,24,24,26].map(width=>({width}));
 for(let i=1;i<=3;i++) s.mergeCells(i,1,i,7);
 s.getCell('A1').value=d.negocio;s.getCell('A2').value='Directorio de clientes';s.getCell('A3').value='Generado: '+d.generado+' · '+d.clientes.length+' registros · Uso interno';
 s.getRow(1).height=36;s.getCell('A1').font={size:20,bold:true,color:{argb:'FFFFFFFF'}};s.getCell('A1').fill={type:'pattern',pattern:'solid',fgColor:{argb:'FF0B3C74'}};
 if(d.logo && /^data:image\/(png|jpeg);base64,/.test(d.logo)){const ext=d.logo.startsWith('data:image/png')?'png':'jpeg';const logoId=wb.addImage({base64:d.logo,extension:ext});s.addImage(logoId,{tl:{col:4,row:0},ext:{width:44,height:44}});}
 s.getCell('A2').font={size:15,bold:true,color:{argb:'FF0B3C74'}};s.getRow(2).height=28;
 const names=['Nombre completo','Documento','Teléfono','Correo','País emisor del documento','Nacionalidad','País de residencia actual'];
 // Always plain strings, including text starting with =, +, - or @. Never create formulas from client data.
 const rows=d.clientes.map(row=>row.map(v=>String(v??'')));
 if(rows.length)s.addTable({name:'DirectorioClientes',ref:'A5',headerRow:true,style:{theme:'TableStyleMedium2',showRowStripes:true},columns:names.map(name=>({name,filterButton:true})),rows});
 else {s.getRow(5).values=names;s.getCell('A6').value='Sin registros';}
 s.eachRow((row,n)=>{if(n>=5){row.height=32;row.eachCell(cell=>{cell.numFmt='@';cell.alignment={vertical:'middle',wrapText:true};});}});
 s.headerFooter.oddFooter='&LUso interno&CPágina &P de &N';
 await wb.xlsx.writeFile(salida);
}
if(require.main===module){let input='';process.stdin.setEncoding('utf8');process.stdin.on('data',x=>input+=x);process.stdin.on('end',()=>generar(JSON.parse(input),process.argv[2]).catch(e=>{console.error(e.message);process.exitCode=1;}));}
module.exports={generar};
