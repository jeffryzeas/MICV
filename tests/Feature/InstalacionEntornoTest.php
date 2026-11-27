<?php

namespace Tests\Feature;

use App\Support\InstalacionEntorno;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstalacionEntornoTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->raiz = storage_path('framework/testing/instalacion-'.Str::uuid());
        File::ensureDirectoryExists($this->raiz);
        file_put_contents($this->raiz.'/.env.example', "APP_KEY=\nAPP_ENV=production\nAPP_URL=https://tu-dominio.com\nDB_CONNECTION=mysql\nDB_DATABASE=mi_cv\nDB_PASSWORD=\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->raiz);
        parent::tearDown();
    }

    private function opciones(array $mas = []): array
    {
        return $mas + ['nuevo' => false, 'dominio' => '', 'node' => '/opt/micv/node22/bin/node', 'npm' => '/opt/micv/node22/bin/npm', 'chrome' => '/var/www/micv/.cache/puppeteer/chrome/chrome'];
    }

    public function test_actualizacion_conserva_clave_credenciales_y_https_y_es_idempotente(): void
    {
        $original = "APP_KEY=base64:CLAVE_EXISTENTE\nAPP_URL=https://micv.example.test\nDB_CONNECTION=mysql\nDB_DATABASE=produccion\nDB_PASSWORD=\"clave-secreta\"\nTELEGRAM_OTRO=conservar\n";
        file_put_contents($this->raiz.'/.env', $original);
        InstalacionEntorno::configurar($this->raiz, $this->opciones(['dominio' => 'micv.example.test']));
        $d = InstalacionEntorno::leer($this->raiz);
        $this->assertSame('base64:CLAVE_EXISTENTE', $d['APP_KEY']);
        $this->assertSame('mysql', $d['DB_CONNECTION']);
        $this->assertSame('produccion', $d['DB_DATABASE']);
        $this->assertSame('clave-secreta', $d['DB_PASSWORD']);
        $this->assertSame('https://micv.example.test', $d['APP_URL']);
        $this->assertSame('conservar', $d['TELEGRAM_OTRO']);
        $this->assertSame($this->raiz.'/storage/app/tiktok-venv/bin/python', $d['TIKTOK_PYTHON']);
        $this->assertSame($this->raiz.'/storage/app/telegram-cv-venv/bin/python', $d['TELEGRAM_CV_PYTHON']);
        $this->assertFileDoesNotExist($this->raiz.'/storage/app/private/database.sqlite');
        $antes = file_get_contents($this->raiz.'/.env');
        InstalacionEntorno::configurar($this->raiz, $this->opciones(['dominio' => 'micv.example.test']));
        $this->assertSame($antes, file_get_contents($this->raiz.'/.env'));
        $copias = glob($this->raiz.'/storage/app/private/instalacion/.env.*.bak');
        $this->assertCount(1, $copias);
        $this->assertSame($original, file_get_contents($copias[0]));
        $this->assertSame(0600, fileperms($copias[0]) & 0777);
    }

    public function test_instalacion_nueva_usa_sqlite_privado_sin_inventar_credenciales(): void
    {
        InstalacionEntorno::configurar($this->raiz, $this->opciones(['nuevo' => true, 'dominio' => 'micv.example.test']));
        $d = InstalacionEntorno::leer($this->raiz);
        $this->assertSame('sqlite', $d['DB_CONNECTION']);
        $this->assertSame($this->raiz.'/storage/app/private/database.sqlite', $d['DB_DATABASE']);
        $this->assertSame('http://micv.example.test', $d['APP_URL']);
        $this->assertSame('database', $d['QUEUE_CONNECTION']);
        $this->assertSame('', $d['APP_KEY']);
        $this->assertFileExists($d['DB_DATABASE']);
        $this->assertSame(0600, fileperms($d['DB_DATABASE']) & 0777);
    }

    public function test_dominio_invalido_o_ruta_inyectada_no_modifican_la_configuracion(): void
    {
        file_put_contents($this->raiz.'/.env', 'APP_KEY=conservar');
        foreach ([['dominio' => "micv.test\nserver{}"], ['node' => '/usr/bin/node;id']] as $mal) {
            try {
                InstalacionEntorno::configurar($this->raiz, $this->opciones($mal));
                $this->fail();
            } catch (\RuntimeException) {
                $this->assertSame('APP_KEY=conservar', file_get_contents($this->raiz.'/.env'));
            }
        }
    }
}
