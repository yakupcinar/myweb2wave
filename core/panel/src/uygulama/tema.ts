// P7a: tema seçimi (kullanıcı: açık varsayılan + Açık / Koyu / Sistem seçici).
// Seçim tarayıcıda; çözüm templates/panel/tema.html.twig'deki boyama öncesi
// betikle aynı (kural 7) — sayfa açılırken orası, seçim değişince burası.
export type Tema = "acik" | "koyu" | "sistem";

const ANAHTAR = "panel-tema";
const KOYU_SISTEM = "(prefers-color-scheme: dark)";

export function temaOku(): Tema {
    try {
        const t = localStorage.getItem(ANAHTAR);
        return t === "koyu" || t === "sistem" ? t : "acik";
    } catch {
        return "acik";
    }
}

export function temaUygula(t: Tema): void {
    const koyu =
        t === "koyu" ||
        (t === "sistem" && window.matchMedia(KOYU_SISTEM).matches);
    document.documentElement.dataset.tema = koyu ? "koyu" : "acik";
}

export function temaSec(t: Tema): void {
    try {
        localStorage.setItem(ANAHTAR, t);
    } catch {
        // Gizli pencere: seçim bu sayfada geçerli, saklanmaz.
    }
    temaUygula(t);
}

/** "Sistem" seçiliyken işletim sistemi teması değişirse panel de değişir. */
export function sistemiDinle(): () => void {
    const m = window.matchMedia(KOYU_SISTEM);
    const dinle = (): void => temaUygula("sistem");
    m.addEventListener("change", dinle);
    return () => m.removeEventListener("change", dinle);
}
