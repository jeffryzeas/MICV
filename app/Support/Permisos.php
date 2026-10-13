<?php

namespace App\Support;

class Permisos
{
    public const LISTA = [
        "correos" => "Registrar correos de clientes",
        "chat" => "Mensajes y trabajos (SMS JZ)",
        "clientes.acceso" => "Generar acceso del cliente",
        "clientes.ver" => "Ver clientes",
        "clientes.editar" => "Crear y editar clientes",
        "clientes.eliminar" => "Eliminar clientes",
        "cv" => "Generar CV",
        "cartas" => "Cartas",
        "tarjetas" => "Tarjetas",
        "fotos" => "Fotos carné",
        "documentos" => "Editor y copias de documentos",
        "autoservicio" => "Autoservicio QR",
        "pos.vender" => "Cobrar ventas",
        "pos.precios" => "Cambiar precios / artículos libres",
        "pos.descuentos" => "Aplicar descuentos",
        "pos.caja" => "Abrir y ver caja",
        "pos.caja.movimientos" => "Ingresos y retiros de caja",
        "pos.caja.cerrar" => "Cerrar caja",
        "pos.ventas" => "Ver ventas y comprobantes",
        "pos.anular" => "Anular ventas",
        "pos.productos" => "Ver productos e inventario",
        "pos.productos.editar" => "Crear y editar productos",
        "pos.ajustar" => "Ajustar inventario",
        "pos.compras" => "Ver compras",
        "pos.compras.editar" => "Registrar compras",
        "pos.compras.anular" => "Anular compras",
        "pos.proveedores" => "Proveedores",
        "pos.reportes" => "Reportes y Excel",
        "pos.config" => "Configurar POS",
        "marca" => "Marca del Cyber",
        "personal" => "Administrar cajeros",
    ];

    public const PRESETS = [
        "cajero" => ["pos.vender", "pos.caja", "pos.ventas", "documentos"],
        "operador" => [
            "chat",
            "clientes.ver",
            "clientes.editar",
            "cv",
            "cartas",
            "tarjetas",
            "fotos",
            "documentos",
            "autoservicio",
        ],
    ];

    public static function perfil(string $perfil): array
    {
        return $perfil === "encargado"
            ? array_keys(self::LISTA)
            : self::PRESETS[$perfil] ?? [];
    }

    public static function completar(array $permisos): array
    {
        $deps = [
            "clientes.acceso" => ["clientes.ver"],
            "clientes.editar" => ["clientes.ver"],
            "clientes.eliminar" => ["clientes.ver"],
            "cv" => ["clientes.ver"],
            "tarjetas" => ["clientes.ver"],
            "pos.vender" => ["pos.ventas", "pos.caja"],
            "pos.anular" => ["pos.ventas"],
            "pos.caja.cerrar" => ["pos.caja"],
            "pos.caja.movimientos" => ["pos.caja"],
            "pos.productos.editar" => ["pos.productos"],
            "pos.ajustar" => ["pos.productos"],
            "pos.compras.editar" => ["pos.compras"],
            "pos.compras.anular" => ["pos.compras"],
        ];
        foreach ($permisos as $p) {
            $permisos = array_merge($permisos, $deps[$p] ?? []);
        }

        return array_values(
            array_unique(array_intersect(array_keys(self::LISTA), $permisos)),
        );
    }

    /** Una ruta puede admitir cualquiera de los permisos indicados. */
    public static function deRuta(?string $r): array
    {
        if (!$r) {
            return [];
        }
        $exactos = [
            "clientes.acceso" => "clientes.acceso",
            "clientes.acceso.crear" => "clientes.acceso",
            "clientes.create" => "clientes.editar",
            "clientes.store" => "clientes.editar",
            "clientes.edit" => "clientes.editar",
            "clientes.update" => "clientes.editar",
            "clientes.destroy" => "clientes.eliminar",
            "pos.vender" => "pos.vender",
            "pos.ventas.guardar" => "pos.vender",
            "pos.ventas.anular" => "pos.anular",
            "pos.caja.movimiento" => "pos.caja.movimientos",
            "pos.caja.cerrar" => "pos.caja.cerrar",
            "pos.productos.foto" => ["pos.vender", "pos.productos"],
            "pos.productos.ajuste" => "pos.ajustar",
            "pos.productos.crear" => "pos.productos.editar",
            "pos.productos.editar" => "pos.productos.editar",
            "pos.productos.guardar" => "pos.productos.editar",
            "pos.productos.actualizar" => "pos.productos.editar",
            "pos.productos.borrar" => "pos.productos.editar",
            "pos.productos.sugeridos" => "pos.productos.editar",
            "pos.compras.crear" => "pos.compras.editar",
            "pos.compras.guardar" => "pos.compras.editar",
            "pos.compras.anular" => "pos.compras.anular",
            "cartas.buscar" => [
                "cartas",
                "pos.vender",
                "fotos",
                "cv",
                "tarjetas",
            ],
            "api.consultar-documento" => ["clientes.editar", "correos"],
            "autoservicio.alternar" => "autoservicio",
            "clientes.foto" => ["clientes.ver", "cv", "tarjetas", "fotos"],
        ];
        if (isset($exactos[$r])) {
            return (array) $exactos[$r];
        }
        foreach (
            [
                "correos." => "correos",
                "chat." => "chat",
                "equipo." => "personal",
                "documentos." => "documentos",
                "cv." => "cv",
                "clientes." => "clientes.ver",
                "cartas." => "cartas",
                "tarjetas." => "tarjetas",
                "fotos." => "fotos",
                "autoservicio." => "autoservicio",
                "pos.caja" => "pos.caja",
                "pos.cajas" => "pos.caja",
                "pos.ventas" => "pos.ventas",
                "pos.productos" => "pos.productos",
                "pos.categorias." => "pos.productos.editar",
                "pos.compras" => "pos.compras",
                "pos.proveedores" => "pos.proveedores",
                "pos.reportes" => "pos.reportes",
                "pos.config" => "pos.config",
                "marca." => "marca",
            ]
            as $prefijo => $permiso
        ) {
            if (str_starts_with($r, $prefijo)) {
                return [$permiso];
            }
        }

        return [];
    }
}
