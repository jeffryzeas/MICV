(() => {
    "use strict";
    const app = document.getElementById("jz-chat");
    if (!app) return;
    const $ = (id) => document.getElementById("jzc-" + id),
        boot = JSON.parse($("boot").textContent),
        base = app.dataset.base,
        icons = $("icons").content;
    const icon = (name) =>
        icons.querySelector(`[data-icon="${name}"] svg`).cloneNode(true);
    const element = (tag, cl, text) => {
        const e = document.createElement(tag);
        if (cl) e.className = cl;
        if (text !== undefined) e.textContent = text;
        return e;
    };
    let me = boot.me,
        current = 0,
        other = null,
        last = 0,
        first = 0,
        version = 0,
        loading = 0,
        busy = false,
        choosing = false,
        attachment = null,
        sendKey = null,
        chats = [],
        unread = false,
        infoVersion = 0;
    const nodes = new Map();
    function fit() {
        const v = visualViewport;
        app.style.setProperty("--app-h", (v ? v.height : innerHeight) + "px");
    }
    window.addEventListener("resize", fit);
    window.visualViewport?.addEventListener("resize", fit);
    fit();
    function status(text, error = false) {
        $("estado").textContent = text;
        $("estado").classList.toggle("error", error);
    }
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
                "La sesión terminó o no hay conexión. Recargue la página.",
            );
        }
        if (r.status === 419 && j.token)
            document.querySelector("meta[name=csrf-token]").content = j.token;
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
    function avatar(p, cl = "") {
        const av = element("span", "av " + cl);
        if (p?.foto) {
            const img = new Image();
            img.src = p.foto;
            img.alt = p.nombre || "";
            av.append(img);
        } else {
            av.classList.add("av-letter");
            av.style.background = "var(--brand)";
            av.textContent = (p?.nombre || "Usuario")
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((s) => s[0])
                .join("")
                .toUpperCase();
        }
        return av;
    }
    function meAvatar() {
        $("me").replaceChildren(avatar(me, "av-sm"));
    }
    meAvatar();
    function contact(c, start = false) {
        const p = c.perfil || c,
            b = element(
                "button",
                "item" +
                    (!start && current === c.id ? " active" : "") +
                    (c.nuevos ? " unread" : ""),
            );
        b.type = "button";
        b.append(avatar(p));
        const body = element("div", "item-body"),
            top = element("div", "item-top"),
            name = element("span", "item-name");
        name.append(element("span", "", c.nombre));
        if (p.verificado) {
            const check = icon("circle-check");
            check.classList.add("verified");
            name.append(check);
        }
        top.append(name);
        if (c.fecha)
            top.append(
                element(
                    "span",
                    "item-time",
                    new Date(c.fecha).toLocaleTimeString("es-CR", {
                        hour: "2-digit",
                        minute: "2-digit",
                    }),
                ),
            );
        const bottom = element("div", "item-bottom");
        bottom.append(
            element(
                "span",
                "item-preview",
                start ? p.info || p.rol : c.ultimo || c.rol,
            ),
        );
        if (c.nuevos) bottom.append(element("span", "unread-badge", c.nuevos));
        body.append(top, bottom);
        b.append(body);
        b.onclick = async () => {
            if (busy || choosing) return;
            choosing = true;
            editor(false);
            try {
                let id = c.id;
                if (start)
                    id = (await api("/abrir", "POST", { user_id: p.id })).id;
                await open({ ...c, id, perfil: p });
                $("buscar").value = "";
                $("resultados").hidden = true;
                $("lista").hidden = false;
                closeSheet();
            } catch (e) {
                status(e.message, true);
            } finally {
                choosing = false;
                editor(!!current);
            }
        };
        return b;
    }
    function renderList() {
        const q = $("buscar").value.trim().toLocaleLowerCase(),
            rows = chats.filter(
                (c) =>
                    (!unread || c.nuevos) &&
                    (!q || c.nombre.toLocaleLowerCase().includes(q)),
            );
        $("lista").replaceChildren(...rows.map((c) => contact(c)));
        if (!rows.length) {
            const e = element("div", "list-empty");
            e.append(
                icon("messages-square"),
                element(
                    "p",
                    "",
                    unread
                        ? "No tiene conversaciones sin leer."
                        : "Busque un contacto o pulse Nuevo chat.",
                ),
            );
            $("lista").append(e);
        }
    }
    async function list() {
        const j = await api("/lista");
        chats = j.chats;
        $("total").hidden = !j.total;
        $("total").textContent = j.total || "";
        document.title = (j.total ? "(" + j.total + ") " : "") + "SMS JZ";
        renderList();
        if (other) {
            const fresh = chats.find((c) => c.id === current)?.perfil;
            if (fresh) {
                other = fresh;
                header();
            }
        }
    }
    function editor(enable) {
        $("texto").disabled = !enable || busy;
        $("file").disabled = !enable || busy;
        $("enviar").disabled = !enable || busy;
    }
    function header() {
        $("nombre").textContent = other.nombre;
        $("rol").textContent = other.online ? "En línea" : other.rol;
        $("avatar").replaceChildren(avatar(other, "av-sm"));
    }
    async function open(c) {
        version++;
        current = c.id;
        other = c.perfil;
        last = first = 0;
        nodes.clear();
        $("items").replaceChildren();
        $("texto").value = "";
        attachment = null;
        sendKey = null;
        $("file").value = "";
        $("adjunto").hidden = true;
        $("empty").hidden = true;
        $("conv").hidden = false;
        $("info-panel").hidden = true;
        $("findbar").hidden = true;
        app.dataset.view = "chat";
        header();
        status("Cargando conversación…");
        await load(true);
        await list();
        status("Mensajes y archivos privados");
    }
    function showImage(url, name, download = url) {
        $("imagen").src = url;
        $("imagen").alt = name || "Foto";
        $("descargar").href = download;
        $("descargar").download = name || "Foto.jpg";
        $("viewer").showModal();
    }
    function draw(m, prepend = false) {
        if (nodes.has(m.id)) return;
        const row = element("article", "msg first " + (m.mio ? "out" : "in")),
            bubble = element("div", "bubble");
        if (m.sistema) {
            const label = element("strong", "system-label");
            label.append(icon("bell"), document.createTextNode("Sistema JZ"));
            bubble.append(label);
        }
        if (m.archivo) {
            if (m.mime.startsWith("image/")) {
                const img = new Image();
                img.className = "chat-photo";
                img.width = 250;
                img.height = 190;
                img.alt = m.nombre;
                img.src = m.miniatura || m.archivo;
                img.loading = "lazy";
                img.tabIndex = 0;
                img.onclick = () =>
                    showImage(m.archivo, m.nombre, m.archivo + "?descargar=1");
                img.onkeydown = (e) => {
                    if (e.key === "Enter") img.click();
                };
                bubble.append(img);
            } else {
                const a = element("a", "doc-file");
                a.href = m.archivo;
                a.download = m.nombre;
                const t = element("span", "", m.nombre);
                t.append(
                    element(
                        "small",
                        "",
                        (m.bytes / 1024 / 1024).toFixed(1) + " MB",
                    ),
                );
                a.append(icon("file-text"), t, icon("download"));
                bubble.append(a);
            }
        }
        if (m.texto) bubble.append(element("p", "text", m.texto));
        const time = element(
            "time",
            "",
            new Date(m.hora).toLocaleTimeString("es-CR", {
                hour: "2-digit",
                minute: "2-digit",
            }),
        );
        time.dateTime = m.hora;
        if (m.mio) {
            const check = icon(m.leido ? "check-check" : "check");
            check.dataset.read = m.id;
            if (m.leido) check.classList.add("read");
            time.append(check);
        }
        bubble.append(time);
        row.append(bubble);
        nodes.set(m.id, { el: row, m });
        prepend ? $("items").prepend(row) : $("items").append(row);
    }
    async function load(initial = false, older = false) {
        if (!current || loading === current) return;
        const cid = current,
            v = version;
        loading = cid;
        const h = $("historial"),
            near = h.scrollHeight - h.scrollTop - h.clientHeight < 130,
            oldHeight = h.scrollHeight;
        try {
            const j = await api(
                "/" +
                    cid +
                    "/historial" +
                    (older
                        ? "?antes=" + first
                        : initial
                          ? ""
                          : "?despues=" + last),
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
            $("anteriores").hidden =
                initial || older
                    ? j.mensajes.length < 60
                    : $("anteriores").hidden;
            $("vacio").hidden = nodes.size > 0;
            for (const [id, { el }] of nodes)
                if (id <= j.otro_leido) {
                    const mark = el.querySelector("[data-read]");
                    if (mark && !mark.classList.contains("read")) {
                        const check = icon("check-check");
                        check.dataset.read = id;
                        check.classList.add("read");
                        mark.replaceWith(check);
                    }
                }
            if (older) h.scrollTop = h.scrollHeight - oldHeight;
            else if (initial || near) h.scrollTop = h.scrollHeight;
            if (
                !document.hidden &&
                app.dataset.view === "chat" &&
                last &&
                !older &&
                (initial || near)
            )
                await api("/" + cid + "/leer", "POST", { hasta: last });
            filterMessages();
        } catch (e) {
            status(e.message, true);
        } finally {
            if (loading === cid) loading = 0;
        }
    }
    $("historial").onscroll = () => {
        $("scroll").hidden =
            $("historial").scrollHeight -
                $("historial").scrollTop -
                $("historial").clientHeight <
            160;
    };
    $("scroll").onclick = () => {
        $("historial").scrollTop = $("historial").scrollHeight;
        load();
    };
    $("anteriores").onclick = () => load(false, true);
    $("volver").onclick = () => {
        version++;
        current = 0;
        app.dataset.view = "list";
        $("info-panel").hidden = true;
        editor(false);
        list().catch((e) => status(e.message, true));
    };
    $("cerrar").onclick = () => $("viewer").close();
    $("viewer").addEventListener("close", () => {
        $("imagen").removeAttribute("src");
    });
    function closeSheet() {
        $("sheet").hidden = true;
        $("sheet").replaceChildren();
    }
    function sheet(title) {
        const box = $("sheet");
        box.replaceChildren();
        const head = element("header", "sheet-head"),
            back = element("button", "icon-btn");
        back.type = "button";
        back.setAttribute("aria-label", "Volver");
        back.append(icon("arrow-left"));
        back.onclick = closeSheet;
        head.append(back, element("h2", "", title));
        const body = element("div", "sheet-body");
        box.append(head, body);
        box.hidden = false;
        return body;
    }
    async function contacts(q, box) {
        const j = await api("/contactos?q=" + encodeURIComponent(q));
        box.replaceChildren(...j.map((c) => contact(c, true)));
        if (!j.length)
            box.append(
                element(
                    "p",
                    "list-empty",
                    "No se encontraron contactos autorizados.",
                ),
            );
    }
    let searchTimer;
    $("buscar").oninput = () => {
        clearTimeout(searchTimer);
        renderList();
        const q = $("buscar").value.trim();
        if (!q) {
            $("resultados").hidden = true;
            $("lista").hidden = false;
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                $("resultados").hidden = false;
                $("lista").hidden = true;
                await contacts(q, $("resultados"));
            } catch (e) {
                status(e.message, true);
            }
        }, 250);
    };
    $("nuevo").onclick = () => {
        const body = sheet("Nuevo chat"),
            search = element("input", "profile-input");
        search.placeholder = "Buscar contacto";
        search.setAttribute("aria-label", "Buscar contacto");
        const box = element("div", "pick-list");
        body.append(search, box);
        let t;
        search.oninput = () => {
            clearTimeout(t);
            t = setTimeout(
                () =>
                    contacts(search.value, box).catch((e) =>
                        status(e.message, true),
                    ),
                200,
            );
        };
        contacts("", box).catch((e) => status(e.message, true));
        search.focus();
    };
    $("todos").onclick = () => {
        unread = false;
        tabs();
    };
    $("no-leidos").onclick = () => {
        unread = true;
        tabs();
    };
    function tabs() {
        for (const id of ["todos", "no-leidos"]) {
            $(id).classList.toggle("active", (id === "no-leidos") === unread);
            $(id).setAttribute(
                "aria-selected",
                String((id === "no-leidos") === unread),
            );
        }
        renderList();
    }
    $("menu-btn").onclick = () => ($("menu").hidden = !$("menu").hidden);
    $("tema").onclick = () => {
        const dark = document.documentElement.dataset.theme !== "dark";
        document.documentElement.dataset.theme = dark ? "dark" : "light";
        localStorage.setItem("jz-tema", dark ? "oscuro" : "claro");
        $("menu").hidden = true;
    };
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".menu-wrap")) {
            $("menu").hidden = true;
            $("attach-menu").hidden = true;
        }
    });
    function button(text, ic, action, cl = "btn btn-ghost") {
        const b = element("button", cl);
        b.type = "button";
        b.append(icon(ic), document.createTextNode(text));
        b.onclick = action;
        return b;
    }
    async function ownProfile() {
        const body = sheet("Perfil");
        $("menu").hidden = true;
        const art = element("div", "sheet-avatar"),
            wrap = element("div", "pf-avatar"),
            photo = element("button", "pf-photo");
        photo.type = "button";
        photo.title = "Ver foto";
        photo.append(avatar(me, "av-xl"));
        photo.onclick = () => {
            if (me.foto) showImage(me.foto, me.nombre);
        };
        const choose = element("label", "pf-cam");
        choose.title = "Cambiar foto";
        choose.append(icon("camera"));
        const file = document.createElement("input");
        file.type = "file";
        file.accept = "image/jpeg,image/png,image/webp";
        file.hidden = true;
        choose.append(file);
        wrap.append(photo, choose);
        art.append(wrap);
        body.append(art);
        const section = element("div", "sheet-section pf-list"),
            form = element("form", "");
        const name = element("input", "profile-input");
        name.value = me.nombre;
        name.maxLength = 150;
        name.disabled = me.cliente;
        name.setAttribute("aria-label", "Nombre");
        const about = element("textarea", "profile-input");
        about.rows = 3;
        about.maxLength = 200;
        about.value = me.info;
        about.setAttribute("aria-label", "Información del perfil");
        const out = element("p", "profile-help");
        const label = element("label", "pf-row"),
            nameWrap = element("div", "pf-main");
        nameWrap.append(element("small", "", "Nombre"), name);
        const nameIcon = element("span", "pf-ic");
        nameIcon.append(icon("user-round"));
        label.append(nameIcon, nameWrap, icon("pencil"));
        const info = element("label", "pf-row"),
            infoWrap = element("div", "pf-main"),
            infoIcon = element("span", "pf-ic");
        infoIcon.append(icon("info"));
        infoWrap.append(element("small", "", "Info."), about);
        info.append(infoIcon, infoWrap, icon("pencil"));
        form.append(label, info);
        if (me.cliente) {
            const link = element(
                "a",
                "profile-help",
                "Su nombre se modifica en Mis datos.",
            );
            link.href = boot.datos;
            form.append(link);
        }
        const user = element(
            "p",
            "profile-help",
            "Usuario: " + me.usuario + " · " + me.rol,
        );
        form.append(user);
        const save = element("button", "btn btn-primary", "Guardar perfil");
        save.type = "submit";
        form.append(save, out);
        section.append(form);
        body.append(section);
        const links = element("div", "sheet-section profile-links");
        for (const [title, url, ic] of [
            ["Seguridad y contraseña", boot.perfil, "shield-check"],
            ["Llaves de acceso", boot.llaves, "fingerprint"],
        ]) {
            const a = element("a", "", title);
            a.href = url;
            a.prepend(icon(ic));
            links.append(a);
        }
        body.append(links);
        async function update(data) {
            if (save.disabled) return;
            save.disabled = true;
            file.disabled = true;
            name.disabled = true;
            about.disabled = true;
            out.textContent = "Guardando…";
            try {
                me = await api("/perfil", "POST", data);
                meAvatar();
                await list();
                ownProfile();
            } catch (e) {
                out.textContent = e.message;
                out.style.color = "var(--danger)";
            } finally {
                save.disabled = false;
                file.disabled = false;
                name.disabled = me.cliente;
                about.disabled = false;
            }
        }
        form.onsubmit = (e) => {
            e.preventDefault();
            const d = new FormData();
            d.append("info", about.value);
            if (!me.cliente) d.append("nombre", name.value);
            update(d);
        };
        file.onchange = () => {
            const f = file.files[0];
            if (!f) return;
            if (f.size > 8 * 1024 * 1024) {
                out.textContent = "La foto debe pesar como máximo 8 MB.";
                return;
            }
            const d = new FormData();
            d.append("foto", f);
            update(d);
        };
        if (me.foto)
            art.append(
                button(
                    "Eliminar foto",
                    "x",
                    () => {
                        const d = new FormData();
                        d.append("quitar_foto", "1");
                        update(d);
                    },
                    "link-btn",
                ),
            );
    }
    $("me").onclick = ownProfile;
    $("menu-perfil").onclick = ownProfile;
    async function info() {
        if (!other) return;
        const ticket = ++infoVersion,
            panel = $("info-panel");
        panel.hidden = false;
        panel.replaceChildren();
        const head = element("header", "info-head"),
            close = button(
                "",
                "x",
                () => {
                    panel.hidden = true;
                },
                "icon-btn",
            );
        close.setAttribute("aria-label", "Cerrar perfil");
        head.append(close, element("strong", "", "Información del contacto"));
        panel.append(head);
        try {
            const p = await api("/perfil/" + other.id);
            if (ticket !== infoVersion || other.id !== p.id) return;
            const hero = element("div", "info-hero");
            const av = avatar(p, "av-lg");
            av.tabIndex = 0;
            av.onclick = () => {
                if (p.foto) showImage(p.foto, p.nombre);
            };
            hero.append(
                av,
                element("h2", "", p.nombre),
                element("p", "", p.rol),
            );
            panel.append(hero);
            const section = element("div", "info-card");
            section.append(
                element("h4", "", "Info."),
                element("p", "", p.info || "Sin información de perfil"),
                element("h4", "", "Usuario"),
                element("p", "", "@" + p.usuario),
            );
            if (p.verificado) {
                const verified = element("div", "info-row");
                verified.append(
                    icon("circle-check"),
                    document.createTextNode("Cuenta verificada"),
                );
                section.append(verified);
            }
            panel.append(section);
            const files = element("div", "info-card");
            files.append(element("h4", "", "Archivos, fotos y documentos"));
            const list = element("div", "info-assets");
            files.append(list);
            panel.append(files);
            await assets(list, 0, ticket);
        } catch (e) {
            panel.append(element("p", "info-card", e.message));
        }
    }
    async function assets(box, before, ticket) {
        const cid = current,
            j = await api(
                "/" + cid + "/archivos" + (before ? "?antes=" + before : ""),
            );
        if (ticket !== infoVersion || cid !== current) return;
        if (!j.archivos.length && !before)
            box.append(
                element(
                    "p",
                    "profile-help",
                    "Todavía no se han compartido archivos.",
                ),
            );
        for (const m of j.archivos) {
            const a = element("button", "asset-button");
            a.type = "button";
            if (m.mime.startsWith("image/")) {
                const img = new Image();
                img.src = m.miniatura || m.archivo;
                img.alt = "";
                a.append(img);
                a.onclick = () =>
                    showImage(m.archivo, m.nombre, m.archivo + "?descargar=1");
            } else {
                a.append(icon("file-text"));
                a.onclick = () => {
                    const link = document.createElement("a");
                    link.href = m.archivo;
                    link.download = m.nombre;
                    link.click();
                };
            }
            a.append(element("span", "", m.nombre));
            box.append(a);
        }
        if (j.mas) {
            const next = button("Ver más", "arrow-left", async () => {
                next.remove();
                try {
                    await assets(box, j.antes, ticket);
                } catch (e) {
                    status(e.message, true);
                }
            });
            box.append(next);
        }
    }
    $("who").onclick = info;
    $("info").onclick = () => {
        $("info-panel").hidden ? info() : ($("info-panel").hidden = true);
    };
    $("find").onclick = () => {
        $("findbar").hidden = !$("findbar").hidden;
        if (!$("findbar").hidden) $("find-input").focus();
        else {
            $("find-input").value = "";
            filterMessages();
        }
    };
    function filterMessages() {
        const q = $("find-input").value.trim().toLocaleLowerCase();
        let n = 0;
        for (const { el, m } of nodes.values()) {
            el.hidden =
                !!q &&
                !((m.texto || "") + " " + (m.nombre || ""))
                    .toLocaleLowerCase()
                    .includes(q);
            if (!el.hidden) n++;
        }
        $("find-status").textContent = q
            ? n + " coincidencias en mensajes cargados"
            : "";
    }
    $("find-input").oninput = filterMessages;
    $("attach").onclick = () => {
        if (current && !busy)
            $("attach-menu").hidden = !$("attach-menu").hidden;
    };
    for (const [id, accept] of [
        ["pick-foto", "image/jpeg,image/png,image/webp"],
        ["pick-doc", ".pdf,.docx,.xlsx,.pptx"],
    ])
        $(id).onclick = () => {
            $("file").accept = accept;
            $("file").click();
            $("attach-menu").hidden = true;
        };
    $("pick-camera").onclick = () => {
        $("camera").click();
        $("attach-menu").hidden = true;
    };
    function selected(f) {
        if (!f) return;
        if (f.size > 20 * 1024 * 1024) {
            status("El máximo por archivo es 20 MB.", true);
            return;
        }
        if (!/\.(jpe?g|png|webp|pdf|docx|xlsx|pptx)$/i.test(f.name)) {
            status("Tipo de archivo no permitido.", true);
            return;
        }
        attachment = f;
        sendKey = null;
        $("adjunto").replaceChildren(
            icon("paperclip"),
            element("span", "", f.name),
            button(
                "",
                "x",
                () => {
                    attachment = null;
                    sendKey = null;
                    $("file").value = "";
                    $("adjunto").hidden = true;
                },
                "icon-btn",
            ),
        );
        $("adjunto").hidden = false;
    }
    $("file").onchange = () => selected($("file").files[0]);
    $("camera").onchange = () => selected($("camera").files[0]);
    $("texto").oninput = () => {
        sendKey = null;
        $("texto").style.height = "auto";
        $("texto").style.height = Math.min(140, $("texto").scrollHeight) + "px";
    };
    $("form").onsubmit = async (e) => {
        e.preventDefault();
        if (
            busy ||
            choosing ||
            !current ||
            (!$("texto").value.trim() && !attachment)
        )
            return;
        busy = true;
        editor(false);
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
            status(e.message + " Puede reintentar.", true);
        } finally {
            busy = false;
            editor(!!current);
            $("texto").focus();
        }
    };
    $("texto").onkeydown = (e) => {
        if (
            e.key === "Enter" &&
            !e.shiftKey &&
            matchMedia("(min-width:901px)").matches
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
            status(e.message, true);
        } finally {
            polling = false;
        }
    }
    setInterval(poll, 3000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) poll();
    });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closeSheet();
            $("info-panel").hidden = true;
            $("menu").hidden = true;
            $("attach-menu").hidden = true;
        }
    });
    list().catch((e) => status(e.message, true));
})();
