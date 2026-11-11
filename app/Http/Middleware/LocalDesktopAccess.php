<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Desktop\LocalBusinessJournal;
use Illuminate\Support\Facades\{DB, Schema};
use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalDesktopAccess
{
    public function handle(Request $request, Closure $next)
    {
        $secret = getenv('MICV_LOCAL_TOKEN');
        if (!$secret) {
            return $next($request);
        }
        $validUntil = defined('MICV_ROUTER_VALID_UNTIL') ? MICV_ROUTER_VALID_UNTIL : (int) getenv('MICV_LOCAL_VALID_UNTIL');
        abort_unless(strlen($secret) >= 32
            && $request->ip() === '127.0.0.1'
            && hash_equals($secret, $request->header('X-MiCV-Local', ''))
            && $validUntil > time(), 403);

        $user = User::find((int) getenv('MICV_LOCAL_USER'));
        abort_unless($user && $user->activo && $user->rol === getenv('MICV_LOCAL_ROLE'), 403);
        $edit = ($request->isMethod('PUT') && $request->routeIs('clientes.update', 'mi-cv.actualizar'))
            || ($request->isMethod('POST') && $request->routeIs('fotos.cv', 'tarjetas.guardar'));
        $localAction = $request->isMethod('POST') && $request->routeIs(
            'cartas.previsualizar', 'cartas.generar', 'tarjetas.vista', 'tarjetas.exportar', 'fotos.procesar', 'fotos.hoja',
            'chat.sincronizar', 'chat.leer', 'pos.pendientes.guardar', 'pos.pendientes.descartar',
        );
        $businessEdit = in_array($request->method(), ['POST','PUT','DELETE'], true) && $request->routeIs(...LocalBusinessJournal::ROUTES);
        abort_unless($edit || $businessEdit || $localAction || $request->isMethod('GET') || $request->isMethod('HEAD'), 403,
            'Esta operación requiere la sincronización del módulo local.');
        abort_if($request->routeIs('login*', 'registro*', 'instalar*', 'desktop.*', 'admin.desktop.*'), 403);

        Auth::setUser($user);
        $request->setUserResolver(fn() => $user);
        $permissions = json_decode(getenv('MICV_LOCAL_PERMISSIONS') ?: '[]', true);
        abort_unless(is_array($permissions), 403);
        $request->attributes->set('local_permissions', $permissions);
        $required = Permisos::deRuta($request->route()?->getName());
        if ($user->esCyber() && $required) {
            abort_unless(is_array($permissions) && array_intersect($required, $permissions), 403);
        }
        if ($businessEdit) {
            abort_unless(Schema::hasTable('local_business_edits'),409,'Prepare una copia actualizada para registrar operaciones.');
        }
        $response = $businessEdit ? DB::transaction(fn() => $next($request)) : ($edit ? app(\App\Services\Desktop\LocalClientJournal::class)->save($request, $next) : $next($request));
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
