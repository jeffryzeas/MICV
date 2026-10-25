<?php
namespace Tests\Unit;

use App\Models\{Cliente,ContableMovimiento,DesktopDevice,DesktopLicense};
use App\Models\Pos\{PosCaja,PosProducto,PosVenta};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DesktopDatabaseConnectionTest extends TestCase
{
    public function test_desktop_models_and_receipts_use_the_mysql_connection_configured_in_laravel(): void
    {
        $original=config('database.default');
        try {
            config(['database.default'=>'mysql']);
            foreach ([Cliente::class,ContableMovimiento::class,DesktopDevice::class,DesktopLicense::class,PosCaja::class,PosProducto::class,PosVenta::class] as $model) {
                $this->assertSame('mysql',(new $model)->getConnection()->getName());
                $this->assertSame('mysql',(new $model)->getConnection()->getDriverName());
            }
            $this->assertSame('mysql',DB::connection()->getName());
        } finally {
            DB::purge('mysql');
            config(['database.default'=>$original]);
        }
    }
}
