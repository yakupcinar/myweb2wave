// Testler için (P7e): Tailwind sıfırlaması sınıfsız form öğesini çıplak
// bırakır (p7-taslak-farki.md: çerçevesiz alan, yapışık etiket); sayfaya
// özgü eski sınıflar da (border-kenar bg-zemin) taslaktan ayrı düşer.
// Çizilmiş sayfada her alan ortak stili (ui/Alan: border-kenar-2) taşısın,
// her kutu vurgu rengini. Çerçeve alanın kendisinde ya da sarmalayıcısında
// olabilir (Uygulama'daki şema alanı gibi).
const cerceveli = (e: Element | null): boolean =>
    !!e?.classList.contains("border-kenar-2");

export function ciplakAlanlar(kok: ParentNode): string[] {
    return [...kok.querySelectorAll("input, select, textarea")]
        .filter((e) => {
            const tur = e.getAttribute("type");
            if (tur === "hidden") return false;
            if (tur === "checkbox" || tur === "radio")
                return ![...e.classList].some((c) => c === "accent-vurgu");
            return !cerceveli(e) && !cerceveli(e.parentElement);
        })
        .map(
            (e) =>
                `${e.tagName.toLowerCase()}${e.id ? `#${e.id}` : ""}[${e.getAttribute("aria-label") ?? e.getAttribute("placeholder") ?? e.getAttribute("type") ?? ""}]`,
        );
}
