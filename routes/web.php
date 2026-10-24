<?php

use App\Http\Controllers\AccesoClienteController;
use App\Http\Controllers\Admin\ActividadController;
use App\Http\Controllers\Admin\AvisoController;
use App\Http\Controllers\Admin\BloqueoController;
use App\Http\Controllers\Admin\ChatAvisoController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\CorreoController;
use App\Http\Controllers\Admin\CyberController;
use App\Http\Controllers\Admin\ErrorController;
use App\Http\Controllers\Admin\LegalAdminController;
use App\Http\Controllers\Admin\MarcaSistemaController;
use App\Http\Controllers\Admin\PaisController;
use App\Http\Controllers\Admin\RecaptchaController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\Auth\InstaladorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperarController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\AutoservicioController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MiCvController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\TarjetaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Primera vez: crear el administrador (se bloquea solo cuando ya hay usuarios)
Route::get("instalar", [InstaladorController::class, "mostrar"])->name(
    "instalar.mostrar",
);
Route::post("instalar", [InstaladorController::class, "guardar"])
    ->name("instalar.guardar")
    ->middleware("throttle:registro");

// Logos: públicos a propósito (se ven en el login y en el menú)
Route::get("marca/logo", [ArchivoController::class, "logo"])->name(
    "marca.logo",
);
Route::get("cybers/{cyber}/logo", [
    ArchivoController::class,
    "logoCyber",
])->name("cybers.logo");

// Token nuevo para formularios abiertos mucho tiempo (evita "Página expirada")
Route::get(
    "csrf-token",
    fn(Request $r) => response()
        ->json(["token" => $r->session()->token()])
        ->header("Cache-Control", "no-store"),
)
    ->name("csrf.refrescar")
    ->middleware("throttle:token");

// Páginas legales y app (públicas)
Route::get("legal/{doc}", [LegalController::class, "ver"])->name("legal.ver");
Route::get("eliminar-cuenta", [
    CuentaController::class,
    "publicaMostrar",
])->name("eliminar.mostrar");
Route::get("manifest.webmanifest", [PwaController::class, "manifest"])->name(
    "pwa.manifest",
);
Route::get(".well-known/assetlinks.json", [
    PwaController::class,
    "assetlinks",
])->name("pwa.assetlinks");

// Activación privada del cliente (token aleatorio, de un solo uso).
Route::get("activar-acceso/{token}", [
    AccesoClienteController::class,
    "activar",
])
    ->name("acceso.activar")
    ->middleware("throttle:20,1");
Route::post("activar-acceso/{token}", [
    AccesoClienteController::class,
    "guardar",
])
    ->name("acceso.guardar")
    ->middleware("throttle:10,1");

// Autoservicio por QR (público: lo abre el cliente en su celular, con o sin sesión)
Route::get("a/{codigo}", [AutoservicioController::class, "formulario"])->name(
    "autoservicio.formulario",
);
Route::post("a/{codigo}", [AutoservicioController::class, "guardar"])
    ->name("autoservicio.guardar")
    ->middleware("throttle:autoservicio");
Route::get("a/{codigo}/listo", [AutoservicioController::class, "listo"])->name(
    "autoservicio.listo",
);

