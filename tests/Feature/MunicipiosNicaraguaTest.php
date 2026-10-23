<?php
namespace Tests\Feature;
use App\Models\{User, Cyber, MunicipioNicaragua, Cliente};
use App\Support\{MunicipiosNicaragua, Documentos};
use App\Services\CedulaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
class MunicipiosNicaraguaTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(["app.key" => "base64:" . base64_encode(str_repeat("x", 32))]);
        $this->withoutMiddleware([
            \App\Http\Middleware\CorreoVerificado::class,
            \App\Http\Middleware\AceptaTerminos::class,
        ]);
    }
    private function cedula(
        string $codigo,
        string $fecha = "010190",
        string $serie = "0137",
    ): string {
        $n = $codigo . $fecha . $serie;
        return $n . "ABCDEFGHJKLMNPQRSTUVWXY"[(int) $n % 23];
    }
    private function usuario(string $rol = "admin"): User
    {
        $s = Str::random(8);
        $cy =
            $rol === "cyber"
                ? Cyber::create([
                    "codigo" => "CAT" . $s,
                    "nombre" => "Cyber prueba",
                ])
                : null;
        return User::create([
            "usuario" => "U" . $s,
            "name" => "Persona de prueba",
            "email" => $s . "@example.test",
            "password" => "Prueba12345678",
            "rol" => $rol,
            "activo" => true,
            "cyber_id" => $cy?->id,
        ]);
    }
    private function datos(array $extra = []): array
    {
        return array_replace(
            [
                "codigo" => "777",
                "municipio" => "Municipio de prueba",
                "departamento" => "Boaco",
                "alias" => "Ejemplo",
                "fuente" =>
                    "Referencia de prueba (no forma parte del catálogo base)",
                "notas" => "Solo prueba",
                "activo" => 1,
                "especial" => 0,
            ],
            $extra,
        );
    }
    public function test_carga_153_municipios_17_departamentos_y_codigo_especial_sin_duplicados()
    {
        $base = MunicipiosNicaragua::base();
        $this->assertCount(154, $base);
        $this->assertCount(154, array_unique(array_column($base, "codigo")));
        $this->assertDatabaseCount("municipios_nicaragua", 154);
        $municipios = array_values(
            array_filter($base, fn($m) => !$m["especial"]),
        );
        $this->assertCount(153, $municipios);
        $this->assertCount(
            17,
            array_unique(array_column($municipios, "departamento")),
        );
        foreach ($municipios as $m) {
            $v = CedulaValidator::validar($this->cedula($m["codigo"]));
            $this->assertTrue(
                $v["valida"],
                $m["codigo"] . ": " . implode(";", $v["errores"]),
            );
            $this->assertSame($m["municipio"], $v["municipio"]);
            $this->assertSame($m["departamento"], $v["departamento"]);
        }
    }
    public function test_prefijos_corregidos_no_dependen_de_rangos()
    {
        $esperados = [
            "615" => ["Bocana de Paiwas", "Costa Caribe Sur"],
            "607" => ["Puerto Cabezas", "Costa Caribe Norte"],
            "601" => ["Bluefields", "Costa Caribe Sur"],
            "521" => ["San Carlos", "Río San Juan"],
            "561" => ["Rivas", "Rivas"],
            "454" => ["Waslala", "Costa Caribe Norte"],
            "492" => ["Wiwilí de Jinotega", "Jinotega"],
            "493" => ["Wiwilí de Nueva Segovia", "Nueva Segovia"],
            "248" => ["San José de Bocay", "Jinotega"],
            "009" => ["El Crucero", "Managua"],
        ];
        foreach ($esperados as $codigo => [$mun, $dep]) {
            $v = CedulaValidator::validar($this->cedula((string) $codigo));
            $this->assertTrue($v["valida"]);
            $this->assertSame($mun, $v["municipio"]);
            $this->assertSame($dep, $v["departamento"]);
        }
        foreach (["000", "530", "641", "249", "282"] as $codigo) {
            $v = CedulaValidator::validar($this->cedula($codigo));
            $this->assertFalse($v["valida"]);
            $this->assertFalse($v["codigo_reconocido"]);
            $this->assertStringContainsString(
                "catálogo",
                implode(" ", $v["errores"]),
            );
        }
    }
    public function test_codigo_615_con_guiones_ceros_y_letra_minuscula_es_consistente_con_api()
    {
        $n = $this->cedula("615");
        $form =
            substr($n, 0, 3) .
            "–" .
            substr($n, 3, 6) .
            "–" .
            strtolower(substr($n, 9));
        $v = CedulaValidator::validar(" " . $form . " ");
        $this->assertTrue($v["valida"]);
        $this->assertSame($n, Documentos::limpiar("cedula", $form));
        $this->actingAs($this->usuario("cyber"))
            ->postJson(route("api.consultar-documento"), [
                "tipo_documento" => "cedula",
                "numero_documento" => $form,
            ])
            ->assertOk()
            ->assertJsonPath("cedula.valida", true)
            ->assertJsonPath("cedula.municipio", "Bocana de Paiwas")
            ->assertJsonPath("cedula.departamento", "Costa Caribe Sur");
        $v = CedulaValidator::validar($this->cedula("041"));
        $this->assertTrue($v["valida"]);
        $this->assertSame("041", $v["municipio_codigo"]);
    }
    public function test_no_relaja_letra_fecha_ni_caracteres_invalidos()
    {
        $n = $this->cedula("615");
        $mala = substr($n, 0, -1) . (str_ends_with($n, "A") ? "B" : "A");
        $this->assertFalse(CedulaValidator::validar($mala)["valida"]);
        foreach (["310290", "000190", "011390"] as $fecha) {
            $this->assertFalse(
                CedulaValidator::validar($this->cedula("615", $fecha))[
                    "valida"
                ],
            );
        }
        $this->assertFalse(CedulaValidator::validar($n . "!")["valida"]);
        $this->assertFalse(CedulaValidator::validar("615")["valida"]);
    }
    public function test_especial_888_no_inventa_municipio()
    {
        $v = CedulaValidator::validar($this->cedula("888"));
        $this->assertTrue($v["valida"]);
        $this->assertTrue($v["codigo_especial"]);
        $this->assertNull($v["municipio"]);
        $this->assertNull($v["departamento"]);
    }
    public function test_modulo_filtra_codigo_nombre_sin_tildes_y_departamento()
    {
        $this->actingAs($this->usuario())
            ->get(route("municipios.index", ["q" => "615"]))
            ->assertOk()
            ->assertSee("Bocana de Paiwas")
            ->assertDontSee('data-codigo="607"', false);
        $this->get(route("municipios.index", ["q" => "Wiwili"]))
            ->assertOk()
            ->assertSee("Wiwilí de Jinotega")
            ->assertSee("Wiwilí de Nueva Segovia");
        $this->get(route("municipios.index", ["departamento" => "Rivas"]))
            ->assertOk()
            ->assertSee('data-codigo="561"', false)
            ->assertDontSee('data-codigo="521"', false);
    }
    public function test_solo_admin_mantiene_catalogo_y_cyber_autorizado_solo_consulta()
    {
        $cy = $this->usuario("cyber");
        $this->actingAs($cy)
            ->get(route("municipios.index"))
            ->assertOk()
            ->assertDontSee("Nuevo código");
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(),
        )->assertForbidden();
        $this->get(route("admin.municipios-ni.edit", "615"))->assertForbidden();
        $this->actingAs($this->usuario("cliente"))
            ->get(route("municipios.index"))
            ->assertForbidden();
        $cy->forceFill(["permisos" => ["pos.vender"]])->save();
        $this->actingAs($cy)->get(route("municipios.index"))->assertForbidden();
        $cy->forceFill(["permisos" => ["correos"]])->save();
        $this->actingAs($cy)->get(route("municipios.index"))->assertOk();
    }
    public function test_agregar_codigo_tiene_efecto_inmediato_y_no_elude_el_checksum()
    {
        $this->assertFalse(
            CedulaValidator::validar($this->cedula("777"))["valida"],
        );
        $admin = $this->usuario();
        $this->actingAs($admin)
            ->post(route("admin.municipios-ni.store"), $this->datos())
            ->assertRedirect();
        $this->assertDatabaseHas("actividad", [
            "user_id" => $admin->id,
            "accion" => "municipio_ni_crear",
        ]);
        $v = CedulaValidator::validar($this->cedula("777"));
        $this->assertTrue($v["valida"]);
        $this->assertSame("Municipio de prueba", $v["municipio"]);
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(),
        )->assertSessionHasErrors("codigo");
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(["codigo" => "00"]),
        )->assertSessionHasErrors("codigo");
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(["codigo" => "000"]),
        )->assertSessionHasErrors("codigo");
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(["codigo" => "776", "departamento" => "Inventado"]),
        )->assertSessionHasErrors("departamento");
        $this->post(
            route("admin.municipios-ni.store"),
            $this->datos(["codigo" => "776", "municipio" => "<script>"]),
        )->assertSessionHasErrors("municipio");
    }
    public function test_corregir_desactivar_reactivar_y_evitar_sobrescrituras_conserva_fichas()
    {
        $admin = $this->usuario();
        $c = Cliente::create([
            "primer_nombre" => "Persona",
            "primer_apellido" => "Prueba",
            "municipio_nacimiento" => "Dato histórico",
        ]);
        $d = $this->datos([
            "municipio" => "Bocana de Paiwas revisado",
            "departamento" => "Costa Caribe Sur",
            "version" => 1,
        ]);
        unset($d["codigo"]);
        $this->actingAs($admin)
            ->put(route("admin.municipios-ni.update", "615"), $d)
            ->assertRedirect();
        $this->assertSame(
            "Bocana de Paiwas revisado",
            CedulaValidator::validar($this->cedula("615"))["municipio"],
        );
        $this->put(
            route("admin.municipios-ni.update", "615"),
            $d,
        )->assertStatus(409);
        $d["version"] = 2;
        $d["activo"] = 0;
        $this->put(
            route("admin.municipios-ni.update", "615"),
            $d,
        )->assertRedirect();
        $this->assertFalse(
            CedulaValidator::validar($this->cedula("615"))["valida"],
        );
        $this->postJson(route("api.consultar-documento"), [
            "tipo_documento" => "cedula",
            "numero_documento" => $this->cedula("615"),
        ])->assertJsonPath("cedula.valida", false);
        $d["version"] = 3;
        $d["activo"] = 1;
        $this->put(
            route("admin.municipios-ni.update", "615"),
            $d,
        )->assertRedirect();
        $this->assertTrue(
            CedulaValidator::validar($this->cedula("615"))["valida"],
        );
        $this->assertSame("Dato histórico", $c->fresh()->municipio_nacimiento);
        $this->assertDatabaseHas("actividad", [
            "accion" => "municipio_ni_editar",
        ]);
    }
    public function test_formulario_correos_acepta_615_y_reutiliza_datos_del_catalogo()
    {
        $u = $this->usuario("cyber");
        $this->actingAs($u)
            ->post(route("correos.guardar"), [
                "pais" => "NI",
                "tipo_documento" => "cedula",
                "numero_documento" => $this->cedula("615"),
                "primer_nombre" => "Cliente",
                "primer_apellido" => "De prueba",
                "celular" => "+505 8888 8888",
                "proveedor" => "Otro",
                "correo" => "prueba@example.com",
                "clave_correo" => "Clave de ejemplo",
                "confirmado" => 1,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas("clientes", [
            "municipio_nacimiento" => "Bocana de Paiwas",
            "departamento_nacimiento" => "Costa Caribe Sur",
            "fecha_nacimiento" => "1990-01-01 00:00:00",
        ]);
    }
    public function test_catalogo_base_funciona_antes_de_la_migracion()
    {
        Schema::drop("municipios_nicaragua");
        $v = CedulaValidator::validar($this->cedula("615"));
        $this->assertTrue($v["valida"]);
        $this->assertSame("Bocana de Paiwas", $v["municipio"]);
    }
    public function test_registro_normal_del_cv_usa_el_mismo_catalogo_para_615():void {
        $u=$this->usuario('cyber');$n=$this->cedula('615');
        $this->actingAs($u)->post(route('clientes.store'),['pais'=>'NI','pais_residencia'=>'NI','tipo_documento'=>'cedula','numero_documento'=>$n,'primer_nombre'=>'Cliente','primer_apellido'=>'De prueba','nacionalidad'=>'Nicaragüense','municipio_nacimiento'=>'Dato incorrecto','departamento_nacimiento'=>'Otro'])->assertRedirect();
        $c=Cliente::where('numero_documento',$n)->firstOrFail();$this->assertSame('Bocana de Paiwas',$c->municipio_nacimiento);$this->assertSame('Costa Caribe Sur',$c->departamento_nacimiento);$this->assertSame('1990-01-01',$c->fecha_nacimiento->format('Y-m-d'));
    }
    public function test_registro_especial_conserva_lugar_manual_sin_inventar_departamento():void {
        $u=$this->usuario('cyber');$n=$this->cedula('888');
        $this->actingAs($u)->post(route('clientes.store'),['pais'=>'NI','pais_residencia'=>'NI','tipo_documento'=>'cedula','numero_documento'=>$n,'primer_nombre'=>'Cliente','primer_apellido'=>'De prueba','nacionalidad'=>'Nicaragüense','municipio_nacimiento'=>'Ciudad indicada en el documento','departamento_nacimiento'=>'Lugar indicado'])->assertRedirect();
        $c=Cliente::where('numero_documento',$n)->firstOrFail();$this->assertSame('Ciudad indicada en el documento',$c->municipio_nacimiento);$this->assertSame('Lugar indicado',$c->departamento_nacimiento);
    }

}
