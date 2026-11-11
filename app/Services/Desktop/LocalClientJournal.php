<?php

namespace App\Services\Desktop;

use App\Models\{Cliente, Tarjeta};
use Closure;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class LocalClientJournal
{
    public static function initialize(array $tables): void
    {
        Schema::create('local_cv_edits', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->primary();
            $table->string('revision',64);
            $table->text('operation')->nullable();
        });
        $children = [];
        foreach (FullClientSync::RELATIONS as $name) {
            foreach ($tables['cliente_'.$name] as $row) {
                $children[$row['cliente_id']][$name][] = $row;
            }
        }
        foreach ($tables['clientes'] as $client) {
            $state = ['client'=>$client];
            foreach (FullClientSync::RELATIONS as $name) {
                $rows = $children[$client['id']][$name] ?? [];
                usort($rows, fn($a,$b) => $a['id'] <=> $b['id']);
                $state[$name] = $rows;
            }
            DB::table('local_cv_edits')->insert(['client_id'=>$client['id'],'revision'=>FullClientSync::fingerprint($state)]);
        }
    }

    public static function payload(Request $request): array
    {
        $photo = $request->file('foto');
        if ($photo !== null) {
            abort_unless($photo instanceof UploadedFile && $photo->isValid(),422,'La foto no es válida.');
            abort_if($photo->getSize() > 600 * 1024,413,'Use una foto de hasta 600 KB para editar sin conexión.');
        }
        $data = ['client'=>$request->except('_token','_method','foto','autoservicio_id')];
        if ($photo) {
            $data['photo'] = ['content'=>base64_encode($photo->getContent())];
        }
        abort_if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>890000,413,'La ficha supera el tamaño admitido.');
        return $data;
    }

    public function save(Request $request, Closure $next)
    {
        $user = $request->user();
        $value = $request->route('cliente');
        $client = $request->routeIs('mi-cv.actualizar')
            ? $user->cliente
            : Cliente::find($value instanceof Cliente ? $value->id : $value);
        abort_unless($client && $client->esVisiblePara($user),403);
        if ($request->routeIs('fotos.cv')) {
            $photo = \App\Support\Archivos::desdeDataUri($request->input('imagen'), 600 * 1024);
            abort_unless($photo, 422, 'Use una imagen válida de hasta 600 KB para guardar sin conexión.');
            $data = ['photo_only' => true, 'photo' => ['content' => base64_encode($photo[0])]];
        } elseif ($request->routeIs('tarjetas.guardar')) {
            $logo = $request->file('logo');
            abort_if($logo && (!$logo instanceof UploadedFile || !$logo->isValid() || $logo->getSize() > 600 * 1024),
                422, 'Use un logo válido de hasta 600 KB para guardar sin conexión.');
            $data = [
                'card' => $request->only(array_merge(Tarjeta::CAMPOS, ['diseno','color','color_propio','imagen','qr','quitar_logo'])),
                'card_revision' => FullClientSync::cardRevision($client),
            ];
            if ($logo) {
                $data['photo'] = ['content' => base64_encode($logo->getContent())];
            }
        } else {
            $data = self::payload($request);
        }
        abort_unless(Schema::hasTable('local_cv_edits'),409,'Prepare una copia local actualizada para editar.');
        return DB::transaction(function () use ($request,$next,$client,$data) {
            $entry = DB::table('local_cv_edits')->where('client_id',$client->id)->first();
            abort_unless($entry && !$entry->operation,409,'Sincronice esta ficha antes de editarla de nuevo.');
            $operation = ['uuid'=>(string)Str::uuid(),'type'=>'client.full.update','occurred_at'=>now()->utc()->toIso8601String(),
                'data'=>$data+['id'=>LocalBusinessJournal::reference($request,'client.full.create',$client->id),
                'local_client_id'=>$client->id,'revision'=>$entry->revision]];
            $encoded = json_encode($operation, JSON_THROW_ON_ERROR);
            abort_if(strlen($encoded)>900000,413,'La edición supera el tamaño admitido.');
            $revision = array_key_exists('card', $data)
                ? fn () => FullClientSync::cardRevision($client)
                : fn () => FullClientSync::revision($client);
            $before = $revision();
            $response = $next($request);
            if ($response->getStatusCode()<400 && $before !== $revision()) {
                DB::table('local_cv_edits')->where('client_id',$client->id)->update(['operation'=>Crypt::encryptString($encoded)]);
            }
            return $response;
        });
    }
}
