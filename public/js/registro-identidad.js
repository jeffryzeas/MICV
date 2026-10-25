(() => {
    const pais = document.getElementById('regPais');
    const tipo = document.getElementById('regTipo');
    const datos = document.getElementById('regDocumentos');
    if (!pais || !tipo || !datos) return;
    const paises = JSON.parse(datos.textContent);
    pais.addEventListener('change', () => {
        // No conservar ni elegir por defecto un documento de otro país.
        tipo.replaceChildren(new Option('Seleccione el tipo de documento', ''));
        for (const [value, label] of Object.entries(paises[pais.value]?.documentos || {})) {
            tipo.add(new Option(label, value));
        }
        // Nacionalidad y residencia son declaraciones independientes.
    });
})();
