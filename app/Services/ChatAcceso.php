<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;

class ChatAcceso
{
    public static function habilitado(User $u): bool
    {
        return $u->activo &&
            ($u->esAdmin() ||
                $u->esCliente() ||
                ($u->tienePermiso("chat") && $u->cyber?->activo));
    }

    public static function puede(User $a, User $b): bool
    {
        if (
            $a->id === $b->id ||
            !self::habilitado($a) ||
            !self::habilitado($b)
        ) {
            return false;
        }
        if ($a->esAdmin() || $b->esAdmin()) {
            return true;
        }
        if ($a->esCyber() && $b->esCyber()) {
            return (int) $a->cyber_id === (int) $b->cyber_id;
        }
        $staff = $a->esCyber() ? $a : ($b->esCyber() ? $b : null);
        $cliente = $a->esCliente() ? $a : ($b->esCliente() ? $b : null);

        return $staff &&
            $cliente &&
            Cliente::visiblesPara($staff)
                ->where("user_id", $cliente->id)
                ->exists();
    }

    public static function contactos(User $u)
    {
        $q = User::where("activo", true)->where("id", "!=", $u->id);
        if (!$u->esAdmin()) {
            $q->where(function ($q) use ($u) {
                $q->where("rol", "admin");
                if ($u->esCyber()) {
                    $q->orWhere(
                        fn($w) => $w
                            ->where("rol", "cyber")
                            ->where("cyber_id", $u->cyber_id),
                    )->orWhereIn(
                        "id",
                        Cliente::visiblesPara($u)
                            ->whereNotNull("user_id")
                            ->select("user_id"),
                    );
                } else {
                    $ids = $u->cliente?->cybers()->pluck("cybers.id") ?? [];
                    $q->orWhere(
                        fn($w) => $w
                            ->where("rol", "cyber")
                            ->whereIn("cyber_id", $ids),
                    );
                }
            });
        }

        return $q;
    }
}
