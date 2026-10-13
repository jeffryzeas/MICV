(() => {
    const link = document.getElementById("jz-chat-notificacion"),
        badge = document.getElementById("jz-chat-contador");
    if (!link) return;
    let busy = false;
    async function refresh() {
        if (document.hidden || busy) return;
        busy = true;
        try {
            const r = await fetch(link.dataset.url, {
                headers: { Accept: "application/json" },
                cache: "no-store",
            });
            if (!r.ok) return;
            const j = await r.json();
            badge.hidden = !j.total;
            badge.textContent = j.total > 99 ? "99+" : j.total;
            link.setAttribute("aria-label", j.total + " mensajes sin leer");
        } catch {
        } finally {
            busy = false;
        }
    }
    refresh();
    setInterval(refresh, 10000);
    document.addEventListener("visibilitychange", refresh);
})();