Route::middleware("guest")->group(function () {
    Route::get("recuperar", [RecuperarController::class, "mostrar"])->name(
        "recuperar.mostrar",
    );
    Route::post("recuperar", [RecuperarController::class, "enviar"])
        ->name("recuperar.enviar")
        ->middleware("throttle:recuperar");
    Route::get("recuperar/codigo", [
        RecuperarController::class,
        "codigo",
    ])->name("recuperar.codigo");
    Route::post("recuperar/codigo", [RecuperarController::class, "restablecer"])
        ->name("recuperar.restablecer")
        ->middleware("throttle:codigo");
    Route::get("/", [LoginController::class, "mostrar"])->name("login");
    Route::post("login", [LoginController::class, "iniciar"])
        ->name("login.iniciar")
        ->middleware("throttle:login");
    // Registro abierto para que cualquier persona haga su CV
    Route::get("registro", [RegistroController::class, "mostrar"])->name(
        "registro.mostrar",
    );
    Route::post("registro", [RegistroController::class, "guardar"])
        ->name("registro.guardar")
        ->middleware("throttle:registro");
    Route::get("registro/codigo", [
        RegistroController::class,
        "codigoMostrar",
    ])->name("registro.codigo");
    Route::post("registro/codigo", [RegistroController::class, "codigoGuardar"])
        ->name("registro.codigo.guardar")
        ->middleware("throttle:codigo");
    Route::post("registro/codigo/reenviar", [
        RegistroController::class,
        "codigoReenviar",
    ])
        ->name("registro.codigo.reenviar")
        ->middleware("throttle:envio-codigo");
    Route::post("registro/codigo/cancelar", [
        RegistroController::class,
        "codigoCancelar",
    ])->name("registro.codigo.cancelar");
});

// Verificación del correo con código (después de registrarse)
Route::middleware(["auth", "activo"])->group(function () {
    Route::get("verificar", [
        RegistroController::class,
        "verificarMostrar",
    ])->name("verificar.mostrar");
    Route::post("verificar", [RegistroController::class, "verificarGuardar"])
        ->name("verificar.guardar")
        ->middleware("throttle:codigo");
    Route::post("verificar/reenviar", [RegistroController::class, "reenviar"])
        ->name("verificar.reenviar")
        ->middleware("throttle:envio-codigo");
    Route::get("aceptar-terminos", [
        LegalController::class,
        "aceptarMostrar",
    ])->name("legal.aceptar");
    Route::post("aceptar-terminos", [
        LegalController::class,
        "aceptarGuardar",
    ])->name("legal.aceptar.guardar");
});

