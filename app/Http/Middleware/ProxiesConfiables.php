<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/** Lee TRUSTED_PROXIES desde la configuración (funciona aunque se use config:cache). */
class ProxiesConfiables extends TrustProxies
{
    protected function proxies()
    {
        $p = config('app.trusted_proxies');
        if (blank($p)) {
            return parent::proxies();
        }
        return $p === '*' ? '*' : array_map('trim', explode(',', $p));
    }
}
