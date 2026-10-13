(() => {
    "use strict";
    const decode = (s) =>
            Uint8Array.from(
                atob(s.replace(/-/g, "+").replace(/_/g, "/")),
                (c) => c.charCodeAt(0),
            ),
        encode = (b) => btoa(String.fromCharCode(...new Uint8Array(b)));
    async function post(url, data) {
        const r = await fetch(url, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector("meta[name=csrf-token]")?.content ||
                    document.querySelector("[name=_token]").value,
            },
            body: JSON.stringify(data),
        });
        const j = await r.json();
        if (!r.ok)
            throw Error(
                Object.values(j.errors || {})
                    .flat()
                    .join(" ") ||
                    j.message ||
                    "No se pudo verificar.",
            );
        return j;
    }
    function options(j) {
        j.publicKey.challenge = decode(j.publicKey.challenge);
        if (j.publicKey.user) j.publicKey.user.id = decode(j.publicKey.user.id);
        for (const key of ["allowCredentials", "excludeCredentials"])
            for (const c of j.publicKey[key] || []) c.id = decode(c.id);
        return j;
    }
    const form = document.getElementById("jz-passkey-create"),
        login = document.getElementById("jz-passkey-login");
    function supported() {
        if (!window.isSecureContext || !window.PublicKeyCredential)
            throw Error(
                "Use HTTPS y un dispositivo compatible con llaves de acceso.",
            );
    }
    function error(e) {
        return ["NotAllowedError", "AbortError"].includes(e.name)
            ? "Operación cancelada o tiempo agotado. Puede volver a intentar."
            : e.message;
    }
    if (form)
        form.onsubmit = async (e) => {
            e.preventDefault();
            const out = document.getElementById("jz-passkey-status"),
                btn = form.querySelector("button");
            btn.disabled = true;
            try {
                supported();
                out.textContent = "Confirme su identidad en el dispositivo…";
                const j = await post(form.dataset.options, {
                    password: form.password.value,
                });
                form.password.value = "";
                const c = await navigator.credentials.create(options(j));
                await post(form.dataset.save, {
                    clientDataJSON: encode(c.response.clientDataJSON),
                    attestationObject: encode(c.response.attestationObject),
                    nombre: form.nombre.value,
                });
                location.reload();
            } catch (e) {
                out.textContent = error(e);
            } finally {
                btn.disabled = false;
            }
        };
    if (login)
        login.onclick = async () => {
            const out = document.getElementById("jz-passkey-status");
            login.disabled = true;
            try {
                supported();
                out.textContent = "Confirme el acceso en su dispositivo…";
                const j = await post(login.dataset.options, {}),
                    c = await navigator.credentials.get(options(j)),
                    v = await post(login.dataset.login, {
                        rawId: encode(c.rawId),
                        clientDataJSON: encode(c.response.clientDataJSON),
                        authenticatorData: encode(c.response.authenticatorData),
                        signature: encode(c.response.signature),
                    });
                location.href = v.url;
            } catch (e) {
                out.textContent = error(e);
            } finally {
                login.disabled = false;
            }
        };
})();
