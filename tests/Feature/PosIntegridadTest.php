<?php

namespace Tests\Feature;

use App\Models\Cyber;
use App\Models\User;
use App\Models\Pos\PosCaja;
use App\Models\Pos\PosConfig;
use App\Models\Pos\PosProducto;
use App\Services\Pos\Inventario;
use App\Services\Pos\Ventas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private function contexto(): array
    {
        $cy = Cyber::create(['codigo' => 'PRUEBA', 'nombre' => 'Prueba']);
        $u = User::create(['usuario' => 'prueba', 'name' => 'Prueba', 'email' => 'prueba@example.test', 'password' => 'prueba-segura', 'rol' => 'admin', 'cyber_id' => $cy->id]);
        $cfg = PosConfig::create(['cyber_id' => $cy->id, 'decimales' => 2, 'impuesto_tasa' => 0, 'precios_con_impuesto' => true, 'metodos' => [['clave' => 'efectivo', 'activo' => true], ['clave' => 'tarjeta', 'activo' => true]]]);
        $caja = PosCaja::create(['cyber_id' => $cy->id, 'user_id' => $u->id, 'abierta_en' => now(), 'monto_inicial' => 100]);
        $p = PosProducto::create(['cyber_id' => $cy->id, 'nombre' => 'Artículo', 'precio' => 10, 'costo' => 4, 'stock' => 2, 'controla_stock' => true, 'activo' => true]);
        return [$cfg, $caja, $u, $p];
    }

    private function datos(PosProducto $p): array
    {
        return ['clave_operacion' => '11111111-1111-4111-8111-111111111111', 'items' => [['producto_id' => $p->id, 'cantidad' => 1, 'precio' => 10]], 'pagos' => [['metodo' => 'efectivo', 'monto' => 10]]];
    }

    public function test_reintentar_la_misma_venta_no_duplica_el_cobro_ni_el_stock(): void
    {
        [$cfg, $caja, $u, $p] = $this->contexto();
        $primera = Ventas::registrar($this->datos($p), $cfg, $caja, $u);
        $segunda = Ventas::registrar($this->datos($p), $cfg, $caja, $u);
        $this->assertSame($primera->id, $segunda->id);
        $this->assertDatabaseCount('pos_ventas', 1);
        $this->assertEquals(1, $p->fresh()->stock);
    }

    public function test_una_segunda_anulacion_no_devuelve_stock_dos_veces(): void
    {
        [$cfg, $caja, $u, $p] = $this->contexto();
        $venta = Ventas::registrar($this->datos($p), $cfg, $caja, $u);
        Ventas::anular($venta, $u, 'Prueba');
        try {
            Ventas::anular($venta, $u, 'Reintento');
            $this->fail('Debió rechazar la segunda anulación.');
        } catch (ValidationException $e) {
            $this->assertEquals(2, $p->fresh()->stock);
        }
    }

    public function test_restar_mas_del_stock_no_guarda_movimiento(): void
    {
        [, , $u, $p] = $this->contexto();
        try {
            Inventario::mover($p, -3, 'ajuste', 'Prueba', $u->id);
            $this->fail('Debió rechazar el stock negativo.');
        } catch (ValidationException $e) {
            $this->assertEquals(2, $p->fresh()->stock);
            $this->assertDatabaseCount('pos_movimientos', 0);
        }
    }

    public function test_no_vende_con_una_instancia_de_caja_que_ya_se_cerro(): void
    {
        [$cfg, $caja, $u, $p] = $this->contexto();
        PosCaja::whereKey($caja->id)->update(['estado' => 'cerrada']);
        $this->expectException(ValidationException::class);
        Ventas::registrar($this->datos($p), $cfg, $caja, $u);
    }

    public function test_no_acepta_pago_incompleto_ni_sobrepago_electronico_con_cero_decimales(): void
    {
        [$cfg, $caja, $u, $p] = $this->contexto();
        $cfg->update(['decimales' => 0]);
        foreach ([['efectivo', 9.6], ['tarjeta', 10.4]] as [$metodo, $monto]) {
            $d = $this->datos($p);
            $d['pagos'] = [['metodo' => $metodo, 'monto' => $monto]];
            try {
                Ventas::registrar($d, $cfg, $caja, $u);
                $this->fail('Debió rechazar la diferencia de pago.');
            } catch (ValidationException $e) {
                $this->assertDatabaseCount('pos_ventas', 0);
                $this->assertEquals(2, $p->fresh()->stock);
            }
        }
    }
    public function test_cajero_no_puede_alterar_precio_ni_descuento_en_el_payload(): void
    {
        [$cfg,$caja,$admin,$p]=$this->contexto();
        $encargado=User::create(['usuario'=>'ENCARGADO','name'=>'Encargado','email'=>'encargado@example.test','password'=>'clave-segura','rol'=>'cyber','cyber_id'=>$cfg->cyber_id]);
        $cajero=User::create(['usuario'=>'CAJERO','name'=>'Cajero','email'=>'cajero@example.test','password'=>'clave-segura','rol'=>'cyber','cyber_id'=>$cfg->cyber_id,'encargado_id'=>$encargado->id]);
        foreach (['precio','descuento'] as $cambio) {
            $d=$this->datos($p);
            if($cambio==='precio')$d['items'][0]['precio']=9;
            else $d['descuento_pct']=5;
            try { Ventas::registrar($d,$cfg,$caja,$cajero);$this->fail('El cambio no autorizado debía rechazarse.'); }
            catch(ValidationException $e) {$this->assertDatabaseCount('pos_ventas',0);$this->assertEquals(2,$p->fresh()->stock);}
        }
    }

}
