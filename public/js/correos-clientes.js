(() => {
    "use strict";
    const f = document.getElementById("correo-form");
    if (!f) return;
    const pais = f.elements.pais,
        tipo = f.elements.tipo_documento,
        out = document.getElementById("correo-resultado");
    let seq = 0;
    document.getElementById("correo-ver-clave").onclick = function () {
        const input = f.elements.clave_correo,
            show = input.type === "password";
        input.type = show ? "text" : "password";
        const text=show ? "Ocultar contraseña" : "Mostrar contraseña";
        this.lastChild.textContent=text;
        this.setAttribute("aria-label",text);
    };
    function tipos() {
        tipo.replaceChildren(
            ...Object.entries(window.correoPaises[pais.value].documentos).map(
                ([v, t]) => new Option(t, v),
            ),
        );
        if (window.correoTipo) {
            tipo.value = window.correoTipo;
            window.correoTipo = null;
        }
        seq++;
    }
    pais.onchange = tipos;
    tipos();
    document.getElementById("correo-consultar").onclick = async () => {
        const v = ++seq,
            numero = f.elements.numero_documento.value,
            p = pais.value,
            t = tipo.value;
        out.textContent = "Consultando…";
        try {
            const r = await fetch(window.correoConsulta, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        "meta[name=csrf-token]",
                    ).content,
                },
                body: JSON.stringify({
                    tipo_documento: t,
                    numero_documento: numero,
                }),
            });
            const j = await r.json();
            if (
                v !== seq ||
                p !== pais.value ||
                t !== tipo.value ||
                numero !== f.elements.numero_documento.value
            )
                return;
            if (!r.ok) throw Error("No se pudo consultar el documento.");
            const d = j.cedula || j.cr;
            if (j.bloqueado || d?.valida === false)
                throw Error((d?.errores || ["Revise el documento."]).join(" "));
            if (j.existe && !j.en_mi_cyber) {
                out.textContent =
                    "El documento ya existe. Solicite al administrador revisar la vinculación antes de continuar.";
                return;
            }
            if (j.cliente) {
                for (const k of [
                    "primer_nombre",
                    "segundo_nombre",
                    "primer_apellido",
                    "segundo_apellido",
                ])
                    f.elements[k].value = j.cliente[k] || "";
                if (j.cliente.telefono && !f.elements.celular.value)
                    f.elements.celular.value = j.cliente.telefono;
            }
            out.textContent = [
                j.existe
                    ? "Cliente encontrado; se reutilizará su ficha."
                    : "Documento validado. Complete los nombres del titular.",
                d?.fecha_nacimiento ? "Nacimiento: " + d.fecha_nacimiento : "",
                d?.edad != null ? "Edad: " + d.edad : "",
                d?.codigo_especial ? "Código especial: el lugar no se deduce del número." : d?.municipio || "",
                d?.departamento || "",
            ]
                .filter(Boolean)
                .join(" · ");
        } catch (e) {
            if (v === seq) out.textContent = e.message;
        }
    };
})();
