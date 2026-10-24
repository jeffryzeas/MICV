<?php

namespace App\Support;

use App\Models\AutoservicioSolicitud;
use App\Models\SolicitudEliminacion;
use App\Models\User;

/** Menú lateral (mismo formato que el Sistema JZ: id, url, icono lucide, etiqueta). */
class Menu
{
    private static function pendientes(): int
    {
        try {
            return SolicitudEliminacion::where("estado", "pendiente")->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function autoservicioPendientes(User $u): int
    {
        try {
            return AutoservicioSolicitud::query()
                ->de($u)
                ->where("estado", "pendiente")
                ->whereDate("fecha", ">=", today()->subDay())
                ->count();
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
                [
                    "id" => "chat",
                    "url" => route("chat.index"),
                    "icon" => "messages-square",
                    "label" => "Mensajes y mis trabajos",
                ],
                [
                    "id" => "mi-panel",
                    "url" => route("mi-cv.panel"),
                    "icon" => "layout-dashboard",
                    "label" => "Mi panel y mi CV",
                ],
                [
                    "id" => "mi-datos",
                    "url" => route("mi-cv.editar"),
                    "icon" => "user-round-pen",
                    "label" => "Mis datos",
                ],
                [
                    "id" => "cartas",
                    "url" => route("cartas.index"),
                    "icon" => "mail",
                    "label" => "Mis cartas",
                ],
                [
                    "id" => "tarjeta",
                    "url" => route("tarjetas.mia"),
                    "icon" => "contact",
                    "label" => "Mi tarjeta de presentación",
                ],
                [
                    "id" => "perfil",
                    "url" => route("perfil.edit"),
                    "icon" => "lock",
                    "label" => "Correo y contraseña",
                ],
                [
                    "id" => "cuenta",
                    "url" => route("mi-cuenta.mostrar"),
                    "icon" => "shield",
                    "label" => "Privacidad y cuenta",
                ],
            ];
        }

        $items = [
            ['id'=>'correos','url'=>route('correos.index'),'icon'=>'mail-plus','label'=>'Correos de clientes'],
            [
                "id" => "chat",
                "url" => route("chat.index"),
                "icon" => "messages-square",
                "label" => "SMS JZ · Mensajes",
            ],
            [
                "id" => "tarjetas",
                "url" => route("tarjetas.index"),
                "icon" => "contact",
                "label" => "Tarjetas de presentación",
            ],
            [
                "id" => "dashboard",
                "url" => route("dashboard"),
                "icon" => "layout-dashboard",
                "label" => $u->esAdmin() ? "Dashboard" : "Panel",
            ],
            [
                "id" => "clientes-nuevo",
                "url" => route("clientes.create"),
                "icon" => "user-plus",
                "label" => "Registrar cliente",
            ],
            [
                "id" => "clientes",
                "url" => route("clientes.index"),
                "icon" => "list",
                "label" => $u->esAdmin()
                    ? "Clientes y usuarios"
                    : "Lista de clientes",
            ],
            [
                "id" => "cartas",
                "url" => route("cartas.index"),
                "icon" => "mail",
                "label" => "Cartas",
            ],
            [
                "id" => "fotos",
                "url" => route("fotos.index"),
                "icon" => "camera",
                "label" => "Fotos carné y pasaporte",
            ],
            [
                "id" => "autoservicio",
                "url" => route("autoservicio.panel"),
                "icon" => "qr-code",
                "label" => "Autoservicio (QR)",
                "badge" => self::autoservicioPendientes($u) ?: null,
            ],
            [
                "id" => "pos",
                "icon" => "shopping-cart",
                "label" => "Punto de venta",
                "hijos" => [
                    [
                        "id" => "pos-vender",
                        "url" => route("pos.vender"),
                        "icon" => "scan-barcode",
                        "label" => "Vender",
                    ],
                    [
                        "id" => "pos-caja",
                        "url" => route("pos.caja"),
                        "icon" => "wallet",
                        "label" => "Caja (abrir / cerrar)",
                    ],
                    [
                        "id" => "pos-ventas",
                        "url" => route("pos.ventas"),
                        "icon" => "receipt-text",
                        "label" => "Ventas y comprobantes",
                    ],
                    [
                        "id" => "pos-productos",
                        "url" => route("pos.productos"),
                        "icon" => "package",
                        "label" => "Productos e inventario",
                    ],
                    [
                        "id" => "pos-compras",
                        "url" => route("pos.compras"),
                        "icon" => "truck",
                        "label" => "Compras",
                    ],
                    [
                        "id" => "pos-proveedores",
                        "url" => route("pos.proveedores"),
                        "icon" => "factory",
                        "label" => "Proveedores",
                    ],
                    [
                        "id" => "pos-reportes",
                        "url" => route("pos.reportes"),
                        "icon" => "chart-column",
                        "label" => "Reportes",
                    ],
                    [
                        "id" => "pos-config",
                        "url" => route("pos.config"),
                        "icon" => "settings",
                        "label" => "Configuración",
                    ],
                ],
            ],
        ];

        if ($u->esAdmin()) {
            $items = array_merge($items, [
                ['id'=>'supervision','url'=>route('admin.chat.supervision'),'icon'=>'shield-check','label'=>'Supervisión de mensajes'],
                [
                    "id" => "cybers",
                    "url" => route("admin.cybers.index"),
                    "icon" => "building-2",
                    "label" => "Negocios",
                ],
                [
                    "id" => "usuarios",
                    "url" => route("admin.usuarios.index"),
                    "icon" => "user-cog",
                    "label" => "Usuarios",
                ],
                [
                    "id" => "solicitudes",
                    "url" => route("admin.solicitudes.index"),
                    "icon" => "user-x",
                    "label" => "Solicitudes de eliminación",
                    "badge" => self::pendientes() ?: null,
                ],
                [
                    "id" => "avisos",
                    "url" => route("admin.avisos.index"),
                    "icon" => "megaphone",
                    "label" => "Avisos por correo",
                ],
                [
                    "id" => "admin-config",
                    "icon" => "settings",
                    "label" => "Configuración",
                    "hijos" => [
                        [
                            "id" => "marca-sistema",
                            "url" => route("admin.marca.edit"),
                            "icon" => "palette",
                            "label" => "Marca del sistema",
                        ],
                        [
                            "id" => "configuracion",
                            "url" => route("admin.configuracion.edit"),
                            "icon" => "ruler",
                            "label" => "Config. y medidas del CV",
                        ],
                        ['id'=>'municipios-ni','url'=>route('municipios.index'),'icon'=>'map-pinned','label'=>'Municipios de Nicaragua'],
                        [
                            "id" => "paises",
                            "url" => route("admin.paises.index"),
                            "icon" => "globe",
                            "label" => "Países y documentos",
                        ],
                        [
                            "id" => "legal",
                            "url" => route("admin.legal.edit"),
                            "icon" => "scale",
                            "label" => "Textos legales",
                        ],
                        [
                            "id" => "correo",
                            "url" => route("admin.correo.edit"),
                            "icon" => "mail-check",
                            "label" => "Correo Gmail y registro",
                        ],
                    ],
                ],
                [
                    "id" => "admin-seguridad",
                    "icon" => "shield",
                    "label" => "Seguridad",
                    "hijos" => [
                        [
                            "id" => "bloqueos",
                            "url" => route("admin.bloqueos.index"),
                            "icon" => "shield-ban",
                            "label" => "Lista privada",
                        ],
                        [
                            "id" => "recaptcha",
                            "url" => route("admin.recaptcha.edit"),
                            "icon" => "shield-check",
                            "label" => "Google reCAPTCHA v3",
                        ],
                        [
                            "id" => "actividad",
                            "url" => route("admin.actividad"),
                            "icon" => "scroll-text",
                            "label" => "Auditoría",
                        ],
                        [
                            "id" => "errores",
                            "url" => route("admin.errores.index"),
                            "icon" => "bug",
                            "label" => "Errores del sistema",
                        ],
                    ],
                ],
            ]);
        } else {
            $items[] = [
                "id" => "marca",
                "url" => route("marca.edit"),
                "icon" => "palette",
                "label" => "Mi marca",
            ];
        }


        $items[] = [
            "id" => "perfil",
            "url" => route("perfil.edit"),
            "icon" => "user-circle",
            "label" => "Mi perfil y contraseña",
        ];

        if ($u->esAdmin() || $u->esEncargado()) {
            $items[] = [
                "id" => "equipo",
                "url" => route("equipo.index"),
                "icon" => "users-round",
                "label" => "Mi equipo",
            ];
        }
        $items[] = [
            "id" => "documentos",
            "url" => route("documentos.index"),
            "icon" => "scan-line",
            "label" => "Editor y copias de documentos",
        ];
        if ($u->tienePermiso('contabilidad.ver')) $items[]=['id'=>'contabilidad','url'=>route('contabilidad.index'),'icon'=>'calculator','label'=>'Ingresos y gastos'];
        $mapa = [
            'contabilidad'=>'contabilidad.ver',
            "correos" => "correos",
            "chat" => "chat",
            "tarjetas" => "tarjetas",
            "clientes-nuevo" => "clientes.editar",
            "clientes" => "clientes.ver",
            "cartas" => "cartas",
            "fotos" => "fotos",
            "autoservicio" => "autoservicio",
            "marca" => "marca",
            "equipo" => "personal",
            "documentos" => "documentos",
            "pos-vender" => "pos.vender",
            "pos-caja" => "pos.caja",
            "pos-ventas" => "pos.ventas",
            "pos-productos" => "pos.productos",
            "pos-compras" => "pos.compras",
            "pos-proveedores" => "pos.proveedores",
            "pos-reportes" => "pos.reportes",
            "pos-config" => "pos.config",
        ];
        foreach ($items as &$item) {
            if (!empty($item["hijos"])) {
                $item["hijos"] = array_values(
                    array_filter(
                        $item["hijos"],
                        fn($h) => !isset($mapa[$h["id"]]) ||
                            $u->tienePermiso($mapa[$h["id"]]),
                    ),
                );
            }
        }
        unset($item);
        // Daily work first, administrative settings last; permission filtering stays below.
        $orden = array_flip(['dashboard', 'clientes', 'clientes-nuevo', 'autoservicio', 'pos', 'contabilidad', 'documentos', 'fotos', 'tarjetas', 'cartas', 'correos', 'chat', 'equipo', 'marca', 'cybers', 'usuarios', 'configuracion', 'seguridad', 'perfil']);
        usort($items, fn ($a, $b) => ($orden[$a['id']] ?? 16) <=> ($orden[$b['id']] ?? 16));

        return array_values(
            array_filter(
                $items,
                fn($i) => (!isset($mapa[$i["id"]]) ||
                    $u->tienePermiso($mapa[$i["id"]])) &&
                    ($i["id"] !== "pos" || !empty($i["hijos"])),
            ),
        );
    }
}
