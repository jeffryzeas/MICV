<?php
namespace App\Http\Controllers;
use App\Models\Cliente;
use App\Services\Desktop\{LicenseSigner,FullClientSync};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DesktopDossierController extends Controller {
 public function index(Request $request){
  $data=$request->validate(['after'=>'nullable|integer|min:0']);$after=(int)($data['after']??0);
  $device=$request->attributes->get('desktop_device');$signer=app(LicenseSigner::class);
  abort_unless(in_array('clientes.ver',$signer->scopes($device),true),403);
  $payload=DB::transaction(function()use($device,$after){
   $query=Cliente::query()->visiblesPara($device->user);
   if($device->user->esAdmin()&&$device->negocio())$query->whereHas('cybers',fn($q)=>$q->where('cybers.id',$device->negocio()->id));
   $clients=$query->where('id','>',$after)->orderBy('id')->limit(25)->get();
   $rows=$clients->map(function($client){$full=FullClientSync::row($client);$revision=$full['revision'];unset($full['revision']);return ['id'=>$client->id,'revision'=>$revision,'data'=>$full];})->all();
   return ['format'=>'micv-dossiers-v1','issuer'=>rtrim(config('desktop.server_url'),'/'),'device'=>$device->uuid,
    'user_id'=>$device->user_id,'tenant_id'=>$device->negocio()?->id??0,'role'=>$device->user->rol,'issued_at'=>now()->timestamp,
    'after'=>$after,'next'=>count($rows)===25?$clients->last()->id:null,'rows'=>$rows];
  });
  $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);abort_if(strlen($json)>32*1024*1024,413);
  return response()->json(['payload'=>base64_encode($json),'signature'=>base64_encode(sodium_crypto_sign_detached($json,$signer->secret()))])->header('Cache-Control','private, no-store');
 }
}
