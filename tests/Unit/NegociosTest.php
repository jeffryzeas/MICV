<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Support\Negocios;
class NegociosTest extends TestCase {
 public function test_perfil_contador_no_habilita_pos_y_cv_incluye_clientes(): void {$this->assertSame(['contabilidad'],Negocios::modulos('contabilidad'));$this->assertContains('clientes',Negocios::modulos('personalizado',['cv']));$this->assertNotContains('falso',Negocios::modulos('personalizado',['falso']));}
}
