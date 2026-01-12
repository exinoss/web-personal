// Año actual en el footer
const yearEl = document.getElementById("year");
if (yearEl) yearEl.textContent = new Date().getFullYear();

// Menú móvil
const toggle = document.querySelector(".menu-toggle");
const menu = document.getElementById("menu");

function setMenu(open) {
    if (!toggle || !menu) return;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    document.body.classList.toggle("menu-open", open);
}

if (toggle && menu) {
    toggle.addEventListener("click", () =>
        setMenu(toggle.getAttribute("aria-expanded") !== "true")
    );
    menu.addEventListener("click", (e) => {
        const a = e.target.closest("a");
        if (a) setMenu(false);
    });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") setMenu(false);
    });
}

// Breadcrumb dinámico
const crumb = document.getElementById("crumb-current");
const sections = Array.from(document.querySelectorAll("[data-crumb]"));
const observer = new IntersectionObserver(
    (entries) => {
        const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => (b.intersectionRatio ?? 0) - (a.intersectionRatio ?? 0))[0];
        if (!visible || !crumb) return;
        crumb.textContent = visible.target.getAttribute("data-crumb") || "Inicio";
    },
    { rootMargin: "-35% 0px -55% 0px", threshold: [0.05, 0.25, 0.5] }
);
sections.forEach((s) => observer.observe(s));

// Paginador de proyectos
const track = document.getElementById("projects-track");
const dots = Array.from(document.querySelectorAll(".pager .dot"));
const pagerBtns = Array.from(document.querySelectorAll("[data-pager]"));
let page = 0;

function setPage(next) {
    if (!track) return;
    const max = Math.max(0, dots.length - 1);
    page = Math.min(max, Math.max(0, next));
    track.style.transform = `translateX(${page * -100}%)`;
    dots.forEach((d, i) => {
        const active = i === page;
        d.classList.toggle("is-active", active);
        d.setAttribute("aria-selected", active ? "true" : "false");
    });
}

dots.forEach((d) =>
    d.addEventListener("click", () => {
        const p = Number(d.getAttribute("data-page") || "0");
        setPage(p);
    })
);
pagerBtns.forEach((b) =>
    b.addEventListener("click", () => {
        const dir = b.getAttribute("data-pager");
        setPage(dir === "next" ? page + 1 : page - 1);
    })
);
setPage(0);

// Loader de iframes
const embeds = Array.from(document.querySelectorAll("[data-embed] iframe"));
embeds.forEach((frame) => {
    frame.addEventListener("load", () => {
        const wrap = frame.closest("[data-embed]");
        if (wrap) wrap.classList.add("is-loaded");
    });
});

// Quitar estado de carga
document.body.classList.remove("is-loading");
document.body.classList.add("is-ready");
