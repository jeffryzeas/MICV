<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\Permisos;
class PermisosModulo
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && !$u->esAdmin() && !$u->esCliente()) {
            $requeridos = Permisos::deRuta($request->route()?->getName());
            abort_if($requeridos && !collect($requeridos)->contains(fn ($p) => $u->tienePermiso($p)), 403, 'Su cuenta no tiene permiso para esta acción.');
        }
        return $next($request);
    }
}
