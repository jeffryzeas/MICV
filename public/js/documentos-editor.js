(() => {
    "use strict";
    const C = window.DocumentosCore,
        $ = (id) => document.getElementById("doc-" + id);
    if (!$("app")) return;
    const state = { photos: [], active: 0, drag: -1, selected: 0, busy: false },
        canvas = $("canvas"),
        ctx = canvas.getContext("2d", { willReadFrequently: true });
    const actual = () => state.photos[state.active],
        defaults = () => ({
            modo: "color",
            brillo: 100,
            contraste: 100,
            umbral: 150,
        });
    const message = (text, error = false) => {
        $("message").textContent = text;
        $("message").classList.toggle("error", error);
    };
    const settings = () => ({
        papel: $("paper").value,
        ancho: 10 * +$("width").value,
        alto: 10 * +$("height").value,
        margen: 10 * +$("margin").value,
        separacion: 10 * +$("gap").value,
        cantidad: state.photos.length || 1,
        ajustar: $("fit").checked,
    });
    function corners(p) {
        const w = p.src.width - 1,
            h = p.src.height - 1;
        return [
            { x: w * 0.04, y: h * 0.04 },
            { x: w * 0.96, y: h * 0.04 },
            { x: w * 0.96, y: h * 0.96 },
            { x: w * 0.04, y: h * 0.96 },
        ];
    }
    function invalidate(p) {
        p.base = null;
    }
    function snapshot(p) {
        return {
            src: p.src,
            points: p.points.map((q) => ({ ...q })),
            filters: { ...p.filters },
        };
    }
    function remember(p) {
        p.undo ||= [];
        p.undo.push(snapshot(p));
        if (p.undo.length > 20) p.undo.shift();
        p.redo = [];
    }
    function history(from, to) {
        const p = actual();
        if (!p || state.busy || !p[from]?.length) return;
        p[to] ||= [];
        p[to].push(snapshot(p));
        Object.assign(p, p[from].pop());
        invalidate(p);
        sync();
    }
    function updateTabs() {
        const box = $("tabs");
        box.replaceChildren();
        state.photos.forEach((p, i) => {
            const b = document.createElement("button");
            b.type = "button";
            b.className = "doc-btn doc-tab";
            b.textContent = "Lado " + (i + 1);
            b.setAttribute(
                "aria-pressed",
                i === state.active ? "true" : "false",
            );
            b.addEventListener("click", () => {
                state.active = i;
                state.selected = 0;
                sync();
            });
            box.append(b);
        });
    }
    function paint() {
        const p = actual();
        canvas.hidden = !p;
        $("empty").hidden = !!p;
        if (!p) return;
        canvas.width = p.src.width;
        canvas.height = p.src.height;
        ctx.drawImage(p.src, 0, 0);
        const points = p.points;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        points.slice(1).forEach((q) => ctx.lineTo(q.x, q.y));
        ctx.closePath();
        ctx.fillStyle = "#0f7e7922";
        ctx.fill();
        const scale =
            canvas.width /
            (canvas.getBoundingClientRect().width || canvas.width);
        ctx.strokeStyle = C.valido(points) ? "#0f9c90" : "#d12d27";
        ctx.lineWidth = 2.5 * scale;
        ctx.stroke();
        points.forEach((q, i) => {
            ctx.beginPath();
            ctx.arc(q.x, q.y, 13 * scale, 0, Math.PI * 2);
            ctx.fillStyle = i === state.selected ? "#153d62" : "#fff";
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = i === state.selected ? "#fff" : "#153d62";
            ctx.font = `bold ${12 * scale}px Arial`;
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText(String(i + 1), q.x, q.y);
        });
        ctx.restore();
    }
    let liveTimer;
    function live() {
        if (liveTimer) return;
        liveTimer = setTimeout(() => {
            liveTimer = null;
            if (state.busy || !actual()) return;
            try {
                for (const p of state.photos) process(p, true);
                preview();
                message("Vista previa actualizada · exportación a 300 ppp.");
            } catch (e) {
                message(e.message, true);
            }
        }, 100);
    }
    function sync() {
        const p = actual();
        updateTabs();
        paint();
        const f = p?.filters || defaults();
        $("filter").value = f.modo;
        $("bright").value = f.brillo;
        $("contrast").value = f.contraste;
        $("threshold").value = f.umbral;
        filterLabels();
        preview();
        updateButtons();
        live();
    }
    function updateButtons() {
        for (const input of $("app").querySelectorAll("input,select"))
            input.disabled = state.busy;
        $("clear").disabled = state.busy;
        filterLabels();
        for (const id of [
            "rotate-left",
            "rotate-right",
            "reset",
            "crop",
            "jpg",
            "png",
            "pdf",
            "print",
            "filter-reset",
            "undo",
            "redo",
            "remove",
            "swap",
        ])
            $(id).disabled = state.busy || !state.photos.length;
    }
    function filterLabels() {
        $("bright-val").textContent = $("bright").value + "%";
        $("contrast-val").textContent = $("contrast").value + "%";
        $("threshold-val").textContent = $("threshold").value;
        $("threshold").disabled = state.busy || $("filter").value !== "bn";
    }
    function preview() {
        const p = actual();
        $("result").hidden = !p?.result;
        if (p?.result) $("result").src = p.result.toDataURL("image/jpeg", 0.88);
        const sheet = $("sheet");
        sheet.replaceChildren();
        try {
            const m = C.medidas(settings());
            sheet.style.aspectRatio = m.pw + "/" + m.ph;
            state.photos.forEach((photo, i) => {
                if (!photo.result) return;
                const img = new Image();
                img.src = photo.result.toDataURL("image/jpeg", 0.85);
                img.alt = "Lado " + (i + 1);
                Object.assign(img.style, {
                    left: (100 * m.posiciones[i].x) / m.pw + "%",
                    top: (100 * m.posiciones[i].y) / m.ph + "%",
                    width: (100 * m.ancho) / m.pw + "%",
                    height: (100 * m.alto) / m.ph + "%",
                });
                sheet.append(img);
            });
            $("size-info").textContent =
                `${(m.ancho / 10).toFixed(2)} × ${(m.alto / 10).toFixed(2)} cm por cara · escala ${(m.escala * 100).toFixed(0)}%`;
        } catch (e) {
            $("size-info").textContent = e.message;
        }
    }
    async function busy(fn) {
        if (state.busy) return;
        state.busy = true;
        updateButtons();
        message("Procesando…");
        try {
            await new Promise(requestAnimationFrame);
            await fn();
        } catch (e) {
            message(e.message || "No se pudo procesar la imagen.", true);
        } finally {
            state.busy = false;
            updateButtons();
        }
    }
    async function cargar(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type))
            throw Error("Use una foto JPG, PNG o WebP.");
        if (file.size > 12 * 1024 * 1024)
            throw Error("Cada foto debe pesar como máximo 12 MB.");
        let image;
        if (window.createImageBitmap) image = await createImageBitmap(file);
        else {
            const url = URL.createObjectURL(file);
            try {
                image = await new Promise((resolve, reject) => {
                    const i = new Image();
                    i.onload = () => resolve(i);
                    i.onerror = () =>
                        reject(Error("No se pudo abrir esta foto."));
                    i.src = url;
                });
            } finally {
                URL.revokeObjectURL(url);
            }
        }
        const scale = Math.min(1, 2400 / Math.max(image.width, image.height)),
            src = document.createElement("canvas");
        src.width = Math.max(2, Math.round(image.width * scale));
        src.height = Math.max(2, Math.round(image.height * scale));
        const g = src.getContext("2d");
        g.fillStyle = "white";
        g.fillRect(0, 0, src.width, src.height);
        g.drawImage(image, 0, 0, src.width, src.height);
        image.close?.();
        const p = { src, filters: defaults(), base: null, result: null };
        p.points = corners(p);
        return p;
    }
    $("files").addEventListener("change", () =>
        busy(async () => {
            const files = [...$("files").files];
            if (files.length > 2) throw Error("Seleccione una o dos fotos.");
            const loaded = [];
            for (const f of files) loaded.push(await cargar(f));
            if (loaded.length === 2) {
                state.photos = loaded;
                state.active = 0;
            } else if (loaded.length === 1) {
                if (state.photos.length < 2) {
                    state.photos.push(loaded[0]);
                    state.active = state.photos.length - 1;
                } else state.photos[state.active] = loaded[0];
            }
            state.active = Math.min(state.active, state.photos.length - 1);
            $("files").value = "";
            sync();
            message(
                "Arrastre las esquinas: la vista se actualiza automáticamente.",
            );
        }),
    );
    function sourcePoint(e) {
        const r = canvas.getBoundingClientRect();
        return {
            x: Math.max(
                0,
                Math.min(
                    canvas.width - 1,
                    ((e.clientX - r.left) * canvas.width) / r.width,
                ),
            ),
            y: Math.max(
                0,
                Math.min(
                    canvas.height - 1,
                    ((e.clientY - r.top) * canvas.height) / r.height,
                ),
            ),
        };
    }
    canvas.addEventListener("pointerdown", (e) => {
        if (state.busy || !actual()) return;
        const q = sourcePoint(e),
            scale = canvas.width / canvas.getBoundingClientRect().width;
        let nearest = -1,
            distance = 32 * scale;
        actual().points.forEach((p, i) => {
            const d = Math.hypot(p.x - q.x, p.y - q.y);
            if (d < distance) {
                distance = d;
                nearest = i;
            }
        });
        if (nearest < 0) return;
        remember(actual());
        state.drag = nearest;
        state.selected = nearest;
        canvas.setPointerCapture(e.pointerId);
        e.preventDefault();
        paint();
    });
    canvas.addEventListener("pointermove", (e) => {
        if (state.drag < 0 || state.busy) return;
        actual().points[state.drag] = sourcePoint(e);
        invalidate(actual());
        paint();
        live();
    });
    for (const ev of ["pointerup", "pointercancel"])
        canvas.addEventListener(ev, () => {
            state.drag = -1;
            preview();
        });
    canvas.addEventListener("keydown", (e) => {
        if (!actual() || state.busy) return;
        const d = {
            ArrowLeft: [-1, 0],
            ArrowRight: [1, 0],
            ArrowUp: [0, -1],
            ArrowDown: [0, 1],
        }[e.key];
        if (!d) {
            if (/^[1-4]$/.test(e.key)) {
                state.selected = +e.key - 1;
                paint();
            }
            return;
        }
        e.preventDefault();
        remember(actual());
        const p = actual().points[state.selected],
            step = e.shiftKey ? 10 : 1;
        p.x = Math.max(0, Math.min(canvas.width - 1, p.x + d[0] * step));
        p.y = Math.max(0, Math.min(canvas.height - 1, p.y + d[1] * step));
        invalidate(actual());
        paint();
        live();
        preview();
    });
    function rotate(direction) {
        const p = actual();
        if (!p || state.busy) return;
        remember(p);
        const src = document.createElement("canvas");
        src.width = p.src.height;
        src.height = p.src.width;
        const g = src.getContext("2d");
        g.translate(src.width / 2, src.height / 2);
        g.rotate((direction * Math.PI) / 2);
        g.drawImage(p.src, -p.src.width / 2, -p.src.height / 2);
        p.src = src;
        p.points = corners(p);
        invalidate(p);
        sync();
    }
    $("rotate-left").onclick = () => rotate(-1);
    $("rotate-right").onclick = () => rotate(1);
    $("reset").onclick = () => {
        const p = actual();
        if (!p) return;
        remember(p);
        p.points = corners(p);
        invalidate(p);
        sync();
    };
    function process(p, quick = false) {
        const s = settings();
        if (
            ![s.ancho, s.alto].every(
                (v) => Number.isFinite(v) && v >= 10 && v <= 500,
            )
        )
            throw Error("Indique ancho y alto entre 1 y 50 cm.");
        if (!p.base || (!quick && p.quick)) {
            if (!C.valido(p.points))
                throw Error("Ajuste las esquinas: no deben cruzarse.");
            const ratio = s.ancho / s.alto;
            let w = Math.round((s.ancho / 25.4) * 300),
                h = Math.round(w / ratio);
            const scale = Math.min(1, (quick ? 650 : 2400) / Math.max(w, h));
            w = Math.max(2, Math.round(w * scale));
            h = Math.max(2, Math.round(h * scale));
            const src = p.src
                .getContext("2d", { willReadFrequently: true })
                .getImageData(0, 0, p.src.width, p.src.height);
            p.base = C.transformar(src, p.points, w, h);
            p.quick = quick;
        }
        const out = C.filtrar(p.base, p.filters),
            result = document.createElement("canvas");
        result.width = out.width;
        result.height = out.height;
        result
            .getContext("2d")
            .putImageData(new ImageData(out.data, out.width, out.height), 0, 0);
        p.result = result;
    }
    $("crop").onclick = () =>
        busy(async () => {
            if (!actual()) return;
            process(actual());
            preview();
            message(
                "Foto enderezada. Puede ajustar los filtros o preparar el PDF.",
            );
        });
    let filterTimer;
    function changeFilter() {
        if (!actual()) return;
        actual().filters = {
            modo: $("filter").value,
            brillo: +$("bright").value,
            contraste: +$("contrast").value,
            umbral: +$("threshold").value,
        };
        filterLabels();
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            const p = actual();
            if (p && !state.busy) {
                try {
                    process(p, true);
                    preview();
                } catch (e) {
                    message(e.message, true);
                }
            }
        }, 100);
    }
    for (const id of ["filter", "bright", "contrast", "threshold"]) {
        $(id).addEventListener("pointerdown", () => {
            if (actual()) remember(actual());
        });
        $(id).addEventListener("keydown", () => {
            if (actual()) remember(actual());
        });
        $(id).addEventListener("input", changeFilter);
    }
    $("filter-reset").onclick = () => {
        if (!actual()) return;
        remember(actual());
        actual().filters = defaults();
        if (actual().base) process(actual());
        sync();
    };
    for (const id of ["width", "height"])
        $(id).addEventListener("input", () => {
            $("preset").value = "libre";
            for (const p of state.photos) invalidate(p);
            live();
        });
    $("preset").onchange = () => {
        if ($("preset").value === "tarjeta") {
            $("width").value = 8.56;
            $("height").value = 5.4;
            for (const p of state.photos) invalidate(p);
        } else {
            const sizes = { pasaporte: [12.5, 8.8], a5: [14.8, 21] }[
                $("preset").value
            ];
            if (sizes) {
                $("width").value = sizes[0];
                $("height").value = sizes[1];
                for (const p of state.photos) invalidate(p);
            }
        }
        preview();
        live();
    };
    for (const id of ["paper", "margin", "gap", "fit"])
        $(id).addEventListener("change", preview);
    function download(blob, name) {
        const url = URL.createObjectURL(blob),
            a = document.createElement("a");
        a.href = url;
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }
    async function image(format) {
        if (!actual()) return;
        process(actual());
        const blob = await new Promise((resolve) =>
            actual().result.toBlob(
                resolve,
                format === "jpg" ? "image/jpeg" : "image/png",
                0.96,
            ),
        );
        if (!blob) throw Error("No se pudo crear la foto.");
        download(blob, "Documento_lado_" + (state.active + 1) + "." + format);
        preview();
        message("Foto lista.");
    }
    $("jpg").onclick = () => busy(() => image("jpg"));
    $("png").onclick = () => busy(() => image("png"));
    async function pdf() {
        if (!state.photos.length) throw Error("Primero cargue una foto.");
        const m = C.medidas(settings());
        const doc = await PDFLib.PDFDocument.create(),
            pt = 72 / 25.4,
            page = doc.addPage([m.pw * pt, m.ph * pt]);
        for (let i = 0; i < state.photos.length; i++) {
            const p = state.photos[i];
            process(p);
            const foto = await doc.embedJpg(
                p.result.toDataURL("image/jpeg", 0.96),
            );
            page.drawImage(foto, {
                x: m.posiciones[i].x * pt,
                y: (m.ph - m.posiciones[i].y - m.alto) * pt,
                width: m.ancho * pt,
                height: m.alto * pt,
            });
        }
        doc.setTitle("Copia de documento");
        doc.setCreator("Mi CV");
        preview();
        return new Blob([await doc.save()], { type: "application/pdf" });
    }
    $("pdf").onclick = () =>
        busy(async () => {
            download(await pdf(), "Copia_documento.pdf");
            message("PDF listo. Imprima al 100% para conservar las medidas.");
        });
    $("print").onclick = () => {
        if (state.busy || !state.photos.length) return;
        const viewer = window.open("about:blank", "_blank");
        if (!viewer) {
            message(
                "Permita abrir la pestaña de impresión o descargue el PDF.",
                true,
            );
            return;
        }
        busy(async () => {
            try {
                const url = URL.createObjectURL(await pdf());
                viewer.location.href = url;
                setTimeout(() => URL.revokeObjectURL(url), 120000);
                message(
                    "PDF abierto. Seleccione tamaño real / 100% al imprimir.",
                );
            } catch (e) {
                viewer.close();
                throw e;
            }
        });
    };
    $("undo").onclick = () => history("undo", "redo");
    $("redo").onclick = () => history("redo", "undo");
    $("remove").onclick = () => {
        if (state.busy) return;
        state.photos.splice(state.active, 1);
        state.active = 0;
        sync();
    };
    $("swap").onclick = () => {
        if (state.busy) return;
        state.photos.reverse();
        state.active = Math.max(0, state.photos.length - 1 - state.active);
        sync();
    };
    $("clear").onclick = () => {
        if (state.busy) return;
        state.photos = [];
        state.active = 0;
        $("files").value = "";
        sync();
        message("Sesión limpia. Puede cargar otras fotos.");
    };
    window.addEventListener("resize", () => {
        if (state.drag < 0) paint();
    });
    sync();
})();