Route::middleware(["auth", "activo", "verificado", "terminos"])->group(
    function () {
        Route::post("salir", [LoginController::class, "salir"])
            ->name("logout")
            ->withoutMiddleware("verificado");
        Route::get("inicio", [DashboardController::class, "index"])->name(
            "dashboard",
        );

        Route::get('municipios-nicaragua',[\App\Http\Controllers\MunicipioNicaraguaController::class,'index'])->name('municipios.index')->middleware('rol:admin');
        Route::prefix('contabilidad')->name('contabilidad.')->middleware('rol:admin,cyber')->group(function(){
            $c=\App\Http\Controllers\ContabilidadController::class;
            Route::get('/',[$c,'index'])->name('index');
            Route::get('nuevo',[$c,'create'])->name('create');
            Route::post('/',[$c,'store'])->name('store')->middleware('throttle:30,1');
            Route::get('exportar',[$c,'exportar'])->name('exportar')->middleware('throttle:10,1');
            Route::post('{movimiento}/anular',[$c,'anular'])->name('anular')->middleware('throttle:15,1');
        });
        Route::prefix("correos-clientes")
            ->name("correos.")
            ->middleware("rol:admin,cyber")
            ->group(function () {
                $c = \App\Http\Controllers\CorreoServicioController::class;
                Route::get("/", [$c, "index"])->name("index");
                Route::post("/", [$c, "guardar"])
                    ->name("guardar")
                    ->middleware("throttle:20,1");
                Route::get("nuevo", [$c, "create"])->name("create");
                Route::get("{correo}", [$c, "ver"])->name("ver");
                Route::put("{correo}", [$c, "actualizar"])
                    ->name("actualizar")
                    ->middleware("throttle:20,1");
                Route::get("{correo}/pdf", [$c, "pdf"])
                    ->name("pdf")
                    ->middleware("throttle:10,1");
            });
        Route::prefix("mensajes")
            ->name("chat.")
            ->group(function () {
                $c = ChatController::class;
                Route::get("/", [$c, "index"])->name("index");
                Route::get("perfil/{usuario}/foto", [
                    \App\Http\Controllers\ChatPerfilController::class,
                    "foto",
                ])->name("perfil.foto");
                Route::get("perfil/{usuario}", [
                    \App\Http\Controllers\ChatPerfilController::class,
                    "ver",
                ])->name("perfil.ver");
                Route::post("perfil", [
                    \App\Http\Controllers\ChatPerfilController::class,
                    "guardar",
                ])
                    ->name("perfil.guardar")
                    ->middleware("throttle:chat-perfil");
                Route::get("lista", [$c, "lista"])
                    ->name("lista")
                    ->middleware("throttle:chat-leer");
                Route::get("contactos", [$c, "contactos"])
                    ->name("contactos")
                    ->middleware("throttle:chat-leer");
                Route::post("abrir", [$c, "abrir"])
                    ->name("abrir")
                    ->middleware("throttle:chat-escribir");
                Route::patch("mensaje/{mensaje}", [$c, "editarMensaje"])
                    ->name("mensaje.editar")
                    ->middleware("throttle:chat-escribir");
                Route::delete("mensaje/{mensaje}", [$c, "eliminarMensaje"])
                    ->name("mensaje.eliminar")
                    ->middleware("throttle:chat-escribir");
                Route::post("{chat}/sincronizar", [$c, "sincronizar"])
                    ->name("sincronizar")
                    ->middleware("throttle:chat-leer");
                Route::get("archivo/{mensaje}", [$c, "archivo"])->name(
                    "archivo",
                );
                Route::get("{chat}/archivos", [$c, "archivos"])
                    ->name("archivos")
                    ->middleware("throttle:chat-leer");
                Route::get("{chat}/historial", [$c, "mensajes"])
                    ->name("historial")
                    ->middleware("throttle:chat-leer");
                Route::post("{chat}/leer", [$c, "leer"])
                    ->name("leer")
                    ->middleware("throttle:chat-leer");
                Route::post("{chat}/enviar", [$c, "enviar"])
                    ->name("enviar")
                    ->middleware("throttle:chat-escribir");
            });
        // ── Panel del cliente que se registró solo
        Route::middleware("rol:cliente")
            ->prefix("mi-cv")
            ->name("mi-cv.")
            ->group(function () {
                Route::get("/", [MiCvController::class, "panel"])->name(
                    "panel",
                );
                Route::get("datos", [MiCvController::class, "editar"])->name(
                    "editar",
                );
                Route::put("datos", [MiCvController::class, "actualizar"])
                    ->name("actualizar")
                    ->middleware("throttle:guardar");
            });
        Route::middleware("rol:cliente")
            ->prefix("mi-cuenta")
            ->name("mi-cuenta.")
            ->group(function () {
                Route::get("/", [CuentaController::class, "mostrar"])->name(
                    "mostrar",
                );
                Route::get("descargar-datos", [
                    CuentaController::class,
                    "descargar",
                ])
                    ->name("descargar")
                    ->middleware("throttle:sensible");
                Route::post("eliminar", [CuentaController::class, "solicitar"])
                    ->name("solicitar")
                    ->middleware("throttle:sensible");
                Route::post("eliminar/cancelar", [
                    CuentaController::class,
                    "cancelar",
                ])->name("cancelar");
            });

        // ── Cartas en PDF (admin, cyber y cliente)
        Route::get("cartas", [CartaController::class, "index"])->name(
            "cartas.index",
        );
        Route::get("cartas/buscar-clientes", [
            CartaController::class,
            "buscarClientes",
        ])->name("cartas.buscar");
        Route::get("cartas/{tipo}", [CartaController::class, "create"])->name(
            "cartas.create",
        );
        Route::post("cartas/{tipo}/vista", [
            CartaController::class,
            "previsualizar",
        ])
            ->name("cartas.previsualizar")
            ->middleware("throttle:vista");
        Route::post("cartas/{tipo}", [CartaController::class, "generar"])
            ->name("cartas.generar")
            ->middleware("throttle:pdf");

        // ── CV (cada quien solo los clientes que puede ver) y foto
        Route::get("clientes/{cliente}/foto", [
            ArchivoController::class,
            "fotoCliente",
        ])->name("clientes.foto");
        Route::get("clientes/{cliente}/cv/vista", [
            CvController::class,
            "vista",
        ])
            ->name("cv.vista")
            ->middleware("throttle:vista");
        // Tarjetas de presentación
        Route::get("mi-tarjeta", [TarjetaController::class, "mia"])
            ->name("tarjetas.mia")
            ->middleware("rol:cliente");
        Route::get("tarjeta/{cliente}", [
            TarjetaController::class,
            "editar",
        ])->name("tarjetas.editar");
        Route::post("tarjeta/{cliente}/vista", [
            TarjetaController::class,
            "vista",
        ])
            ->name("tarjetas.vista")
            ->middleware("throttle:vista");
        Route::post("tarjeta/{cliente}", [TarjetaController::class, "guardar"])
            ->name("tarjetas.guardar")
            ->middleware("throttle:guardar");
        Route::post("tarjeta/{cliente}/exportar/{formato}", [
            TarjetaController::class,
            "exportar",
        ])
            ->name("tarjetas.exportar")
            ->middleware("throttle:pdf");
        Route::post("tarjeta/{cliente}/enviar", [
            TarjetaController::class,
            "enviar",
        ])
            ->name("tarjetas.enviar")
            ->middleware("throttle:cv-correo");
        Route::get("clientes/{cliente}/cv/pdf", [CvController::class, "pdf"])
            ->name("cv.pdf")
            ->middleware("throttle:pdf");
        Route::get("clientes/{cliente}/cv/word", [CvController::class, "word"])
            ->name("cv.word")
            ->middleware("throttle:pdf");
        Route::post("clientes/{cliente}/cv/enviar", [
            CvController::class,
            "enviar",
        ])
            ->name("cv.enviar")
            ->middleware("throttle:cv-correo");
        Route::post("api/consultar-documento", [
            DocumentoController::class,
            "consultar",
        ])
            ->name("api.consultar-documento")
            ->middleware("throttle:documento");

        // ── Mi perfil
        Route::get("perfil", [PerfilController::class, "editar"])->name(
            "perfil.edit",
        );
        Route::put("perfil", [PerfilController::class, "actualizar"])->name(
            "perfil.update",
        );
        Route::put("perfil/password", [
            PerfilController::class,
            "password",
        ])->name("perfil.password");

        Route::middleware("rol:admin,cyber")->group(function () {
            Route::get("tarjetas", [TarjetaController::class, "index"])->name(
                "tarjetas.index",
            );
            Route::get("clientes/{cliente}/acceso", [
                AccesoClienteController::class,
                "formulario",
            ])->name("clientes.acceso");
            Route::post("clientes/{cliente}/acceso", [
                AccesoClienteController::class,
                "crear",
            ])
                ->name("clientes.acceso.crear")
                ->middleware("throttle:10,1");
            // ── Clientes (admin ve todos; cada cyber ve solo los suyos)
            Route::get("clientes", [ClienteController::class, "index"])->name(
                "clientes.index",
            );
            Route::get("clientes/nuevo", [
                ClienteController::class,
                "create",
            ])->name("clientes.create");
            Route::post("clientes", [ClienteController::class, "store"])
                ->name("clientes.store")
                ->middleware("throttle:guardar");
            Route::get("clientes/exportar", [ClienteController::class, "exportar"])->name("clientes.exportar")->middleware("throttle:10,1");
            Route::get("clientes/{cliente}", [
                ClienteController::class,
                "show",
            ])->name("clientes.show");
            Route::get("clientes/{cliente}/editar", [
                ClienteController::class,
                "edit",
            ])->name("clientes.edit");
            Route::put("clientes/{cliente}", [
                ClienteController::class,
                "update",
            ])
                ->name("clientes.update")
                ->middleware("throttle:guardar");
            Route::delete("clientes/{cliente}", [
                ClienteController::class,
                "destroy",
            ])->name("clientes.destroy");

            Route::get("equipo", [EquipoController::class, "index"])->name(
                "equipo.index",
            );
            Route::get("equipo/nuevo", [
                EquipoController::class,
                "crear",
            ])->name("equipo.crear");
            Route::get("equipo/{usuario}/editar", [
                EquipoController::class,
                "editar",
            ])->name("equipo.editar");
            Route::post("equipo", [EquipoController::class, "guardar"])
                ->name("equipo.guardar")
                ->middleware("throttle:guardar");
            Route::put("equipo/{usuario}", [
                EquipoController::class,
                "actualizar",
            ])
                ->name("equipo.actualizar")
                ->middleware("throttle:guardar");
            Route::get(
                "documentos/editor",
                fn() => view("documentos.editor"),
            )->name("documentos.index");

            // ── Fotos carné y pasaporte (quitar fondo + hoja para imprimir)
            Route::get("fotos", [FotoController::class, "index"])->name(
                "fotos.index",
            );
            Route::post("fotos/procesar", [FotoController::class, "procesar"])
                ->name("fotos.procesar")
                ->middleware("throttle:fondo");
            Route::post("fotos/hoja", [FotoController::class, "hoja"])
                ->name("fotos.hoja")
                ->middleware("throttle:pdf");
            Route::post("fotos/cv/{cliente}", [
                FotoController::class,
                "usarEnCv",
            ])
                ->name("fotos.cv")
                ->middleware("throttle:guardar");

            // ── Autoservicio por QR (pantalla del Cyber)
            Route::get("autoservicio", [
                AutoservicioController::class,
                "panel",
            ])->name("autoservicio.panel");
            Route::get("autoservicio/lista", [
                AutoservicioController::class,
                "lista",
            ])->name("autoservicio.lista");
            Route::post("autoservicio/alternar", [
                AutoservicioController::class,
                "alternar",
            ])->name("autoservicio.alternar");
            Route::get("autoservicio/afiche", [
                AutoservicioController::class,
                "afiche",
            ])
                ->name("autoservicio.afiche")
                ->middleware("throttle:pdf");
            Route::post("autoservicio/{solicitud}/descartar", [
                AutoservicioController::class,
                "descartar",
            ])->name("autoservicio.descartar");

            // ── Punto de venta (cada Cyber con su inventario, caja y ventas)
            Route::prefix("pos")
                ->name("pos.")
                ->group(function () {
                    $P = "App\\Http\\Controllers\\Pos\\";
                    Route::get("/", [$P . "VenderController", "index"])->name(
                        "vender",
                    );
                    Route::post("ventas", [$P . "VenderController", "guardar"])
                        ->name("ventas.guardar")
                        ->middleware("throttle:guardar");
                    Route::post("caja/abrir", [
                        $P . "CajaController",
                        "abrir",
                    ])->name("caja.abrir");
                    Route::get("caja", [$P . "CajaController", "actual"])->name(
                        "caja",
                    );
                    Route::post("caja/movimiento", [
                        $P . "CajaController",
                        "movimiento",
                    ])->name("caja.movimiento");
                    Route::post("caja/cerrar", [
                        $P . "CajaController",
                        "cerrar",
                    ])->name("caja.cerrar");
                    Route::get("cajas", [
                        $P . "CajaController",
                        "historial",
                    ])->name("cajas");
                    Route::get("cajas/{caja}", [
                        $P . "CajaController",
                        "ver",
                    ])->name("cajas.ver");
                    Route::get("ventas", [
                        $P . "VentaController",
                        "index",
                    ])->name("ventas");
                    Route::get("ventas/{venta}", [
                        $P . "VentaController",
                        "show",
                    ])->name("ventas.show");
                    Route::get("ventas/{venta}/tiquete", [
                        $P . "VentaController",
                        "tiquete",
                    ])->name("ventas.tiquete");
                    Route::get("ventas/{venta}/imagen", [
                        $P . "VentaController",
                        "imagen",
                    ])
                        ->name("ventas.imagen")
                        ->middleware("throttle:pdf");
                    Route::post("ventas/{venta}/correo", [
                        $P . "VentaController",
                        "correo",
                    ])
                        ->name("ventas.correo")
                        ->middleware("throttle:cv-correo");
                    Route::post("ventas/{venta}/anular", [
                        $P . "VentaController",
                        "anular",
                    ])->name("ventas.anular");
                    Route::get("productos", [
                        $P . "ProductoController",
                        "index",
                    ])->name("productos");
                    Route::get("productos/nuevo", [
                        $P . "ProductoController",
                        "crear",
                    ])->name("productos.crear");
                    Route::post("productos", [
                        $P . "ProductoController",
                        "guardar",
                    ])->name("productos.guardar");
                    Route::post("productos/sugeridos", [
                        $P . "ProductoController",
                        "sugeridos",
                    ])->name("productos.sugeridos");
                    Route::post("categorias", [
                        $P . "ProductoController",
                        "categoria",
                    ])->name("categorias.guardar");
                    Route::delete("categorias/{categoria}", [
                        $P . "ProductoController",
                        "categoriaBorrar",
                    ])->name("categorias.borrar");
                    Route::get("productos/{producto}/foto", [
                        $P . "ProductoController",
                        "foto",
                    ])->name("productos.foto");
                    Route::get("productos/{producto}", [
                        $P . "ProductoController",
                        "editar",
                    ])->name("productos.editar");
                    Route::put("productos/{producto}", [
                        $P . "ProductoController",
                        "actualizar",
                    ])->name("productos.actualizar");
                    Route::delete("productos/{producto}", [
                        $P . "ProductoController",
                        "borrar",
                    ])->name("productos.borrar");
                    Route::post("productos/{producto}/ajuste", [
                        $P . "ProductoController",
                        "ajuste",
                    ])->name("productos.ajuste");
                    Route::get("productos/{producto}/kardex", [
                        $P . "ProductoController",
                        "kardex",
                    ])->name("productos.kardex");
                    Route::get("proveedores", [
                        $P . "ProveedorController",
                        "index",
                    ])->name("proveedores");
                    Route::post("proveedores", [
                        $P . "ProveedorController",
                        "guardar",
                    ])->name("proveedores.guardar");
                    Route::delete("proveedores/{proveedor}", [
                        $P . "ProveedorController",
                        "borrar",
                    ])->name("proveedores.borrar");
                    Route::get("compras", [
                        $P . "CompraController",
                        "index",
                    ])->name("compras");
                    Route::get("compras/nueva", [
                        $P . "CompraController",
                        "crear",
                    ])->name("compras.crear");
                    Route::post("compras", [
                        $P . "CompraController",
                        "guardar",
                    ])->name("compras.guardar");
                    Route::get("compras/{compra}", [
                        $P . "CompraController",
                        "ver",
                    ])->name("compras.ver");
                    Route::post("compras/{compra}/anular", [
                        $P . "CompraController",
                        "anular",
                    ])->name("compras.anular");
                    Route::get("reportes", [
                        $P . "ReporteController",
                        "index",
                    ])->name("reportes");
                    Route::get("reportes/exportar", [$P . "ReporteController", "exportar"])->name("reportes.exportar")->middleware("throttle:10,1");
                    Route::get("reportes/pdf", [$P . "ReporteController", "pdf"])->name("reportes.pdf")->middleware("throttle:10,1");
                    Route::get("reportes/excel", [
                        $P . "ReporteController",
                        "excel",
                    ])->name("reportes.excel");
                    Route::get("reportes/csv", [
                        $P . "ReporteController",
                        "csv",
                    ])->name("reportes.csv");
                    Route::get("configuracion", [
                        $P . "ConfigController",
                        "editar",
                    ])->name("config");
                    Route::put("configuracion", [
                        $P . "ConfigController",
                        "actualizar",
                    ])->name("config.actualizar");
                });
        });

        // ── Solo usuarios de Cyber
        Route::middleware("rol:cyber")->group(function () {
            Route::get("mi-marca", [MarcaController::class, "editar"])->name(
                "marca.edit",
            );
            Route::put("mi-marca", [
                MarcaController::class,
                "actualizar",
            ])->name("marca.update");
        });

        // ── Solo administrador general
        Route::middleware("rol:admin")
            ->prefix("admin")
            ->name("admin.")
            ->group(function () {
                Route::get("supervision/mensajes", [
                    \App\Http\Controllers\ChatSupervisionController::class,
                    "index",
                ])->name("chat.supervision");
                Route::get("supervision/mensajes/{chat}", [
                    \App\Http\Controllers\ChatSupervisionController::class,
                    "ver",
                ])->name("chat.supervision.ver");
                Route::get("supervision/archivo/{mensaje}", [
                    \App\Http\Controllers\ChatSupervisionController::class,
                    "archivo",
                ])->name("chat.supervision.archivo");
                Route::get("mensajes/avisos", [
                    ChatAvisoController::class,
                    "index",
                ])->name("chat.avisos");
                Route::post("mensajes/avisos", [
                    ChatAvisoController::class,
                    "enviar",
                ])
                    ->name("chat.avisos.enviar")
                    ->middleware("throttle:6,1");
                Route::get("cybers", [CyberController::class, "index"])->name(
                    "cybers.index",
                );
                Route::get("cybers/nuevo", [
                    CyberController::class,
                    "create",
                ])->name("cybers.create");
                Route::post("cybers", [CyberController::class, "store"])->name(
                    "cybers.store",
                );
                Route::get("cybers/{cyber}/editar", [
                    CyberController::class,
                    "edit",
                ])->name("cybers.edit");
                Route::put("cybers/{cyber}", [
                    CyberController::class,
                    "update",
                ])->name("cybers.update");
                Route::post("cybers/{cyber}/toggle", [
                    CyberController::class,
                    "toggle",
                ])->name("cybers.toggle");
                Route::delete("cybers/{cyber}", [
                    CyberController::class,
                    "destroy",
                ])->name("cybers.destroy");

                Route::get("usuarios", [
                    UsuarioController::class,
                    "index",
                ])->name("usuarios.index");
                Route::get("usuarios/nuevo", [
                    UsuarioController::class,
                    "create",
                ])->name("usuarios.create");
                Route::post("usuarios", [
                    UsuarioController::class,
                    "store",
                ])->name("usuarios.store");
                Route::get("usuarios/{usuario}/editar", [
                    UsuarioController::class,
                    "edit",
                ])->name("usuarios.edit");
                Route::put("usuarios/{usuario}", [
                    UsuarioController::class,
                    "update",
                ])->name("usuarios.update");
                Route::post("usuarios/{usuario}/toggle", [
                    UsuarioController::class,
                    "toggle",
                ])->name("usuarios.toggle");
                Route::delete("usuarios/{usuario}", [
                    UsuarioController::class,
                    "destroy",
                ])->name("usuarios.destroy");

                Route::get("configuracion", [
                    ConfiguracionController::class,
                    "editar",
                ])->name("configuracion.edit");
                Route::put("configuracion", [
                    ConfiguracionController::class,
                    "actualizar",
                ])->name("configuracion.update");
                Route::post("configuracion/restablecer-cv", [
                    ConfiguracionController::class,
                    "restablecerCv",
                ])->name("configuracion.restablecer");

                Route::get("auditoria", [
                    ActividadController::class,
                    "index",
                ])->name("actividad");
                Route::post("usuarios/{usuario}/verificar", [
                    UsuarioController::class,
                    "verificar",
                ])->name("usuarios.verificar");

                Route::get("correo", [CorreoController::class, "editar"])->name(
                    "correo.edit",
                );
                Route::put("correo", [
                    CorreoController::class,
                    "actualizar",
                ])->name("correo.update");
                Route::post("correo/probar", [
                    CorreoController::class,
                    "probar",
                ])
                    ->name("correo.probar")
                    ->middleware("throttle:sensible");

                Route::get("recaptcha", [
                    RecaptchaController::class,
                    "editar",
                ])->name("recaptcha.edit");
                Route::put("recaptcha", [
                    RecaptchaController::class,
                    "actualizar",
                ])->name("recaptcha.update");

                Route::get("marca", [
                    MarcaSistemaController::class,
                    "editar",
                ])->name("marca.edit");
                Route::put("marca", [
                    MarcaSistemaController::class,
                    "actualizar",
                ])->name("marca.update");
                Route::get("legal", [
                    LegalAdminController::class,
                    "editar",
                ])->name("legal.edit");
                Route::put("legal", [
                    LegalAdminController::class,
                    "actualizar",
                ])->name("legal.update");
                Route::get("solicitudes", [
                    SolicitudController::class,
                    "index",
                ])->name("solicitudes.index");
                Route::post("solicitudes/{solicitud}/aprobar", [
                    SolicitudController::class,
                    "aprobar",
                ])->name("solicitudes.aprobar");
                Route::post("solicitudes/{solicitud}/rechazar", [
                    SolicitudController::class,
                    "rechazar",
                ])->name("solicitudes.rechazar");

                Route::get("lista-privada", [
                    BloqueoController::class,
                    "index",
                ])->name("bloqueos.index");
                Route::post("lista-privada", [
                    BloqueoController::class,
                    "store",
                ])->name("bloqueos.store");
                Route::delete("lista-privada/{bloqueo}", [
                    BloqueoController::class,
                    "destroy",
                ])->name("bloqueos.destroy");

                Route::get("avisos", [AvisoController::class, "index"])->name(
                    "avisos.index",
                );
                Route::post("avisos", [AvisoController::class, "enviar"])
                    ->name("avisos.enviar")
                    ->middleware("throttle:sensible");

                Route::get('municipios-nicaragua/nuevo',[\App\Http\Controllers\MunicipioNicaraguaController::class,'crear'])->name('municipios-ni.create');
                Route::post('municipios-nicaragua',[\App\Http\Controllers\MunicipioNicaraguaController::class,'guardar'])->name('municipios-ni.store')->middleware('throttle:30,1');
                Route::get('municipios-nicaragua/{municipio}',[\App\Http\Controllers\MunicipioNicaraguaController::class,'editar'])->name('municipios-ni.edit');
                Route::put('municipios-nicaragua/{municipio}',[\App\Http\Controllers\MunicipioNicaraguaController::class,'actualizar'])->name('municipios-ni.update')->middleware('throttle:30,1');
                Route::get("paises", [PaisController::class, "index"])->name(
                    "paises.index",
                );
                Route::get("paises/nuevo", [
                    PaisController::class,
                    "crear",
                ])->name("paises.create");
                Route::post("paises", [PaisController::class, "guardar"])->name(
                    "paises.store",
                );
                Route::get("paises/{codigo}/escudo", [
                    PaisController::class,
                    "escudo",
                ])->name("paises.escudo");
                Route::get("paises/{codigo}", [
                    PaisController::class,
                    "editar",
                ])->name("paises.edit");
                Route::put("paises/{codigo}", [
                    PaisController::class,
                    "actualizar",
                ])->name("paises.update");
                Route::delete("paises/{codigo}", [
                    PaisController::class,
                    "borrar",
                ])->name("paises.destroy");

                Route::get("errores", [ErrorController::class, "index"])->name(
                    "errores.index",
                );
                Route::post("errores/limpiar", [
                    ErrorController::class,
                    "limpiar",
                ])->name("errores.limpiar");
            });
    },
);
