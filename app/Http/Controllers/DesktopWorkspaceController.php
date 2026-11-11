<?php
namespace App\Http\Controllers;
use App\Services\Desktop\{LicenseSigner,WorkspaceSnapshot,WorkspaceFiles};
use Illuminate\Http\Request;
class DesktopWorkspaceController extends Controller {
 public function index(Request $r){
  $json=json_encode((new WorkspaceSnapshot($r->attributes->get('desktop_device')))->build(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  abort_if(strlen($json)>32*1024*1024,413,'La copia supera el tamaño permitido.');
  return response()->json(['payload'=>base64_encode($json),'signature'=>base64_encode(sodium_crypto_sign_detached($json,app(LicenseSigner::class)->secret()))])->header('Cache-Control','private, no-store');
 }
 public function file(Request $r){
  $d=$r->validate(['table'=>'required|string','key'=>'required','field'=>'required|string','sha256'=>'required|regex:/^[a-f0-9]{64}$/']);
  abort_unless(in_array($d['field'],WorkspaceFiles::FIELDS[$d['table']]??[],true),404);
  $snapshot=new WorkspaceSnapshot($r->attributes->get('desktop_device'));$queries=$snapshot->queries();
  abort_unless(isset($queries[$d['table']]),404);
  $key=$d['table']==='paises'?'codigo':'id';
  abort_unless(is_string($d['key'])||is_int($d['key']),422);
  if($key==='id')abort_unless(ctype_digit((string)$d['key'])&&(int)$d['key']>0,422);
  $row=$queries[$d['table']]->where($key,$d['key'])->first();abort_unless($row,404);
  $data=$snapshot->project($d['table'],(array)$row);abort_unless(!empty($data[$d['field']]),404);
  $path=WorkspaceFiles::path($data[$d['field']]);abort_unless($path,404);
  abort_unless(hash_equals($d['sha256'],hash_file('sha256',$path)),409,'El archivo cambió. Actualice la descarga.');
  return response()->download($path,basename($path),['Cache-Control'=>'private, no-store','Content-Type'=>'application/octet-stream','X-Content-Type-Options'=>'nosniff']);
 }
}
