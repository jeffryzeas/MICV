#!/usr/bin/env node
const sharp=require('sharp');
sharp(process.argv[2],{limitInputPixels:80000000}).rotate().resize({width:2400,height:2400,fit:'inside',withoutEnlargement:true}).flatten({background:'#ffffff'}).jpeg({quality:94,mozjpeg:true}).toFile(process.argv[3]).then(()=>console.log('ok')).catch(e=>{console.error(e.message);process.exit(1)});
