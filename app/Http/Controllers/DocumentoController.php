<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\CedulaValidator;
use App\Services\ClienteGuardar;
use Illuminate\Http\Request;

/** Consulta en tiempo real del documento (misma respuesta que el Sistema JZ). */
class DocumentoController extends Controller
{
    public function consultar(Request $request)
    {
        $tipo = (string) $request->input('tipo_documento', 'cedula');
        $numero = \App\Support\Documentos::limpiar($tipo, $request->input('numero_documento', ''));

        if ($numero === '') {
            return response()->json(['ok' => true, 'vacio' => true]);
        }

        $numeroUi = $numero;
        if ($tipo === 'cedula' && strlen($numero) === 14) {
            $numeroUi = substr($numero, 0, 3) . '-' . substr($numero, 3, 6) . '-' . substr($numero, 9, 4) . substr($numero, 13, 1);
        }

        $r = [
            'ok' => true, 'numero_limpio' => $numero, 'numero_ui' => $numeroUi,
            'existe' => false, 'en_mi_cyber' => false, 'cliente' => null, 'cedula' => null,
        ];

        if ($tipo === 'cedula') {
            $v = CedulaValidator::validar($numero);
            $r['cedula'] = [
                'valida' => $v['valida'], 'errores' => $v['errores'],
                'municipio' => $v['municipio'], 'departamento' => $v['departamento'],
                'fecha_nacimiento' => $v['fecha_nacimiento'], 'edad' => $v['edad'],
            ];
        }

        if ($tipo !== 'cedula') {
            // Reglas de cada país (formato, dígito verificador, departamento, fecha…)
            $r['cr'] = \App\Support\Documentos::validar($tipo, $numero);
            $r['numero_ui'] = $r['cr']['formateado'];
        }

        $c = Cliente::where('tipo_documento', $tipo)->where('numero_documento', $numero)->first();
        if ($c) {
            $u = $request->user();
            $r['existe'] = true;
            $r['en_mi_cyber'] = $c->esVisiblePara($u);
            // Privacidad: un usuario registrado solo sabe que el número ya está en uso;
            // un Cyber solo ve el nombre de quien no es su cliente (para confirmar que es la misma persona)
            // y los datos de contacto únicamente de sus propios clientes.
            if (!$u->esCliente()) {
                $r['cliente'] = [
                    'id' => $r['en_mi_cyber'] ? $c->id : null,
                    'nombre_completo' => $c->nombreCompleto(),
                    'url' => $r['en_mi_cyber'] ? route('clientes.show', $c) : null,
                    'primer_nombre' => $c->primer_nombre, 'segundo_nombre' => $c->segundo_nombre,
                    'primer_apellido' => $c->primer_apellido, 'segundo_apellido' => $c->segundo_apellido,
                ] + ($r['en_mi_cyber'] ? ['correo' => $c->correo, 'telefono' => $c->telefono, 'direccion' => $c->direccion] : []);
            } elseif ($u->cliente && $u->cliente->id === $c->id) {
                $r['existe'] = false; // es su propio documento
            }
        }

        return response()->json($r);
    }
}
