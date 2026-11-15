<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CodigoVerificacion;
use App\Models\Cyber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuariosAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_combina_filtros_y_reserva_el_modulo_al_administrador(): void
    {
        $admin = $this->cuenta('ADMIN', 'admin');
        $pendiente = $this->cuenta('CLIENTE-PENDIENTE');
        $verificado = $this->cuenta('CLIENTE-VERIFICADO');
        $inactivo = $this->cuenta('CLIENTE-INACTIVO');
        $verificado->forceFill(['email_verified_at' => now(), 'terminos_version' => '1.0'])->save();
        $inactivo->update(['activo' => false]);

        $this->actingAs($verificado)->get(route('admin.usuarios.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.usuarios.index', [
            'rol' => 'cliente', 'estado' => 'activo', 'verificacion' => 'pendiente',
        ]))->assertOk()->assertViewHas('usuarios', fn ($usuarios) => $usuarios->pluck('id')->all() === [$pendiente->id]);

        $this->get(route('admin.usuarios.index', ['verificacion' => 'verificado']))
            ->assertOk()->assertViewHas('usuarios', fn ($usuarios) => $usuarios->pluck('id')->all() === [$verificado->id]);

        $this->get(route('admin.usuarios.index', ['q' => ['x'], 'cyber_id' => ['x']]))->assertOk();
        $this->get(route('admin.usuarios.create'))->assertOk()->assertSee('Rol y permisos');
        $this->get(route('admin.usuarios.edit', $pendiente))->assertOk()->assertSee('Permisos de edición del cliente');
    }

    public function test_crea_cajero_con_encargado_del_mismo_negocio_y_valida_el_acceso(): void
    {
        $admin = $this->cuenta('ADMIN', 'admin');
        $negocio = Cyber::create(['codigo' => 'UNO', 'nombre' => 'Comercio Uno']);
        $otro = Cyber::create(['codigo' => 'DOS', 'nombre' => 'Comercio Dos']);
        $encargado = $this->cuenta('ENCARGADO', 'cyber');
        $encargado->update(['cyber_id' => $negocio->id]);
        $datos = [
            'name' => 'Empleado', 'usuario' => 'empleado', 'email' => 'EMPLEADO@example.test',
            'rol' => 'cyber', 'cyber_id' => $otro->id, 'perfil_acceso' => 'cajero',
            'encargado_id' => $encargado->id, 'activo' => '1',
            'password' => 'UnaClaveLarga', 'password_confirmation' => 'UnaClaveLarga',
        ];

        $this->actingAs($admin)->post(route('admin.usuarios.store'), $datos)->assertSessionHasErrors('encargado_id');
        $datos['cyber_id'] = $negocio->id;
        $this->post(route('admin.usuarios.store'), $datos)->assertRedirect(route('admin.usuarios.index'));

        $cajero = User::where('usuario', 'EMPLEADO')->firstOrFail();
        $this->assertSame($encargado->id, $cajero->encargado_id);
        $this->assertSame('empleado@example.test', $cajero->email);
        $this->assertTrue(Hash::check('UnaClaveLarga', $cajero->password));
    }

    public function test_cambiar_el_correo_requiere_verificar_la_nueva_direccion(): void
    {
        $admin = $this->cuenta('ADMIN', 'admin');
        $cliente = $this->cuenta('CLIENTE');
        $cliente->forceFill(['email_verified_at' => now()])->save();
        $ficha = Cliente::create([
            'primer_nombre' => 'Ana', 'primer_apellido' => 'Pérez',
            'correo' => $cliente->email, 'user_id' => $cliente->id,
        ]);
        CodigoVerificacion::create([
            'user_id' => $cliente->id, 'tipo' => 'recuperar',
            'codigo' => Hash::make('123456'), 'expira_en' => now()->addMinutes(5),
        ]);
        DB::table('jz_invitaciones')->insert([
            'user_id' => $cliente->id, 'token' => str_repeat('a', 64), 'vence' => now()->addDay(),
        ]);

        $this->actingAs($admin)->put(route('admin.usuarios.update', $cliente), [
            'name' => $cliente->name, 'usuario' => $cliente->usuario, 'email' => 'nuevo@example.test',
            'rol' => 'cliente', 'activo' => '1', 'edicion_cliente_enviada' => '1',
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertFalse($cliente->fresh()->verificado());
        $this->assertSame([], $cliente->fresh()->edicion_cliente);
        $this->assertSame('nuevo@example.test', $ficha->fresh()->correo);
        $this->assertDatabaseMissing('codigos_verificacion', ['user_id' => $cliente->id]);
        $this->assertDatabaseMissing('jz_invitaciones', ['user_id' => $cliente->id]);

        $this->post(route('admin.usuarios.verificar', $cliente), ['email' => $cliente->email])->assertSessionHasErrors('general');
        $this->assertFalse($cliente->fresh()->verificado());
        $this->post(route('admin.usuarios.verificar', $cliente), ['email' => 'nuevo@example.test'])->assertSessionHasNoErrors();
        $this->assertTrue($cliente->fresh()->verificado());
        $this->post(route('admin.usuarios.verificar', $admin))->assertForbidden();
    }

    public function test_repetir_una_desactivacion_y_proteger_la_cuenta_del_administrador(): void
    {
        $admin = $this->cuenta('ADMIN', 'admin');
        $cliente = $this->cuenta('CLIENTE');
        $this->actingAs($admin);

        $this->post(route('admin.usuarios.toggle', $cliente), ['activo' => '0'])->assertSessionHasNoErrors();
        $this->post(route('admin.usuarios.toggle', $cliente), ['activo' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($cliente->fresh()->activo);
        $this->assertDatabaseCount('actividad', 1);

        $this->post(route('admin.usuarios.toggle', $admin), ['activo' => '0'])->assertSessionHasErrors('general');
        $this->delete(route('admin.usuarios.destroy', $admin))->assertSessionHasErrors('general');
        $negocio = Cyber::create(['codigo' => 'UNO', 'nombre' => 'Comercio Uno']);
        $this->put(route('admin.usuarios.update', $admin), [
            'name' => $admin->name, 'usuario' => $admin->usuario, 'email' => $admin->email,
            'rol' => 'cyber', 'cyber_id' => $negocio->id, 'activo' => '1',
        ])->assertSessionHasErrors('rol');
        $this->assertTrue($admin->fresh()->esAdmin());
    }

    private function cuenta(string $usuario, string $rol = 'cliente'): User
    {
        return User::create([
            'usuario' => $usuario, 'name' => $usuario,
            'email' => strtolower($usuario).'@example.test',
            'password' => 'ClaveLarga12345', 'rol' => $rol, 'activo' => true,
        ]);
    }
}
