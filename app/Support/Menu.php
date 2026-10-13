<?php

namespace App\Support;

use App\Models\User;

/** Menú lateral (mismo formato que el Sistema JZ: id, url, icono lucide, etiqueta). */
class Menu
{
    private static function pendientes(): int
    {
        try {
            return \App\Models\SolicitudEliminacion::where('estado', 'pendiente')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function para(?User $u): array
    {
        if (!$u) {
            return [];
        }

        if ($u->esCliente()) {
            return [
                ['id' => 'mi-panel', 'url' => route('mi-cv.panel'),  'icon' => 'layout-dashboard', 'label' => 'Mi panel y mi CV'],
                ['id' => 'mi-datos', 'url' => route('mi-cv.editar'), 'icon' => 'user-round-pen',   'label' => 'Mis datos'],
                ['id' => 'cartas',   'url' => route('cartas.index'), 'icon' => 'mail',             'label' => 'Mis cartas'],
                ['id' => 'perfil',   'url' => route('perfil.edit'),  'icon' => 'lock',             'label' => 'Correo y contraseña'],
                ['id' => 'cuenta',   'url' => route('mi-cuenta.mostrar'), 'icon' => 'shield',       'label' => 'Privacidad y cuenta'],
            ];
        }

        $items = [
            ['id' => 'dashboard',      'url' => route('dashboard'),       'icon' => 'layout-dashboard', 'label' => $u->esAdmin() ? 'Dashboard' : 'Panel'],
            ['id' => 'clientes-nuevo', 'url' => route('clientes.create'), 'icon' => 'user-plus',        'label' => 'Registrar cliente'],
            ['id' => 'clientes',       'url' => route('clientes.index'),  'icon' => 'list',             'label' => $u->esAdmin() ? 'Clientes y usuarios' : 'Lista de clientes'],
            ['id' => 'cartas',         'url' => route('cartas.index'),    'icon' => 'mail',             'label' => 'Cartas'],
        ];

        if ($u->esAdmin()) {
            $items = array_merge($items, [
                ['id' => 'cybers',        'url' => route('admin.cybers.index'),        'icon' => 'building-2',  'label' => 'Cybers'],
                ['id' => 'usuarios',      'url' => route('admin.usuarios.index'),      'icon' => 'user-cog',    'label' => 'Usuarios'],
                ['id' => 'marca-sistema', 'url' => route('admin.marca.edit'),          'icon' => 'palette',     'label' => 'Marca del sistema'],
                ['id' => 'configuracion', 'url' => route('admin.configuracion.edit'),  'icon' => 'ruler',       'label' => 'Config. y medidas del CV'],
                ['id' => 'legal',         'url' => route('admin.legal.edit'),          'icon' => 'scale',       'label' => 'Textos legales'],
                ['id' => 'solicitudes',   'url' => route('admin.solicitudes.index'),   'icon' => 'user-x',      'label' => 'Solicitudes de eliminación', 'badge' => self::pendientes() ?: null],
                ['id' => 'correo',        'url' => route('admin.correo.edit'),         'icon' => 'mail-check',  'label' => 'Correo Gmail y registro'],
                ['id' => 'recaptcha',     'url' => route('admin.recaptcha.edit'),      'icon' => 'shield-check','label' => 'Google reCAPTCHA v3'],
                ['id' => 'actividad',     'url' => route('admin.actividad'),           'icon' => 'scroll-text', 'label' => 'Auditoría'],
                ['id' => 'errores',       'url' => route('admin.errores.index'),       'icon' => 'bug',         'label' => 'Errores del sistema'],
            ]);
        } else {
            $items[] = ['id' => 'marca', 'url' => route('marca.edit'), 'icon' => 'palette', 'label' => 'Mi marca'];
        }

        $items[] = ['id' => 'perfil', 'url' => route('perfil.edit'), 'icon' => 'user-circle', 'label' => 'Mi perfil y contraseña'];

        return $items;
    }
}
