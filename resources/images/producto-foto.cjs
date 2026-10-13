#!/usr/bin/env node
const sharp=require('sharp');
sharp(process.argv[2],{limitInputPixels:128000000}).rotate().resize({width:1000,height:1000,fit:'inside',withoutEnlargement:true}).flatten({background:'#ffffff'}).jpeg({quality:86,mozjpeg:true}).toFile(process.argv[3]).then(()=>console.log('ok')).catch(e=>{console.error(e.message);process.exit(1)});
