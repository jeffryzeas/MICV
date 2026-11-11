<?php

namespace App\Services\Desktop;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use Illuminate\Support\Str;

class LocalBusinessJournal
{
    public const ROUTES = [
        'clientes.store', 'autoservicio.descartar', 'pos.config.actualizar', 'marca.update', 'clientes.acceso.permisos', 'equipo.actualizar',
        'contabilidad.store', 'contabilidad.anular',
        'pos.caja.abrir', 'pos.caja.movimiento', 'pos.caja.cerrar',
        'pos.ventas.guardar', 'pos.ventas.anular',
        'pos.compras.guardar', 'pos.compras.anular', 'pos.productos.ajuste',
        'pos.productos.guardar', 'pos.productos.actualizar', 'pos.productos.borrar', 'pos.productos.sugeridos',
        'pos.categorias.guardar', 'pos.categorias.borrar', 'pos.proveedores.guardar', 'pos.proveedores.borrar',
    ];

    public static function initialize(): void
    {
        Schema::create('local_business_edits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type',40);
            $table->unsignedBigInteger('entity_id');
            $table->text('operation');
        });
    }

    public static function record(Request $request, int $tenant, string $type, int $id, array $data, ?string $uuid = null): void
    {
        if (!$request->attributes->has('local_permissions')) {
            return;
        }
        abort_unless($tenant > 0 && $tenant === (int) getenv('MICV_LOCAL_TENANT'),403,'Abra el espacio de este negocio para registrar operaciones.');
        $uuid ??= (string) Str::uuid();
        $existing = DB::table('local_business_edits')->where('uuid',$uuid)->first();
        if ($existing) {
            abort_unless($existing->type === $type && (int)$existing->entity_id === $id,409,'La referencia ya se usó para otra operación.');
            return;
        }
        $operation = ['uuid'=>$uuid,'type'=>$type,'occurred_at'=>now()->utc()->toIso8601String(),'data'=>$data];
        DB::table('local_business_edits')->insert([
            'uuid'=>$uuid,'type'=>$type,'entity_id'=>$id,
            'operation'=>Crypt::encryptString(json_encode($operation,JSON_THROW_ON_ERROR)),
        ]);
    }

    public static function productData(Request $request): array
    {
        if (!$request->attributes->has('local_permissions')) {
            return [];
        }
        $data = ['product'=>$request->except('_token','_method','foto')];
        foreach (['categoria_id'=>'category.create','proveedor_id'=>'supplier.create'] as $field=>$kind) {
            if ($request->filled($field)) {
                $data['product'][$field] = self::reference($request,$kind,$request->integer($field));
            }
        }
        if ($file = $request->file('foto')) {
            abort_unless($file instanceof \Illuminate\Http\UploadedFile && $file->isValid(),422,'La foto no es válida.');
            abort_if($file->getSize()>600*1024,413,'Use una foto de hasta 600 KB para editar sin conexión.');
            $data['photo'] = base64_encode($file->getContent());
        }
        abort_if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>900000,413,'El producto supera el tamaño admitido.');
        return $data;
    }

    public static function productReferences(Request $request, array $items): array
    {
        foreach ($items as &$item) {
            if (!empty($item['producto_id'])) {
                $item['producto_id'] = self::reference($request,'product.create',(int)$item['producto_id']);
            }
        }
        return $items;
    }

    public static function reference(Request $request, string $type, int $id): int|string
    {
        if (!$request->attributes->has('local_permissions')) {
            return $id;
        }
        $uuid = DB::table('local_business_edits')->where('type',$type)->where('entity_id',$id)->value('uuid');
        return $uuid ? 'local:'.$uuid : $id;
    }
}
