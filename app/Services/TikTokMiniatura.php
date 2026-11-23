<?php
namespace App\Services;
class TikTokMiniatura {
 public function crear(string $bytes,string $mime): ?array {
  if(strlen($bytes)<=65536)return [$bytes,$mime];
  try{
   if(function_exists('imagecreatefromstring')){
    $src=@imagecreatefromstring($bytes);if(!$src)return null;$dst=imagecreatetruecolor(128,128);imagefill($dst,0,0,imagecolorallocate($dst,255,255,255));$scale=min(128/imagesx($src),128/imagesy($src));$w=max(1,(int)round(imagesx($src)*$scale));$h=max(1,(int)round(imagesy($src)*$scale));imagecopyresampled($dst,$src,(int)((128-$w)/2),(int)((128-$h)/2),0,0,$w,$h,imagesx($src),imagesy($src));ob_start();imagejpeg($dst,null,80);$preview=ob_get_clean();imagedestroy($src);imagedestroy($dst);
   }else{
    $html='<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; img-src data:; style-src &apos;unsafe-inline&apos;"><style>html,body{margin:0;width:128px;height:128px;background:#fff}img{display:block;width:128px;height:128px;object-fit:contain}</style></head><body><img src="data:'.$mime.';base64,'.base64_encode($bytes).'"></body></html>';
    $preview=CvPdf::navegador($html)->windowSize(128,128)->setScreenshotType('jpeg',80)->screenshot();
   }
   return $preview&&strlen($preview)<=65536?[$preview,'image/jpeg']:null;
  }catch(\Throwable){return null;}
 }
}
