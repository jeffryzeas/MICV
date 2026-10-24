<?php
namespace Tests\Feature;
use App\Services\{CvDatos,CvEjemplo};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
class CvGaleriaAtsTest extends TestCase {
 use RefreshDatabase;
 public function test_catalogo_tiene_30_visuales_mas_ats_y_diez_nuevos(): void {$this->assertCount(31,CvDatos::DISENOS);$this->assertCount(10,CvDatos::NUEVOS);$this->assertArrayHasKey('ats',CvDatos::DISENOS);$this->assertFalse(CvDatos::esPro('ats'));foreach(CvDatos::NUEVOS as $key)$this->assertTrue(CvDatos::esPro($key));}
 public function test_las_31_plantillas_renderizan_con_ejemplos_y_no_dejan_directivas_blade(): void {$c=CvEjemplo::cliente();foreach(CvDatos::DISENOS as $key=>$name){$html=view('cv.'.$key,CvDatos::armar($c,$key,'azul',false))->render();$this->assertStringContainsString('LOREM IPSUM',mb_strtoupper($html),$key);$this->assertStringNotContainsString('@if(', $html,$key);$this->assertStringNotContainsString('@foreach(', $html,$key);$this->assertFileExists(public_path('images/cv/'.$key.'.png'));}}
 public function test_ats_no_tiene_fotos_tablas_iconos_ni_datos_personales_innecesarios(): void {$c=CvEjemplo::cliente();$c->numero_documento='DOCUMENTO-SENSIBLE-PRUEBA';$html=view('cv.ats',CvDatos::armar($c,'ats',null,false))->render();foreach(['<img','<table','<svg','DOCUMENTO-SENSIBLE-PRUEBA','cv._una-hoja','overflow:hidden'] as $text)$this->assertStringNotContainsString($text,$html);$this->assertStringContainsString('Experiencia laboral',$html);$this->assertStringContainsString('lorem.ipsum@example.com',$html);$this->assertLessThan(strpos($html,'Licenciatura en Administración'),strpos($html,'Asistente administrativo'));}
 public function test_ats_rechaza_foto_y_paleta_aunque_se_soliciten_en_url(): void {$method=new \ReflectionMethod(\App\Http\Controllers\CvController::class,'opciones');$r=Request::create('/cv','GET',['diseno'=>'ats','foto'=>'1','paleta'=>'azul']);$o=$method->invoke(new \App\Http\Controllers\CvController(),$r);$this->assertSame('ats',$o[0]);$this->assertNull($o[1]);$this->assertFalse($o[2]);}
 public function test_ats_escapa_contenido_del_cliente(): void {$c=CvEjemplo::cliente();$c->perfil_profesional='<script>alert("x")</script>';$html=view('cv.ats',CvDatos::armar($c,'ats',null,false))->render();$this->assertStringContainsString('&lt;script&gt;',$html);$this->assertStringNotContainsString('<script>alert',$html);}
}
