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
    let retryAt = 0,
        draftUrl = null;
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
        const v=window.visualViewport, height=Math.round(v?v.height:innerHeight),top=Math.round(v?v.offsetTop:0);
        document.documentElement.style.setProperty('--app-h',height+'px');
        document.documentElement.style.setProperty('--app-top',top+'px');
        app.style.setProperty('--app-h',height+'px');
        if(document.activeElement?.id==='jzc-texto')requestAnimationFrame(()=>{const h=document.getElementById('jzc-historial');if(h)h.scrollTop=h.scrollHeight;});
    }
    window.addEventListener('resize',fit);
    window.visualViewport?.addEventListener('resize',fit);
    window.visualViewport?.addEventListener('scroll',fit);
    window.addEventListener('orientationchange',()=>setTimeout(fit,300));
    document.addEventListener('focusin',()=>{requestAnimationFrame(fit);setTimeout(fit,250);});
    document.addEventListener('focusout',()=>setTimeout(fit,250));
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
            if (r.status === 429) j = {};
            else
                throw Error(
                    "La sesión terminó o no hay conexión. Recargue la página.",
                );
        }
        if (r.status === 419 && j.token)
            document.querySelector("meta[name=csrf-token]").content = j.token;
        if (r.status === 429) {
            const seconds = Math.max(
                1,
                Number(r.headers.get("Retry-After")) || 30,
            );
            retryAt = Date.now() + seconds * 1000;
            throw Error(
                "Espere " +
                    seconds +
                    " segundos y vuelva a intentar. Su mensaje y archivo siguen preparados.",
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
        $('me').replaceChildren(avatar(me,'av-sm'));
        if(me.verificado){const v=icon('badge-check');v.classList.add('verified','own-verified');v.setAttribute('aria-label',me.verificacion_tipo || 'Correo verificado');v.removeAttribute('aria-hidden');$('me').append(v);}
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
        name.append(element("span", "name-text", p.nombre||c.nombre));
        if (p.verificado) {
            const check = icon("badge-check");
            check.classList.add("verified");
            check.setAttribute("aria-label",p.verificacion_tipo || "Correo verificado");
            check.setAttribute("role","img");check.removeAttribute("aria-hidden");
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
        $("nombre").replaceChildren(element("span","name-text",other.nombre));
        if (other.verificado) {
            const v = icon("badge-check");
            v.setAttribute("aria-label", other.verificacion_tipo || "Correo verificado");
            v.setAttribute("role","img");v.removeAttribute("aria-hidden");
            v.classList.add("verified");
            $("nombre").append(v);
        }
        $("rol").textContent = other.online ? "En línea" : other.rol;
        $("avatar").replaceChildren(avatar(other, "av-sm"));
    }
    async function open(c) {
        if (recording) {
            status(
                "Termine o cancele la grabación antes de cambiar de chat.",
                true,
            );
            return;
        }
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
    let contextMenu = null;
    function closeContext() {
        contextMenu?.remove();
        contextMenu = null;
    }
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".message-context")) closeContext();
    });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") closeContext();
    });
    window.addEventListener("resize", closeContext);
    function messageMenu(m, x, y) {
        closeContext();
        const menu = element("div", "message-context");
        menu.setAttribute("role", "menu");
        function action(label, ic, fn) {
            const b = element("button", "");
            b.type = "button";
            b.setAttribute("role", "menuitem");
            b.append(icon(ic), document.createTextNode(label));
            b.onclick = async (e) => {
                e.stopPropagation();
                closeContext();
                try {
                    await fn();
                } catch (err) {
                    status(err.message, true);
                }
            };
            menu.append(b);
        }
        if (m.archivo && !m.eliminado)
            action("Descargar", "download", () => {
                const a = document.createElement("a");
                a.href = m.archivo + "?descargar=1";
                a.download = m.nombre || "archivo";
                document.body.append(a);
                a.click();
                a.remove();
            });
        if (m.mio && !m.eliminado)
            action("Editar mensaje", "pencil", () => editMessage(m));
        action("Eliminar para mí", "eye-off", () => confirmDelete(m, "mi"));
        if (m.mio && !m.eliminado)
            action("Eliminar para todos", "trash-2", () =>
                confirmDelete(m, "todos"),
            );
        document.body.append(menu);
        contextMenu = menu;
        const r = menu.getBoundingClientRect();
        menu.style.left =
            Math.max(8, Math.min(x, innerWidth - r.width - 8)) + "px";
        menu.style.top =
            Math.max(8, Math.min(y, innerHeight - r.height - 8)) + "px";
        menu.querySelector("button").focus();
    }
    function editMessage(m) {
        const body = sheet("Editar mensaje");
        const form = element("form", "sheet-section");
        const input = element("textarea", "profile-input");
        input.value = m.texto || "";
        input.maxLength = 6000;
        input.rows = 5;
        input.required = true;
        input.setAttribute("aria-label", "Texto del mensaje");
        const help = element(
            "p",
            "profile-help",
            "El mensaje mostrará la indicación Editado.",
        );
        const save = element("button", "btn btn-primary", "Guardar cambios");
        save.type = "submit";
        const out = element("p", "profile-help");
        out.setAttribute("role", "status");
        form.append(input, help, save, out);
        body.append(form);
        const cid = current;
        form.onsubmit = async (e) => {
            e.preventDefault();
            save.disabled = true;
            try {
                await api("/mensaje/" + m.id, "PATCH", { texto: input.value });
                closeSheet();
                if (cid === current) await load();
            } catch (err) {
                out.textContent = err.message;
            } finally {
                save.disabled = false;
            }
        };
        input.focus();
    }
    function confirmDelete(m, para) {
        const body = sheet(
            para === "todos" ? "Eliminar para todos" : "Eliminar para mí",
        );
        const p = element(
            "p",
            "sheet-section",
            para === "todos"
                ? "Se eliminarán el texto y el archivo para ambos participantes. Esta acción no se puede deshacer."
                : "Este mensaje dejará de estar disponible para usted. El otro participante conservará su copia.",
        );
        const b = element("button", "btn btn-primary", "Confirmar eliminación");
        const out = element("p", "profile-help");
        const section = element("div", "sheet-section");
        section.append(b, out);
        body.append(p, section);
        const cid = current;
        b.onclick = async () => {
            b.disabled = true;
            try {
                await api("/mensaje/" + m.id, "DELETE", { para });
                closeSheet();
                if (cid === current) await load();
            } catch (err) {
                out.textContent = err.message;
                b.disabled = false;
            }
        };
    }
    function draw(m, prepend = false) {
        const previous = nodes.get(m.id);
        if (m.oculto) {
            previous?.el.remove();
            nodes.delete(m.id);
            return;
        }
        if (previous && JSON.stringify(previous.m) === JSON.stringify(m))
            return;
        const row = element("article", "msg first " + (m.mio ? "out" : "in")),
            bubble = element("div", "bubble");
        if (m.sistema) {
            const label = element("strong", "system-label");
            label.append(
                icon("badge-check"),
                document.createTextNode(boot.plataforma || "Mi CV"),
            );
            bubble.append(label);
        }
        if (m.archivo) {
            if (m.mime?.startsWith("audio/")) {
                const audio = element("audio", "chat-audio");
                audio.controls = true;
                audio.preload = "metadata";
                audio.src = m.archivo;
                audio.setAttribute("aria-label", "Nota de voz");
                bubble.append(audio);
            } else if (m.mime.startsWith("image/")) {
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
        if (m.eliminado)
            bubble.append(
                element(
                    "p",
                    "text deleted-message",
                    "Este mensaje fue eliminado.",
                ),
            );
        else if (m.texto) bubble.append(element("p", "text", m.texto));
        if (m.editado && !m.eliminado)
            bubble.append(element("small", "edited-label", "Editado"));
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
        const actions = element("button", "message-actions");
        actions.type = "button";
        actions.setAttribute("aria-label", "Opciones del mensaje");
        actions.append(icon("ellipsis"));
        actions.onclick = (e) => {
            e.stopPropagation();
            messageMenu(
                m,
                actions.getBoundingClientRect().left,
                actions.getBoundingClientRect().bottom,
            );
        };
        bubble.append(actions);
        row.oncontextmenu = (e) => {
            if (e.target.closest("a")) return;
            e.preventDefault();
            messageMenu(m, e.clientX, e.clientY);
        };
        let hold;
        row.addEventListener("pointerdown", (e) => {
            if (e.pointerType === "touch" && !e.target.closest("button,a"))
                hold = setTimeout(
                    () => messageMenu(m, e.clientX, e.clientY),
                    550,
                );
        });
        for (const ev of ["pointerup", "pointercancel", "pointermove"])
            row.addEventListener(ev, () => clearTimeout(hold));
        row.append(bubble);
        nodes.set(m.id, { el: row, m });
        if (previous) previous.el.replaceWith(row);
        else prepend ? $("items").prepend(row) : $("items").append(row);
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
            if (!initial && !older) {
                const ids = [...nodes.keys()];
                for (let i = 0; i < ids.length; i += 200) {
                    const changes = await api(
                        "/" + cid + "/sincronizar",
                        "POST",
                        { ids: ids.slice(i, i + 200) },
                    );
                    if (v !== version) return;
                    changes.mensajes.forEach((m) => draw(m));
                }
            }
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
    let sheetGeneration = 0;
    function closeSheet() {
        sheetGeneration++;
        app.classList.remove("sheet-open");
        $("sheet").hidden = true;
        $("sheet").replaceChildren();
    }
    function sheet(title) {
        sheetGeneration++;
        app.classList.add("sheet-open");
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
    function themeIcon() {
        const dark = document.documentElement.dataset.theme === "dark";
        $("tema").replaceChildren(
            icon(dark ? "sun" : "moon"),
            document.createTextNode(
                dark ? "Cambiar a tema claro" : "Cambiar a tema oscuro",
            ),
        );
        $("tema").setAttribute(
            "aria-label",
            dark ? "Activar tema claro" : "Activar tema oscuro",
        );
    }
    themeIcon();
    $("tema").onclick = () => {
        const dark = document.documentElement.dataset.theme !== "dark";
        document.documentElement.dataset.theme = dark ? "dark" : "light";
        localStorage.setItem("jz-tema", dark ? "oscuro" : "claro");
        themeIcon();
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
        if (!text)
            b.setAttribute("aria-label", ic === "x" ? "Quitar adjunto" : ic);
        return b;
    }
    async function ownProfile() {
        const body = sheet("Perfil");
        const profileGeneration = sheetGeneration;
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
        name.disabled = me.cliente || me.plataforma;
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
                if (profileGeneration === sheetGeneration) ownProfile();
            } catch (e) {
                out.textContent = e.message;
                out.style.color = "var(--danger)";
            } finally {
                save.disabled = false;
                file.disabled = false;
                name.disabled = me.cliente || me.plataforma;
                about.disabled = false;
            }
        }
        form.onsubmit = (e) => {
            e.preventDefault();
            const d = new FormData();
            d.append("info", about.value);
            if (!me.cliente && !me.plataforma) d.append("nombre", name.value);
            update(d);
        };
        file.onchange = () => {
            const f = file.files[0];
            if (!f) return;
            if (f.size > 50 * 1024 * 1024) {
                out.textContent = "La foto debe pesar como máximo 50 MB.";
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
                    icon("badge-check"),
                    document.createTextNode(p.verificacion_tipo || "Correo verificado"),
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
            if (m.mime?.startsWith("audio/")) {
                const audio = element("audio", "chat-audio");
                audio.controls = true;
                audio.preload = "metadata";
                audio.src = m.archivo;
                audio.setAttribute("aria-label", "Nota de voz");
                bubble.append(audio);
            } else if (m.mime.startsWith("image/")) {
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
        ["pick-audio", ".mp3,.m4a,.ogg,.webm,.wav"],
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
        if (!f || busy) return;
        if (f.size > 50 * 1024 * 1024) {
            status("El máximo por archivo es 50 MB.", true);
            return;
        }
        if (
            !/\.(jpe?g|png|webp|pdf|docx|xlsx|pptx|webm|ogg|m4a|mp3|wav)$/i.test(
                f.name,
            )
        ) {
            status("Tipo de archivo no permitido.", true);
            return;
        }
        if (recording) {
            status("Termine o cancele la grabación antes de adjuntar.", true);
            return;
        }
        if (draftUrl) URL.revokeObjectURL(draftUrl);
        draftUrl = URL.createObjectURL(f);
        attachment = f;
        sendKey = null;
        $("adjunto").replaceChildren(
            icon(
                f.type.startsWith("audio/")
                    ? "mic"
                    : f.type.startsWith("image/")
                      ? "image"
                      : "file-text",
            ),
            element(
                "span",
                "draft-filename",
                f.name + " · " + (f.size / 1048576).toFixed(2) + " MB",
            ),
            button(
                "",
                "x",
                () => {
                    if (busy) return;
                    if (draftUrl) {
                        URL.revokeObjectURL(draftUrl);
                        draftUrl = null;
                    }
                    attachment = null;
                    sendKey = null;
                    $("file").value = "";
                    $("adjunto").hidden = true;
                },
                "icon-btn",
            ),
        );
        if (f.type.startsWith("image/")) {
            const preview = new Image();
            preview.className = "draft-photo";
            preview.alt = "Vista previa de " + f.name;
            preview.src = draftUrl;
            preview.onclick = () => showImage(draftUrl, f.name, draftUrl);
            $("adjunto").prepend(preview);
        } else if (f.type.startsWith("audio/")) {
            const preview = element("audio", "draft-audio");
            preview.controls = true;
            preview.src = draftUrl;
            $("adjunto").prepend(preview);
        } else {
            const badge = element(
                "strong",
                "draft-kind",
                f.name.split(".").pop().toUpperCase(),
            );
            $("adjunto").prepend(badge);
        }
        $("adjunto").hidden = false;
    }

    let recording = false,
        recorder = null,
        recordStream = null,
        voiceChunks = [],
        recordStarted = 0,
        recordTimer = null,
        voiceContext = null,
        waveFrame = null,
        recordCancelled = false;
    function cleanupRecording() {
        clearInterval(recordTimer);
        cancelAnimationFrame(waveFrame);
        recordStream?.getTracks().forEach((t) => t.stop());
        voiceContext?.close().catch(() => {});
        $("grabando").hidden = true;
        recording = false;
        $("record").disabled = false;
        editor(!!current);
    }
    async function stopRecording(cancel = false) {
        if (!recording || !recorder) return;
        recordCancelled = cancel;
        recorder.stop();
    }
    $("record").onclick = async () => {
        if (!current || busy || recording) return;
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            status(
                "Para grabar audio use HTTPS y un navegador compatible.",
                true,
            );
            return;
        }
        if (attachment) {
            status("Quite o envíe el adjunto preparado antes de grabar.", true);
            return;
        }
        $("record").disabled = true;
        const cid = current;
        try {
            recordStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });
            if (cid !== current) {
                cleanupRecording();
                return;
            }
            const type = [
                "audio/webm;codecs=opus",
                "audio/ogg;codecs=opus",
                "audio/mp4",
            ].find((t) => MediaRecorder.isTypeSupported(t));
            recorder = new MediaRecorder(
                recordStream,
                type
                    ? { mimeType: type, audioBitsPerSecond: 48000 }
                    : undefined,
            );
            voiceChunks = [];
            recordCancelled = false;
            recorder.ondataavailable = (e) => {
                if (e.data.size) voiceChunks.push(e.data);
            };
            recorder.onstop = () => {
                const mime = recorder.mimeType || "audio/webm",
                    elapsed = (Date.now() - recordStarted) / 1000,
                    cancel = recordCancelled;
                cleanupRecording();
                if (cancel || elapsed < 0.6 || cid !== current) {
                    status("Grabación cancelada.");
                    return;
                }
                const ext = mime.includes("mp4")
                    ? "m4a"
                    : mime.includes("ogg")
                      ? "ogg"
                      : "webm";
                selected(
                    new File(
                        voiceChunks,
                        "nota-de-voz-" + Date.now() + "." + ext,
                        { type: mime },
                    ),
                );
                status("Escuche la nota de voz antes de enviarla.");
            };
            recorder.onerror = () => {
                recordCancelled = true;
                if (recorder.state !== "inactive") recorder.stop();
                cleanupRecording();
                status("No se pudo grabar el audio. Intente de nuevo.", true);
            };
            recording = true;
            recordStarted = Date.now();
            $("grabando").hidden = false;
            editor(false);
            $("record").disabled = true;
            recorder.start(250);
            const tick = () => {
                const n = Math.floor((Date.now() - recordStarted) / 1000);
                $("record-time").textContent =
                    Math.floor(n / 60) + ":" + String(n % 60).padStart(2, "0");
                if (n >= 600) stopRecording();
            };
            tick();
            recordTimer = setInterval(tick, 250);
            const AC = window.AudioContext || window.webkitAudioContext;
            if (AC) {
                voiceContext = new AC();
                const analyser = voiceContext.createAnalyser();
                analyser.fftSize = 256;
                voiceContext
                    .createMediaStreamSource(recordStream)
                    .connect(analyser);
                const values = new Uint8Array(analyser.fftSize),
                    canvas = $("wave"),
                    g = canvas.getContext("2d");
                const animate = () => {
                    if (!recording) return;
                    analyser.getByteTimeDomainData(values);
                    g.clearRect(0, 0, canvas.width, canvas.height);
                    g.strokeStyle = "#c9971f";
                    g.lineWidth = 2;
                    g.beginPath();
                    values.forEach((v, i) => {
                        const x = (i * canvas.width) / values.length,
                            y = (v / 255) * canvas.height;
                        i ? g.lineTo(x, y) : g.moveTo(x, y);
                    });
                    g.stroke();
                    waveFrame = requestAnimationFrame(animate);
                };
                animate();
            }
            status("Grabando. Máximo 10 minutos.");
        } catch (e) {
            cleanupRecording();
            status(
                e.name === "NotAllowedError"
                    ? "Autorice el micrófono para grabar una nota de voz."
                    : e.message,
                true,
            );
        }
    };
    $("record-stop").onclick = () => stopRecording();
    $("record-cancel").onclick = () => stopRecording(true);
    window.addEventListener("pagehide", () => {
        if (recording) stopRecording(true);
        recordStream?.getTracks().forEach((t) => t.stop());
        if (draftUrl) URL.revokeObjectURL(draftUrl);
    });
    $("file").onchange = () => selected($("file").files[0]);
    $("camera").onchange = () => selected($("camera").files[0]);
    $("texto").oninput = () => {
        sendKey = null;
        $("texto").style.height = "auto";
        $("texto").style.height = Math.min(140, $("texto").scrollHeight) + "px";
    };
    $("retry").onclick = () => $("form").requestSubmit();
    $("form").onsubmit = async (e) => {
        e.preventDefault();
        if (
            busy ||
            choosing ||
            !current ||
            recording ||
            (!$("texto").value.trim() && !attachment)
        )
            return;
        if (Date.now() < retryAt) {
            status(
                "Espere unos segundos antes de reintentar. El adjunto sigue preparado.",
                true,
            );
            return;
        }
        $("retry").hidden = true;
        busy = true;
        editor(false);
        status(
            attachment
                ? "Revisando y enviando: " + attachment.name
                : "Enviando…",
        );
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
            $('retry').hidden=false;
            $('retry').disabled=Date.now()<retryAt;
            if($('retry').disabled)setTimeout(()=>{$('retry').disabled=false;},Math.max(0,retryAt-Date.now()));
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
        if (document.hidden || polling || Date.now() < retryAt) return;
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
