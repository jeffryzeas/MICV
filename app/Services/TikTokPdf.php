<?php
namespace App\Services;
class TikTokPdf {
 public function documento(array $datos): array {
  $pie='<div style="font-family:Arial,sans-serif;font-size:8px;color:#657185;width:100%;padding:0 16mm;display:flex;justify-content:space-between"><span>Mi CV · Informe técnico de captura · Parte '.(int)$datos['parte'].'/'.(int)$datos['totalPartes'].'</span><span>Página <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>';
  return ['html'=>view('admin.tiktok.reporte',$datos)->render(),'footer'=>$pie,'header'=>'<span></span>'];
 }
 public function generar(array $datos): string {
  $doc=$this->documento($datos);
  return CvPdf::navegador($doc['html'])->format('A4')->margins(16,15,20,15)->showBackground()->emulateMedia('print')->setOption('preferCSSPageSize',true)->showBrowserHeaderAndFooter()->headerHtml($doc['header'])->footerHtml($doc['footer'])->pdf();
 }
}
