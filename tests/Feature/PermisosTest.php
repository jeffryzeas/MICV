<?php
namespace Tests\Feature;

use App\Models\User;
use App\Support\Permisos;
use App\Http\Middleware\PermisosModulo;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    public function test_cajero_predeterminado_no_puede_configurar_ni_administrar_personal(): void
    {
        $u=new User(['rol'=>'cyber','encargado_id'=>7,'permisos'=>null]);
        $u->setRelation('cyber',new \App\Models\Cyber(['modulos'=>null]));
        $this->assertTrue($u->tienePermiso('pos.vender'));
        $this->assertTrue($u->tienePermiso('pos.ventas'));
        $this->assertFalse($u->tienePermiso('pos.config'));
        $this->assertFalse($u->tienePermiso('pos.precios'));
        $this->assertFalse($u->tienePermiso('pos.descuentos'));
        $this->assertFalse($u->tienePermiso('personal'));
    }
    public function test_lista_vacia_no_recupera_permisos_por_defecto(): void
    {
        $u=new User(['rol'=>'cyber','encargado_id'=>null,'permisos'=>[]]);
        $this->assertFalse($u->tienePermiso('pos.vender'));
    }
    public function test_cajero_no_se_convierte_en_encargado_por_un_permiso(): void
    {
        $u=new User(['rol'=>'cyber','encargado_id'=>7,'permisos'=>['personal']]);
        $this->assertFalse($u->esEncargado());
        $this->assertFalse($u->tienePermiso('personal'));
    }
    public function test_middleware_bloquea_el_post_directo_de_anulacion(): void
    {
        $u=new User(['rol'=>'cyber','encargado_id'=>7,'permisos'=>['pos.vender']]);
        $r=Request::create('/pos/ventas/1/anular','POST');
        $r->setUserResolver(fn()=>$u);
        $route=new Route('POST','pos/ventas/{venta}/anular',fn()=>null);$route->name('pos.ventas.anular');$r->setRouteResolver(fn()=>$route);
        try {(new PermisosModulo)->handle($r,fn()=>response('No debe ejecutarse'));$this->fail('La anulación debía bloquearse.');}
        catch(HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
    public function test_rutas_de_fotos_y_reportes_tienen_permiso_explicito(): void
    {
        $this->assertSame(['pos.vender','pos.productos'],Permisos::deRuta('pos.productos.foto'));
        $this->assertSame(['pos.reportes'],Permisos::deRuta('pos.reportes.excel'));
        $this->assertSame(['documentos'],Permisos::deRuta('documentos.index'));
        $this->assertSame(['personal'],Permisos::deRuta('equipo.guardar'));
    }
}
