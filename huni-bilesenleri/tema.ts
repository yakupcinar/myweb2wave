// Huni teması (E2.2) — huni sayfası ve panel düzenleyicisi için TEK kaynak
// (B4/H1). Doğrulama çekirdekte (Tema.php); burada yalnız çizim. İki dildeki
// formül tema-ornekleri.json ile ölçülür: TemaTest.php ve tema.test.ts aynı
// dosyayı okur.

export type YaziTipi = "sistem" | "serif" | "yuvarlak" | "mono";

export type Tema = {
    anaRenk: string;
    arkaPlan: string;
    metinRengi: string;
    yaziTipi: YaziTipi;
    kose: number;
};

/** Tema::VARSAYILAN ile aynı; huni.css'teki :root değerleri de bundan (testle). */
export const VARSAYILAN_TEMA: Tema = {
    anaRenk: "#2563eb",
    arkaPlan: "#ffffff",
    metinRengi: "#111827",
    yaziTipi: "sistem",
    kose: 10,
};

/** Yalnız cihaz yazı tipleri — dışarıdan font yüklemek IP'yi üçüncü tarafa yollar (Ç12). */
export const YAZI_TIPLERI: Record<YaziTipi, string> = {
    sistem: "system-ui, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    yuvarlak: "ui-rounded, 'SF Pro Rounded', 'Nunito', system-ui, sans-serif",
    mono: "ui-monospace, 'SF Mono', Menlo, monospace",
};

/** WCAG göreli parlaklık; hex #rrggbb. */
function parlaklik(hex: string): number {
    const kanal = (i: number): number => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * kanal(1) + 0.7152 * kanal(3) + 0.0722 * kanal(5);
}

/** WCAG kontrast oranı (1–21). */
export function kontrast(a: string, b: string): number {
    const [x, y] = [parlaklik(a), parlaklik(b)];
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

/** Düğme yazısı: ana renkte beyaz ya da siyahtan okunanı. */
export function anaYazi(ana: string): "#ffffff" | "#000000" {
    return kontrast(ana, "#ffffff") >= kontrast(ana, "#000000")
        ? "#ffffff"
        : "#000000";
}

/** huni.css'in okuduğu değişkenler. Değerler çekirdekte doğrulandı; yine de çağıran CSS'e setProperty / style nesnesiyle yazar, dizge birleştirmeyle değil. */
export function temaDegiskenleri(tema: Tema): Record<string, string> {
    return {
        "--ana": tema.anaRenk,
        "--ana-yazi": anaYazi(tema.anaRenk),
        "--arka": tema.arkaPlan,
        "--metin": tema.metinRengi,
        "--kose": `${tema.kose}px`,
        "--yazi-tipi": YAZI_TIPLERI[tema.yaziTipi],
    };
}
