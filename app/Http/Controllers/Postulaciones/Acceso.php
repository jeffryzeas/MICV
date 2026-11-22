<?php
namespace App\Http\Controllers\Postulaciones;
use App\Models\{Cliente, User};
use Illuminate\Http\Request;

trait Acceso
{
    private function actor(Request $r): User
    {
        $u = $r->user();
        abort_unless($u && ($u->esAdmin() || $u->esCliente() || $u->tienePermiso('cv')), 403);
        return $u;
    }
    private function candidato(Request $r, int $id): Cliente
    {
        $u = $this->actor($r);
        $c = Cliente::findOrFail($id);
        abort_unless($c->esVisiblePara($u) && $c->activo, 403, 'No tiene acceso a este CV.');
        return $c;
    }
    private function privado($response)
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
