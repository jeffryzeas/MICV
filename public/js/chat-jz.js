(() => {
    "use strict";
    const app = document.getElementById("jz-chat");
    if (!app) return;
    const $ = (id) => document.getElementById("jzc-" + id),
        base = app.dataset.base;
    let current = 0,
        last = 0,
        first = 0,
        busy = false,
        loadingId = 0,
        choosing = false,
        attachment = null,
        sendKey = null,
        version = 0;
    function fitViewport() {
        const v = window.visualViewport;
        const bottom = v ? v.offsetTop + v.height : innerHeight;
        const top = app.getBoundingClientRect().top;
        app.style.height = Math.max(220, bottom - Math.max(0, top) - 12) + "px";
    }
    window.addEventListener("resize", fitViewport);
    window.visualViewport?.addEventListener("resize", fitViewport);
    requestAnimationFrame(fitViewport);
    const nodes = new Map();
    async function api(path, method = "GET", body) {
        const headers = {
            Accept: "application/json",
            "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]")
                .content,
        };
        if (body && !(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
            body = JSON.stringify(body);
        }
        const r = await fetch(base + path, {
            method,
            headers,
            body,
            cache: "no-store",
        });
        let j;
        try {
            j = await r.json();
        } catch {
            throw Error(
                "La sesión terminó o el servidor no respondió. Recargue la página.",
            );
        }
        if (!r.ok)
            throw Error(
                Object.values(j.errors || {})
                    .flat()
                    .join(" ") ||
                    j.message ||
                    "No se pudo completar la acción.",
            );
        return j;
    }
    const status = (t) => ($("estado").textContent = t);
    function contact(c, start = false) {
        const b = document.createElement("button");
        b.type = "button";
        b.className =
            "jzc-contact" + (!start && c.id === current ? " active" : "");
        const avatar = document.createElement("span");
        avatar.className = "jzc-avatar";
        avatar.textContent = c.nombre
            .split(/\s+/)
            .slice(0, 2)
            .map((x) => x[0])
            .join("")
            .toUpperCase();
        const div = document.createElement("div"),
            name = document.createElement("b"),
            role = document.createElement("small");
        name.textContent = c.nombre;
        role.textContent = c.rol;
        div.append(name, role);
        b.append(avatar, div);
        if (c.nuevos) {
            const badge = document.createElement("span");
            badge.className = "jzc-badge";
            badge.textContent = c.nuevos;
            b.append(badge);
        }
        b.onclick = async () => {
            if (busy || choosing) return;
            choosing = true;
            $("texto").disabled = true;
            $("file").disabled = true;
            $("enviar").disabled = true;
            try {
                let id = c.id;
                if (start)
                    id = (await api("/abrir", "POST", { user_id: c.id })).id;
                await open({ ...c, id });
                $("buscar").value = "";
                $("contactos").hidden = true;
            } catch (e) {
                status(e.message);
            } finally {
                choosing = false;
                $("texto").disabled = !current;
                $("file").disabled = !current;
                $("enviar").disabled = !current;
            }
        };
        return b;
    }
    async function list() {
        const j = await api("/lista");
        $("lista").replaceChildren(...j.chats.map((c) => contact(c)));
        $("total").textContent = j.total
            ? j.total + " sin leer"
            : "Mensajes y trabajos";
        if (!j.chats.length) {
            const p = document.createElement("p");
            p.style.padding = "18px";
            p.textContent = "Busque un contacto para iniciar.";
            $("lista").append(p);
        }
    }
    function draw(m, prepend = false) {
        if (nodes.has(m.id)) return;
        const div = document.createElement("article");
        div.className = "jzc-message" + (m.mio ? " mine" : "");
        if (m.sistema) {
            const s = document.createElement("strong");
            s.textContent = "🔔 Sistema JZ";
            div.append(s);
        }
        if (m.archivo) {
            if (m.mime.startsWith("image/")) {
                const img = new Image();
                img.className = "jzc-photo";
                img.width = 220;
                img.height = 160;
                img.alt = m.nombre;
                img.loading = "lazy";
                img.src = m.miniatura || m.archivo;
                img.tabIndex = 0;
                img.onclick = () => {
                    $("imagen").src = m.archivo;
                    $("descargar").href = m.archivo + "?descargar=1";
                    $("viewer").showModal();
                };
                img.onkeydown = (e) => {
                    if (e.key === "Enter") img.click();
                };
                div.append(img);
            } else {
                const a = document.createElement("a");
                a.className = "jzc-document";
                a.href = m.archivo;
                a.textContent =
                    "📄 " +
                    m.nombre +
                    " · " +
                    (m.bytes / 1024 / 1024).toFixed(1) +
                    " MB ↓";
                a.download = m.nombre;
                div.append(a);
            }
        }
        if (m.texto) {
            const p = document.createElement("p");
            p.textContent = m.texto;
            div.append(p);
        }
        const time = document.createElement("time");
        time.dateTime = m.hora;
        time.textContent = new Date(m.hora).toLocaleString("es-CR", {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
        if (m.mio) {
            const mark = document.createElement("span");
            mark.dataset.read = m.id;
            mark.textContent = m.leido ? " ✓✓" : " ✓";
            if (m.leido) mark.className = "read";
            time.append(mark);
        }
        div.append(time);
        nodes.set(m.id, div);
        prepend ? $("items").prepend(div) : $("items").append(div);
    }
    async function open(c) {
        if (busy) return;
        version++;
        current = c.id;
        last = 0;
        first = 0;
        nodes.clear();
        $("items").replaceChildren();
        $("nombre").textContent = c.nombre;
        $("rol").textContent = c.rol;
        app.classList.add("open");
        $("enviar").disabled = true;
        attachment = null;
        $("file").value = "";
        $("adjunto").hidden = true;
        sendKey = null;
        $("texto").value = "";
        await load(true);
        await list();
    }
    async function load(initial = false, older = false) {
        if (!current || loadingId === current) return;
        loadingId = current;
        const cid = current,
            v = version,
            h = $("historial"),
            near = h.scrollHeight - h.scrollTop - h.clientHeight < 120,
            prevHeight = h.scrollHeight;
        try {
            const j = await api(
                "/" +
                    cid +
                    "/historial" +
                    (older
                        ? "?antes=" + first
                        : !initial
                          ? "?despues=" + last
                          : ""),
            );
            if (v !== version) return;
            const rows = older ? [...j.mensajes].reverse() : j.mensajes;
            rows.forEach((m) => draw(m, older));
            if (j.mensajes.length) {
                first = first
                    ? Math.min(first, ...j.mensajes.map((m) => m.id))
                    : j.mensajes[0].id;
                last = Math.max(last, ...j.mensajes.map((m) => m.id));
            }
            $("anteriores").hidden = older
                ? j.mensajes.length < 60
                : initial
                  ? j.mensajes.length < 60
                  : $("anteriores").hidden;
            $("vacio").hidden = nodes.size > 0;
            for (const [id, node] of nodes)
                if (id <= j.otro_leido) {
                    const mark = node.querySelector("[data-read]");
                    if (mark) {
                        mark.textContent = " ✓✓";
                        mark.className = "read";
                    }
                }
            if (older) h.scrollTop = h.scrollHeight - prevHeight;
            else if (initial || near) h.scrollTop = h.scrollHeight;
            // Solo marcar lo realmente mostrado, cuando la conversación está visible.
            if (
                !document.hidden &&
                app.offsetWidth &&
                getComputedStyle(app.querySelector(".jzc-main")).display !==
                    "none" &&
                last &&
                (initial || near) &&
                !older
            )
                await api("/" + cid + "/leer", "POST", { hasta: last });
        } catch (e) {
            status(e.message);
        } finally {
            if (loadingId === cid) loadingId = 0;
        }
    }
    $("anteriores").onclick = () => load(false, true);
    $("volver").onclick = () => {
        version++;
        current = 0;
        app.classList.remove("open");
        list().catch((e) => status(e.message));
    };
    $("cerrar").onclick = () => $("viewer").close();
    let searchTimer;
    $("buscar").oninput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async () => {
            const q = $("buscar").value.trim();
            try {
                const j = await api("/contactos?q=" + encodeURIComponent(q));
                if (q !== $("buscar").value.trim()) return;
                $("contactos").replaceChildren(
                    ...j.map((c) => contact(c, true)),
                );
                $("contactos").hidden = false;
                if (!j.length)
                    $("contactos").textContent =
                        "No se encontraron contactos autorizados.";
            } catch (e) {
                status(e.message);
            }
        }, 250);
    };
    $("buscar").onfocus = () => $("buscar").oninput();
    $("file").onchange = () => {
        const f = $("file").files[0];
        if (!f) return;
        if (f.size > 20 * 1024 * 1024) {
            status("El máximo por archivo es 20 MB.");
            $("file").value = "";
            return;
        }
        if (!/\.(jpe?g|png|webp|pdf|docx|xlsx|pptx)$/i.test(f.name)) {
            status("Tipo de archivo no permitido.");
            $("file").value = "";
            return;
        }
        attachment = f;
        sendKey = null;
        const name = document.createElement("span");
        name.textContent = "📎 " + f.name;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.textContent = "Quitar";
        remove.onclick = () => {
            attachment = null;
            sendKey = null;
            $("file").value = "";
            $("adjunto").hidden = true;
        };
        $("adjunto").replaceChildren(name, remove);
        $("adjunto").hidden = false;
    };
    $("texto").oninput = () => {
        sendKey = null;
        $("texto").style.height = "auto";
        $("texto").style.height = Math.min(120, $("texto").scrollHeight) + "px";
    };
    $("form").onsubmit = async (e) => {
        e.preventDefault();
        if (busy || !current || (!$("texto").value.trim() && !attachment))
            return;
        busy = true;
        $("enviar").disabled = true;
        $("texto").disabled = true;
        $("file").disabled = true;
        status(attachment ? "Revisando y enviando archivo…" : "Enviando…");
        const cid = current;
        sendKey ||= crypto.randomUUID();
        const f = new FormData();
        f.append("clave", sendKey);
        f.append("texto", $("texto").value);
        if (attachment) f.append("archivo", attachment);
        if ($("sistema")?.checked) f.append("sistema", "1");
        try {
            await api("/" + cid + "/enviar", "POST", f);
            $("texto").value = "";
            $("texto").style.height = "auto";
            attachment = null;
            $("file").value = "";
            $("adjunto").hidden = true;
            sendKey = null;
            await load();
            await list();
            $("historial").scrollTop = $("historial").scrollHeight;
            status("Enviado.");
        } catch (e) {
            status(e.message + " Puede reintentar.");
        } finally {
            busy = false;
            $("enviar").disabled = !current;
            $("texto").disabled = false;
            $("file").disabled = false;
            $("texto").focus();
        }
    };
    $("texto").onkeydown = (e) => {
        if (
            e.key === "Enter" &&
            !e.shiftKey &&
            matchMedia("(min-width:761px)").matches
        ) {
            e.preventDefault();
            $("form").requestSubmit();
        }
    };
    let polling = false;
    async function poll() {
        if (document.hidden || polling) return;
        polling = true;
        try {
            await load();
            await list();
        } catch (e) {
            status(e.message);
        } finally {
            polling = false;
        }
    }
    setInterval(poll, 3000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) poll();
    });
    list().catch((e) => status(e.message));
})();
