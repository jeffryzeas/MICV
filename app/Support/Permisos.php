<?php

namespace App\Support;

class Permisos
{
    public const LISTA = [
        'contabilidad.ver'=>'Ver ingresos, gastos y saldo', 'contabilidad.registrar'=>'Registrar ingresos y gastos', 'contabilidad.anular'=>'Anular movimientos manuales', 'contabilidad.exportar'=>'Exportar libro de ingresos y gastos',
        "correos" => "Registrar correos de clientes",
        "chat" => "Mensajes y trabajos (SMS JZ)",
        "clientes.permisos" => "Administrar edición del portal del cliente",
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
        "marca" => "Marca del negocio",
        "personal" => "Administrar usuarios del negocio",
    ];

    public const PRESETS = [
        'contador'=>['contabilidad.ver','contabilidad.registrar','contabilidad.exportar'],
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
            "clientes.permisos"=>["clientes.ver"],
            'contabilidad.registrar'=>['contabilidad.ver'], 'contabilidad.anular'=>['contabilidad.ver'], 'contabilidad.exportar'=>['contabilidad.ver'],
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
            "clientes.acceso.permisos"=>"clientes.permisos",
            'contabilidad.index'=>'contabilidad.ver', 'contabilidad.create'=>'contabilidad.registrar', 'contabilidad.store'=>'contabilidad.registrar', 'contabilidad.anular'=>'contabilidad.anular', 'contabilidad.exportar'=>'contabilidad.exportar',
            "municipios.index" => ["clientes.ver", "correos"],
            "clientes.acceso" => ["clientes.acceso","clientes.permisos"],
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
                'contabilidad.' => 'contabilidad.ver',
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
