<?php
namespace App\Support;

use Illuminate\Support\Facades\{DB, Schema};

/** Catálogo de prefijos de cédula. No usar códigos postales ni del censo. */
class MunicipiosNicaragua
{
    public const DEPARTAMENTOS = [
        "Boaco",
        "Carazo",
        "Chinandega",
        "Chontales",
        "Estelí",
        "Granada",
        "Jinotega",
        "León",
        "Madriz",
        "Managua",
        "Masaya",
        "Matagalpa",
        "Nueva Segovia",
        "Río San Juan",
        "Rivas",
        "Costa Caribe Norte",
        "Costa Caribe Sur",
    ];

    public static function base(): array
    {
        return json_decode(
            file_get_contents(
                dirname(__DIR__, 2) .
                    "/resources/catalogos/municipios-nicaragua.json",
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** La base de datos prevalece: cambios y desactivaciones se aplican de inmediato. */
    public static function todos(): array
    {
        if (
            Schema::getFacadeRoot() &&
            Schema::hasTable("municipios_nicaragua")
        ) {
            return DB::table("municipios_nicaragua")
                ->orderBy("codigo")
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        }
        return self::base();
    }

    public static function buscar(string $codigo): ?array
    {
        if (!preg_match('/^\d{3}$/D', $codigo)) {
            return null;
        }
        foreach (self::todos() as $row) {
            if ((string) $row["codigo"] === $codigo) {
                return $row;
            }
        }
        return null;
    }
}
