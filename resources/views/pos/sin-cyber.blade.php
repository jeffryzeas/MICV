<x-layout titulo="Punto de venta" activo="pos-vender">
    <div class="max-w-md mx-auto mt-10 bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-6 text-center">
        <div class="mx-auto size-14 rounded-2xl bg-blue-50 text-azul-800 flex items-center justify-center"><x-icono name="building-2" class="size-7" /></div>
        <h2 class="text-lg font-bold text-azul-950 mt-3">Primero registre un Negocio</h2>
        <p class="text-sm text-gray-500 mt-1">Cada Negocio tiene su propio inventario, caja y ventas. Cree el primero y vuelva aquí.</p>
        <a href="{{ route('admin.cybers.create') }}" class="mt-4 inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><x-icono name="plus" class="size-4" /> Registrar Negocio</a>
    </div>
</x-layout>
