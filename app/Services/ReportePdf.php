<?php
namespace App\Services;
class ReportePdf {
 public function generar(string $html): string {return CvPdf::desdeHtml($html);}
}
