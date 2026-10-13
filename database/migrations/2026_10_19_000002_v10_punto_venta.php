<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v10: punto de venta por Cyber (inventario, proveedores, compras, caja, ventas y comprobantes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_config', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->unique()->constrained('cybers')->cascadeOnDelete();
            $t->string('pais', 2)->default('NI');
            $t->string('moneda', 3)->default('USD');
            $t->string('simbolo', 6)->default('$');
            $t->unsignedTinyInteger('decimales')->default(2);
            $t->string('locale', 10)->default('es');
            $t->string('impuesto_nombre', 30)->default('IVA');
            $t->decimal('impuesto_tasa', 5, 2)->default(0);
            $t->boolean('precios_con_impuesto')->default(true);
            $t->string('nombre_comercial', 120)->nullable();
            $t->string('razon_social', 150)->nullable();
            $t->string('id_fiscal_tipo', 40)->nullable();
            $t->string('id_fiscal', 40)->nullable();
            $t->string('telefono', 40)->nullable();
            $t->string('correo', 150)->nullable();
            $t->string('direccion', 200)->nullable();
            $t->string('pie_tiquete', 300)->nullable();
            $t->string('aviso_fiscal', 200)->nullable();
            $t->unsignedSmallInteger('ancho_tiquete')->default(80);
            $t->string('prefijo', 10)->default('');
            $t->unsignedInteger('siguiente_numero')->default(1);
            $t->json('metodos')->nullable();
            $t->timestamps();
        });

        Schema::create('pos_categorias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->string('nombre', 60);
            $t->string('color', 7)->default('#0B3C74');
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        Schema::create('pos_proveedores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->string('nombre', 150);
            $t->string('identificacion', 40)->nullable();
            $t->string('contacto', 120)->nullable();
            $t->string('telefono', 40)->nullable();
            $t->string('correo', 150)->nullable();
            $t->string('direccion', 200)->nullable();
            $t->text('notas')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('pos_productos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->foreignId('categoria_id')->nullable()->constrained('pos_categorias')->nullOnDelete();
            $t->foreignId('proveedor_id')->nullable()->constrained('pos_proveedores')->nullOnDelete();
            $t->string('tipo', 10)->default('producto'); // producto | servicio
            $t->string('codigo', 60)->nullable();
            $t->string('nombre', 150);
            $t->decimal('precio', 14, 2)->default(0);
            $t->decimal('costo', 14, 2)->default(0);
            $t->decimal('impuesto_tasa', 5, 2)->nullable(); // null = la tasa general
            $t->boolean('exento')->default(false);
            $t->boolean('controla_stock')->default(false);
            $t->decimal('stock', 12, 2)->default(0);
            $t->decimal('stock_minimo', 12, 2)->default(0);
            $t->string('unidad', 15)->default('unid.');
            $t->boolean('favorito')->default(false);
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->unique(['cyber_id', 'codigo']);
            $t->index(['cyber_id', 'activo']);
        });

        Schema::create('pos_compras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->foreignId('proveedor_id')->nullable()->constrained('pos_proveedores')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('numero_factura', 60)->nullable();
            $t->date('fecha');
            $t->decimal('total', 14, 2)->default(0);
            $t->string('notas', 300)->nullable();
            $t->string('estado', 12)->default('registrada'); // registrada | anulada
            $t->timestamps();
        });

        Schema::create('pos_compra_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compra_id')->constrained('pos_compras')->cascadeOnDelete();
            $t->foreignId('producto_id')->nullable()->constrained('pos_productos')->nullOnDelete();
            $t->string('descripcion', 150);
            $t->decimal('cantidad', 12, 2);
            $t->decimal('costo', 14, 2);
            $t->decimal('subtotal', 14, 2);
        });

        Schema::create('pos_movimientos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->foreignId('producto_id')->constrained('pos_productos')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tipo', 20); // inicial | compra | venta | ajuste | anula_venta | anula_compra
            $t->decimal('cantidad', 12, 2);
            $t->decimal('stock_despues', 12, 2);
            $t->decimal('costo', 14, 2)->nullable();
            $t->string('referencia', 120)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['producto_id', 'created_at']);
        });

        Schema::create('pos_cajas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('abierta_en');
            $t->decimal('monto_inicial', 14, 2)->default(0);
            $t->timestamp('cerrada_en')->nullable();
            $t->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('efectivo_esperado', 14, 2)->nullable();
            $t->decimal('efectivo_contado', 14, 2)->nullable();
            $t->decimal('diferencia', 14, 2)->nullable();
            $t->json('conteo')->nullable();
            $t->string('notas_cierre', 300)->nullable();
            $t->string('estado', 10)->default('abierta'); // abierta | cerrada
            $t->timestamps();
            $t->index(['cyber_id', 'estado']);
        });

        Schema::create('pos_caja_movimientos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('caja_id')->constrained('pos_cajas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tipo', 10); // ingreso | retiro
            $t->decimal('monto', 14, 2);
            $t->string('motivo', 200);
            $t->timestamps();
        });

        Schema::create('pos_ventas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cyber_id')->constrained('cybers')->cascadeOnDelete();
            $t->foreignId('caja_id')->nullable()->constrained('pos_cajas')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->string('cliente_nombre', 150)->nullable();
            $t->unsignedInteger('consecutivo');
            $t->string('numero', 30);
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('descuento', 14, 2)->default(0);
            $t->decimal('impuesto', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('pagado', 14, 2)->default(0);
            $t->decimal('cambio', 14, 2)->default(0);
            $t->decimal('costo_total', 14, 2)->default(0);
            $t->string('estado', 10)->default('pagada'); // pagada | anulada
            $t->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('anulada_en')->nullable();
            $t->string('motivo_anulacion', 200)->nullable();
            $t->string('notas', 300)->nullable();
            $t->timestamps();
            $t->unique(['cyber_id', 'consecutivo']);
            $t->index(['cyber_id', 'created_at']);
        });

        Schema::create('pos_venta_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venta_id')->constrained('pos_ventas')->cascadeOnDelete();
            $t->foreignId('producto_id')->nullable()->constrained('pos_productos')->nullOnDelete();
            $t->string('descripcion', 150);
            $t->decimal('cantidad', 12, 2);
            $t->decimal('precio', 14, 2);
            $t->decimal('descuento', 14, 2)->default(0);
            $t->decimal('impuesto_tasa', 5, 2)->default(0);
            $t->decimal('impuesto', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->decimal('costo', 14, 2)->default(0);
        });

        Schema::create('pos_venta_pagos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venta_id')->constrained('pos_ventas')->cascadeOnDelete();
            $t->string('metodo', 20);
            $t->decimal('monto', 14, 2);
            $t->string('referencia', 60)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['pos_venta_pagos', 'pos_venta_items', 'pos_ventas', 'pos_caja_movimientos', 'pos_cajas', 'pos_movimientos',
                  'pos_compra_items', 'pos_compras', 'pos_productos', 'pos_proveedores', 'pos_categorias', 'pos_config'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
