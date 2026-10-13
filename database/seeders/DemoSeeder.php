<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Cyber;
use Illuminate\Database\Seeder;

/** Un cliente de ejemplo con el CV completo, para probar los dos diseños. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::actual();

        $cyber1 = Cyber::firstOrCreate(['codigo' => 'CY001'], [
            'nombre' => 'Cyber San Nicolás', 'color_primario' => '#0B3C74', 'color_secundario' => '#C9971F',
            'telefono' => '2551-0000', 'direccion' => 'San Nicolás, Cartago', 'activo' => true,
        ]);
        Cyber::firstOrCreate(['codigo' => 'CY002'], [
            'nombre' => 'Cyber Centro', 'color_primario' => '#0F3D3E', 'color_secundario' => '#2BB58F', 'activo' => true,
        ]);

        if (Cliente::where('numero_documento', '304560789')->exists()) {
            return;
        }

        $c = Cliente::create([
            'pais' => 'CR', 'tipo_documento' => 'cedula_cr', 'numero_documento' => '304560789', 'inss_nss' => '304560789', 'edad_calculada' => 30,
            'primer_nombre' => 'María', 'segundo_nombre' => 'José',
            'primer_apellido' => 'Ramírez', 'segundo_apellido' => 'Solano',
            'fecha_nacimiento' => '1996-04-18', 'municipio_nacimiento' => 'Oreamuno', 'departamento_nacimiento' => 'Cartago',
            'sexo' => 'F', 'nacionalidad' => 'Costarricense', 'numero_hijos' => 1, 'estado_civil' => 'Casado(a)',
            'profesion' => 'Técnica en Contabilidad',
            'perfil_profesional' => 'Profesional responsable y organizada, con experiencia en registros contables, facturación y atención al cliente. Me adapto con facilidad al trabajo en equipo y busco aportar orden y resultados medibles en cada tarea.',
            'correo' => 'maria.ramirez@ejemplo.com', 'telefono_codigo_pais' => '+506', 'telefono' => '8888-1234', 'telefono_red' => 'Kölbi',
            'telefono_secundario' => '7000-5678', 'direccion' => 'San Nicolás, Cartago, 200 m norte de la iglesia',
            'nombre_padre' => 'Carlos Ramírez Mora', 'nombre_madre' => 'Ana Solano Quesada',
        ]);

        $c->cybers()->attach($cyber1->id);

        $c->estudios()->createMany([
            ['nivel' => 'universidad', 'titulo' => 'Bachillerato en Contaduría Pública', 'institucion' => 'Universidad Técnica Nacional', 'anio' => 2021, 'orden' => 0],
            ['nivel' => 'tecnico', 'titulo' => 'Técnico en Contabilidad y Finanzas', 'institucion' => 'CTP de Cartago', 'anio' => 2014, 'orden' => 1],
            ['nivel' => 'curso', 'titulo' => 'Excel intermedio y avanzado', 'institucion' => 'INA', 'anio' => 2019, 'orden' => 2],
        ]);
        $c->experiencias()->createMany([
            ['cargo' => 'Asistente contable', 'empresa' => 'Distribuidora El Valle S.A.', 'fecha_inicio' => '2021-02-01', 'descripcion' => 'Registro de asientos, conciliaciones bancarias, cuentas por pagar y declaraciones de IVA.', 'orden' => 0],
            ['cargo' => 'Cajera y facturación', 'empresa' => 'Supermercado La Económica', 'fecha_inicio' => '2017-06-01', 'fecha_fin' => '2021-01-15', 'descripcion' => 'Cierres de caja diarios, facturación electrónica y servicio al cliente.', 'orden' => 1],
        ]);
        foreach (['Contabilidad general', 'Facturación electrónica', 'Microsoft Excel', 'Atención al cliente', 'Trabajo en equipo', 'Inglés básico'] as $i => $h) {
            $c->habilidades()->create(['habilidad' => $h, 'orden' => $i]);
        }
        $c->referencias()->createMany([
            ['tratamiento' => 'Lic.', 'nombre' => 'Jorge Castillo Vargas', 'telefono' => '8765-4321', 'correo' => 'jcastillo@ejemplo.com', 'orden' => 0],
            ['tratamiento' => 'Sra.', 'nombre' => 'Patricia Mora Brenes', 'telefono' => '8811-2233', 'orden' => 1],
        ]);
    }
}
