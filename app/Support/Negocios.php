<?php
namespace App\Support;
class Negocios
{
    public const TIPOS = ['cyber'=>'Cyber y servicios digitales','comercio'=>'Comercio general','tienda'=>'Tienda / minorista','servicios'=>'Servicios profesionales','contabilidad'=>'Despacho contable','empresa'=>'Empresa','independiente'=>'Profesional independiente','otro'=>'Otro negocio'];
    public const MODULOS = ['clientes'=>'Clientes','cv'=>'Currículums','pos'=>'Punto de venta','contabilidad'=>'Ingresos y gastos','chat'=>'Centro de mensajes','correos'=>'Correos de clientes','documentos'=>'Documentos e impresión','fotos'=>'Fotos y fondo','tarjetas'=>'Tarjetas','cartas'=>'Cartas','autoservicio'=>'Autoservicio'];
    public const PERFILES = ['integral'=>'Todos los módulos','pos'=>'Solo punto de venta','contabilidad'=>'Solo ingresos y gastos','pos_contabilidad'=>'POS y contabilidad','personalizado'=>'Elegir módulos'];
    public const ZONAS = ['NI'=>'America/Managua','CR'=>'America/Costa_Rica','HN'=>'America/Tegucigalpa','SV'=>'America/El_Salvador','GT'=>'America/Guatemala','MX'=>'America/Mexico_City','CO'=>'America/Bogota','PA'=>'America/Panama','DO'=>'America/Santo_Domingo','CU'=>'America/Havana','VE'=>'America/Caracas','EC'=>'America/Guayaquil','PE'=>'America/Lima','BO'=>'America/La_Paz','CL'=>'America/Santiago','AR'=>'America/Argentina/Buenos_Aires','UY'=>'America/Montevideo','PY'=>'America/Asuncion','ES'=>'Europe/Madrid','US'=>'America/New_York'];
    public static function modulos(string $perfil, array $elegidos = []): array
    {
        $m = match($perfil) { 'pos'=>['pos','clientes'], 'contabilidad'=>['contabilidad'], 'pos_contabilidad'=>['pos','clientes','contabilidad'], 'personalizado'=>$elegidos, default=>array_keys(self::MODULOS) };
        if (array_intersect($m, ['cv','tarjetas','cartas','correos','autoservicio'])) $m[]='clientes';
        return array_values(array_unique(array_intersect(array_keys(self::MODULOS),$m)));
    }
    public static function moduloPermiso(string $permiso): ?string
    {
        $m = explode('.', $permiso)[0];
        return isset(self::MODULOS[$m]) ? $m : null;
    }
}
