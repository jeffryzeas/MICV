<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\DocumentoBloqueado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Lista privada: documentos que ningún Cyber puede registrar, consultar ni ver. */
class BloqueoController extends Controller
{
    public function index(Request $request)
    {
        $q = DocumentoBloqueado::normalizar($request->query('q'));
        $lista = DocumentoBloqueado::with('autor')->when($q !== '', fn ($w) => $w->where('numero', 'like', "%$q%"))
            ->latest()->paginate(30)->withQueryString();
        return view('admin.bloqueos', compact('lista'));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'numero' => ['required', 'string', 'max:40'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [], ['numero' => 'número de documento']);
        $numero = DocumentoBloqueado::normalizar($d['numero']);
        if (strlen($numero) < 4) {
            return back()->withErrors(['numero' => 'Escriba el número completo del documento.'])->withInput();
        }
        DocumentoBloqueado::updateOrCreate(['numero' => $numero], ['motivo' => $d['motivo'] ?? null, 'creado_por' => $request->user()->id]);

        $extra = '';
        if ($request->boolean('desactivar_cuenta')) {
            $ids = Cliente::whereNotNull('user_id')->where(DB::raw("UPPER(REPLACE(numero_documento, '-', ''))"), $numero)->pluck('user_id');
            $n = User::whereIn('id', $ids)->where('rol', 'cliente')->update(['activo' => false]);
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            $extra = $n ? " Se desactivó $n cuenta(s) con ese documento." : '';
        }
        Actividad::registrar('lista_privada_agregar', '••••' . substr($numero, -4));
        return back()->with('mensaje', 'Documento agregado a la lista privada. Ningún Cyber podrá registrarlo ni verlo.' . $extra);
    }

    public function destroy(DocumentoBloqueado $bloqueo)
    {
        $bloqueo->delete();
        Actividad::registrar('lista_privada_quitar', '••••' . substr($bloqueo->numero, -4));
        return back()->with('mensaje', 'Documento quitado de la lista privada.');
    }
}
