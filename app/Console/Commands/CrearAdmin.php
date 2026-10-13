<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * php artisan micv:crear-admin
 * Crea (o repara) un usuario administrador desde la terminal del VPS.
 * Si el usuario ya existe, le pone la nueva contraseña, lo vuelve admin y lo activa.
 */
class CrearAdmin extends Command
{
    protected $signature = 'micv:crear-admin
        {--usuario= : Nombre de usuario (sin espacios)}
        {--email= : Correo}
        {--nombre= : Nombre completo}
        {--password= : Contraseña (mínimo 8). Si no se da, se pregunta en oculto}';

    protected $description = 'Crea un usuario administrador (o restablece uno existente)';

    public function handle(): int
    {
        $usuario = mb_strtoupper(trim((string) ($this->option('usuario') ?: $this->ask('Usuario (sin espacios)', 'ADMIN'))));
        $existente = User::where('usuario', $usuario)->first();

        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Correo', $existente?->email ?? 'admin@micv.local'))));
        $nombre = trim((string) ($this->option('nombre') ?: $this->ask('Nombre completo', $existente?->name ?? 'Administrador')));
        $password = (string) ($this->option('password') ?: $this->secret('Contraseña (mínimo 8 caracteres)'));

        $v = Validator::make(compact('usuario', 'email', 'nombre', 'password'), [
            'usuario'  => ['required', 'min:3', 'max:40', 'regex:/^[A-Z0-9._-]+$/'],
            'email'    => ['required', 'email', 'max:150'],
            'nombre'   => ['required', 'max:150'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }
            return self::FAILURE;
        }

        $otroConCorreo = User::where('email', $email)->when($existente, fn ($q) => $q->where('id', '!=', $existente->id))->exists();
        if ($otroConCorreo) {
            $this->error("Ya existe otro usuario con el correo $email.");
            return self::FAILURE;
        }

        $datos = ['usuario' => $usuario, 'email' => $email, 'name' => $nombre, 'password' => $password, 'rol' => 'admin', 'activo' => true];

        if ($existente) {
            $existente->update($datos);
            $this->info("Usuario $usuario actualizado: ahora es administrador activo con la nueva contraseña.");
        } else {
            User::create($datos);
            $this->info("Administrador $usuario creado. Ya puede entrar al sistema.");
        }

        return self::SUCCESS;
    }
}
