<?php
namespace App\Http\Controllers\Postulaciones;

use App\Http\Controllers\Controller;
use App\Models\{GmailCuenta, User, Actividad};
use App\Services\GmailPostulaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GmailController extends Controller
{
    use Acceso;
    private function propietario(Request $r, ?User $usuario): User
    {
        $actor = $this->actor($r);
        $usuario = $usuario?->exists ? $usuario : $actor;
        abort_unless($actor->esAdmin() || $actor->is($usuario), 403);
        return $usuario;
    }
    public function edit(Request $r, ?User $usuario = null)
    {
        $propietario = $this->propietario($r, $usuario);
        return $this->privado(response()->view('postulaciones.gmail', [
            'propietario'=>$propietario, 'cuenta'=>GmailCuenta::where('user_id', $propietario->id)->first(),
            'usuarios'=>$r->user()->esAdmin() ? User::orderBy('name')->get(['id','name','usuario']) : collect(),
        ]));
    }
    public function update(Request $r, User $usuario)
    {
        $this->propietario($r, $usuario);
        $clave = preg_replace('/\s+/u', '', (string)$r->input('gmail_clave', ''));
        $r->merge(['gmail_clave'=>$clave]);
        $data = $r->validate([
            'correo'=>['required','email:rfc','max:150','not_regex:/[\r\n]/'],
            'nombre'=>['required','string','max:150','not_regex:/[\r\n]/'],
            'gmail_clave'=>['nullable','regex:/^[a-zA-Z]{16}$/'],
            'activo'=>['nullable','boolean'],
        ], ['gmail_clave.regex'=>'La contraseña de aplicación de Google tiene 16 letras. Puede pegarla con espacios.']);
        $cuenta = GmailCuenta::firstOrNew(['user_id'=>$usuario->id]);
        $correo = mb_strtolower($data['correo']);
        if ((!$cuenta->exists || $cuenta->correo !== $correo || !$cuenta->getRawOriginal('clave')) && $clave === '') {
            throw ValidationException::withMessages(['gmail_clave'=>'Introduzca una contraseña de aplicación para esta dirección de Gmail.']);
        }
        $cambia = !$cuenta->exists || $cuenta->correo !== $correo || $clave !== '';
        $cuenta->fill(['correo'=>$correo,'nombre'=>$data['nombre'],'activo'=>$r->boolean('activo')]);
        if ($cambia) { $cuenta->verificada_en=null; $cuenta->revision=(string)Str::uuid(); }
        if ($clave !== '') $cuenta->clave=$clave;
        $cuenta->save();
        Actividad::registrar('postulacion_gmail_configurar', 'Cuenta de usuario #'.$usuario->id);
        return redirect()->route('postulaciones.gmail', $usuario)->with('mensaje','Cuenta guardada. Compruebe la conexión antes de enviar.');
    }
    public function comprobar(Request $r, User $usuario, GmailPostulaciones $gmail)
    {
        $this->propietario($r, $usuario);
        $cuenta=GmailCuenta::where('user_id',$usuario->id)->firstOrFail();
        abort_unless($cuenta->activo && $cuenta->getRawOriginal('clave'),422,'Active la cuenta y guarde su contraseña de aplicación.');
        try { $gmail->comprobar($cuenta); }
        catch (\Throwable) {
            GmailCuenta::whereKey($cuenta->id)->where('revision',$cuenta->revision)->update(['verificada_en'=>null]);
            return back()->withErrors(['conexion'=>'No se pudo autenticar con Gmail. Revise la dirección y la contraseña de aplicación; compruebe también que el VPS permita conexiones salientes al puerto 587.']);
        }
        GmailCuenta::whereKey($cuenta->id)->where('revision',$cuenta->revision)->update(['verificada_en'=>now()]);
        return back()->with('mensaje','Conexión con Gmail comprobada. La prueba no envió ningún correo.');
    }
    public function disconnect(Request $r, User $usuario)
    {
        $this->propietario($r,$usuario);
        GmailCuenta::where('user_id',$usuario->id)->update(['clave'=>null,'activo'=>false,'verificada_en'=>null,'revision'=>(string)Str::uuid()]);
        Actividad::registrar('postulacion_gmail_desconectar', 'Cuenta de usuario #'.$usuario->id);
        return back()->with('mensaje','Gmail desconectado. Se eliminó la contraseña guardada y se impedirán los envíos pendientes de esta cuenta.');
    }
}
