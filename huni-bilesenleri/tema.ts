// Huni teması (E2.2) — huni sayfası ve panel düzenleyicisi için TEK kaynak
// (B4/H1). Doğrulama çekirdekte (Tema.php); burada yalnız çizim. İki dildeki
// formül tema-ornekleri.json ile ölçülür: TemaTest.php ve tema.test.ts aynı
// dosyayı okur.

export type YaziTipi = "sistem" | "serif" | "yuvarlak" | "mono";

/** G3: düz arka plan yerine geçiş ya da görsel (okunurluğu çekirdek ölçer). */
export type Zemin =
    | { tur: "gecis"; ikinciRenk: string }
    | { tur: "gorsel"; gorsel: string; karartma: number };

export type Tema = {
    anaRenk: string;
    arkaPlan: string;
    metinRengi: string;
    yaziTipi: YaziTipi;
    kose: number;
    zemin?: Zemin;
    /** G6: içerik bu renkte kartta; okunurluk metin ↔ kart (çekirdek ölçer). */
    kartRengi?: string;
    /** G7: yerleşim ön ayarları; yoksa huni.css'teki bugünkü değerler. */
    baslikBoyu?: "kucuk" | "normal" | "buyuk";
    yogunluk?: "sik" | "normal" | "ferah";
    hizalama?: "sol" | "orta";
};

/** G7: ön ayar → CSS değeri (gorunum-g7.md). "normal"/"sol" = huni.css yedeği. */
const BASLIK = {
    kucuk: ["24px", "700"],
    normal: ["28px", "750"],
    buyuk: ["36px", "850"],
} as const;
const ARA = { sik: "0.75", normal: "1", ferah: "1.4" } as const;
const HIZA = { sol: "left", orta: "center" } as const;

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
export function temaDegiskenleri(
    tema: Tema,
    medyaKoku = "",
): Record<string, string> {
    return {
        "--ana": tema.anaRenk,
        "--ana-yazi": anaYazi(tema.anaRenk),
        "--arka": tema.arkaPlan,
        "--metin": tema.metinRengi,
        "--kose": `${tema.kose}px`,
        "--yazi-tipi": YAZI_TIPLERI[tema.yaziTipi],
        ...(tema.zemin ? zeminDegiskenleri(tema, tema.zemin, medyaKoku) : {}),
        ...(tema.kartRengi ? kartDegiskenleri(tema, tema.kartRengi) : {}),
        ...(tema.baslikBoyu
            ? {
                  "--baslik-boyu": BASLIK[tema.baslikBoyu][0],
                  "--baslik-agirlik": BASLIK[tema.baslikBoyu][1],
              }
            : {}),
        ...(tema.yogunluk ? { "--ara": ARA[tema.yogunluk] } : {}),
        ...(tema.hizalama ? { "--hiza": HIZA[tema.hizalama] } : {}),
    };
}

/**
 * G6: metin kartın üstünde — iç yüzeyler (--arka'dan türeyen soluk, yüzey,
 * çizgi) ve alt çubuk kart rengiyle; zemin (düz renk ya da G3) çevrede.
 */
function kartDegiskenleri(tema: Tema, kart: string): Record<string, string> {
    return {
        "--arka": kart,
        "--kart": kart,
        // G3 zemini varsa yukarıda yazıldı; yoksa çevre düz arka plan rengi.
        ...(tema.zemin ? {} : { "--zemin": tema.arkaPlan }),
        "--alt-renk": kart,
        "--kart-kose": `${tema.kose + 8}px`,
        "--kart-bosluk": "12px",
        "--kart-golge":
            "0 12px 32px -12px color-mix(in srgb, #000000 40%, transparent)",
    };
}

/**
 * G3: --zemin sayfanın arka planı, --alt-renk alta yapışık düğme çubuğunun
 * solduğu renk (geçişte alt renk, görselde karartma rengi).
 */
function zeminDegiskenleri(
    tema: Tema,
    zemin: Zemin,
    medyaKoku: string,
): Record<string, string> {
    if (zemin.tur === "gecis")
        return {
            "--zemin": `linear-gradient(180deg, ${tema.arkaPlan}, ${zemin.ikinciRenk})`,
            "--alt-renk": zemin.ikinciRenk,
        };
    const katman = `color-mix(in srgb, ${tema.arkaPlan} ${zemin.karartma}%, transparent)`;
    return {
        "--zemin": `linear-gradient(${katman}, ${katman}), url("${medyaKoku}/medya/${zemin.gorsel}.webp") center / cover no-repeat`,
        "--alt-renk": tema.arkaPlan,
    };
}
