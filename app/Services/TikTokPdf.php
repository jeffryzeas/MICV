<?php
namespace App\Services;
class TikTokPdf {
    public function generar(array $datos): string{return CvPdf::desdeHtml(view('admin.tiktok.reporte',$datos)->render());}
}
