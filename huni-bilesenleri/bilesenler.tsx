// Huni adımları (B4/H1; T1c: içerik adımı ekran — tanıtım, soru, yorum yok) — Puck önizlemesi ve huni oynatıcısı (H2,
// funnel/src/oynatici) aynı bileşenleri çizer; huni.css ikisini de boyar.
// Etkileşim alanları (on…, kapali, hata) isteğe bağlı: Puck geçmez. Metinler yalnız JSX metni —
// tanım HTML taşıyamaz (D1); ham HTML basan React özniteliği bin/kontrol'de
// yasak (html-yok adımı; adı burada yazılsa adım kendini yakalar).
import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type CSSProperties,
    type ReactNode,
} from "react";
import { sayiParcalari, sayiYaz } from "./sayac";
import { simgeKodu } from "./simge";

/** E2.3: görsel çekirdekten, kimlikten kurulur (D2); kök huni sayfasında çekirdeğin adresi, panelde "". */
function Gorsel({
    id,
    medyaKoku,
    sinif,
    alt = "",
}: {
    id: string;
    medyaKoku: string;
    sinif: string;
    /** Ç44: boş = dekoratif. */
    alt?: string | undefined;
}): ReactNode {
    return (
        <img
            className={sinif}
            src={`${medyaKoku}/medya/${id}.webp`}
            alt={alt}
            decoding="async"
        />
    );
}

/**
 * V3a: emoji simgelerinin biçimi (tema `simgeler`) ve görsellerin kökü —
 * oynatıcı ve Puck kökü sağlar; yoksa cihaz emojisi (bugünkü davranış).
 */
export const SimgeBaglami = createContext<{
    stil: "cihaz" | "renkli" | "tekrenk";
    kok: string;
}>({ stil: "cihaz", kok: "" });

/**
 * Süs: anlam metinde, ekran okuyucu okumaz. Fluent görseli yoksa (bayrak,
 * ağ hatası) cihaz emojisine döner. Tek renk maske hata olayı vermez — aynı
 * adresi gizli img yoklar (tarayıcı önbelleğinden, ek istek yok).
 */
function Simge({ simge }: { simge: string }): ReactNode {
    const { stil, kok } = useContext(SimgeBaglami);
    const [yok, setYok] = useState(false);
    if (stil === "cihaz" || yok)
        return (
            <span className="simge" aria-hidden="true">
                {simge}
            </span>
        );
    const kod = simgeKodu(simge);
    if (stil === "renkli")
        return (
            <span className="simge" aria-hidden="true">
                <img
                    src={`${kok}/simge/renkli/${kod}.webp`}
                    alt=""
                    decoding="async"
                    onError={() => setYok(true)}
                />
            </span>
        );
    const adres = `${kok}/simge/tekrenk/${kod}.svg`;
    return (
        <span
            className="simge simge-tekrenk"
            aria-hidden="true"
            style={{ "--simge": `url("${adres}")` } as CSSProperties}
        >
            <img src={adres} alt="" hidden onError={() => setYok(true)} />
        </span>
    );
}

/** V4: temanın hareket düzeyi — CSS kökteki data-hareket'i okur, bu bağlam yalnız sayan istatistik için. */
export const HareketBaglami = createContext<"yok" | "sakin" | "canli">("sakin");

/** V4: sayaç bu ortamda çalışacak mı (canlı, hareket serbest, gözlemci var). */
function sayacCalisir(hareket: "yok" | "sakin" | "canli"): boolean {
    return (
        hareket === "canli" &&
        typeof IntersectionObserver !== "undefined" &&
        typeof matchMedia !== "undefined" &&
        !matchMedia("(prefers-reduced-motion: reduce)").matches
    );
}

/**
 * V4: canlı harekette istatistik sayısı ekrana girince sıfırdan sayar.
 * Sayacak ortamda ilk çizim sıfırdan başlar (son değer önce görünüp geri
 * sıçrıyordu — tarayıcıda ölçüldü: "3 dk" → "1 dk"); sayılmayacaksa
 * (statik çizim, ekran görüntüsü, hareketsiz ziyaretçi) son değer.
 */
function Sayac({ deger }: { deger: string }): ReactNode {
    const hareket = useContext(HareketBaglami);
    const oge = useRef<HTMLElement>(null);
    const ilk = (): string => {
        const p = sayiParcalari(deger);
        return p && sayacCalisir(hareket) ? sayiYaz(p, 0) : deger;
    };
    const [metin, setMetin] = useState(ilk);
    useEffect(() => {
        setMetin(ilk());
        const p = sayiParcalari(deger);
        const el = oge.current;
        if (!p || !el || !sayacCalisir(hareket)) return;
        let kare = 0;
        const gozcu = new IntersectionObserver((girdiler) => {
            if (!girdiler.some((g) => g.isIntersecting)) return;
            gozcu.disconnect();
            const bas = performance.now();
            const adim = (simdi: number): void => {
                const t = Math.min(1, (simdi - bas) / 900);
                setMetin(sayiYaz(p, p.sayi * (1 - (1 - t) ** 3)));
                if (t < 1) kare = requestAnimationFrame(adim);
            };
            kare = requestAnimationFrame(adim);
        });
        gozcu.observe(el);
        return () => {
            gozcu.disconnect();
            cancelAnimationFrame(kare);
        };
    }, [deger, hareket]);
    return <strong ref={oge}>{metin}</strong>;
}

/** Ç61 (5): sayfanın başındaki logo; başlık ürünü zaten adlandırır → alt boş. */
export function Logo({
    gorsel,
    medyaKoku,
}: {
    gorsel: string;
    medyaKoku: string;
}): ReactNode {
    return <Gorsel id={gorsel} medyaKoku={medyaKoku} sinif="logo" />;
}

/** Adımın ana düğmesi (ve ödeme hatası): huni oynatıcısında telefonda alta yapışır. */
function Eylem({ children }: { children: ReactNode }): ReactNode {
    return <div className="eylem">{children}</div>;
}

// Boş dize = görsel yok (Puck alanı boş değer taşır; tanımda alan hiç olmaz).
type GorselAlani = {
    gorsel?: string | undefined;
    gorselMetni?: string | undefined;
    medyaKoku: string;
};
type Tiklama = { onDugme?: (() => void) | undefined };

/** Seçenek kimliği çağıranda; bileşen sırayla konuşur. */
export type YanitEtkilesimi = {
    secili?: number[] | undefined;
    cevapMetni?: string | undefined;
    /** Seçeneğin sırası; kimliği çağıran bilir. */
    onSecim?: ((sira: number) => void) | undefined;
    onSecenekDegistir?: ((sira: number) => void) | undefined;
    onMetinDegistir?: ((metin: string) => void) | undefined;
    /** E1: e-posta sorusunun pazarlama izni kutusu. */
    izinVar?: boolean | undefined;
    onIzinDegistir?: ((izin: boolean) => void) | undefined;
};
type SecimTuru = "tekli" | "coklu" | "metin" | "eposta" | "olcek";

/** V5: ölçek sorusunun cevap kimlikleri — çekirdekte Tanim::OLCEK_KIMLIKLERI. */
export const OLCEK = ["1", "2", "3", "4", "5"] as const;

/**
 * E1: düğmeyi açan biçim denetimi — çekirdek ayrıca FILTER_VALIDATE_EMAIL
 * ile denetler; bu yalnız apaçık yazım hatasını (boşluk, @'siz) yakalar.
 */
export function epostaGecerli(eposta: string): boolean {
    return (
        eposta.length <= 254 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(eposta.trim())
    );
}
type SoruSecenegi = { metin: string; simge?: string | undefined } & Omit<
    GorselAlani,
    "medyaKoku"
>;

/** Soru adımı ve T1b soru bloğu: seçenekler ya da serbest yanıt alanı. */
function Yanit({
    secenekler = [],
    secimTuru = "tekli",
    yanitZorunlu = false,
    izinMetni,
    olcekSol,
    olcekSag,
    cevapMetni = "",
    secili = [],
    duzen = "liste",
    medyaKoku,
    onSecim,
    onSecenekDegistir,
    onMetinDegistir,
    izinVar = false,
    onIzinDegistir,
}: {
    secenekler?: SoruSecenegi[] | undefined;
    secimTuru?: SecimTuru | undefined;
    yanitZorunlu?: boolean | undefined;
    izinMetni?: string | undefined;
    olcekSol?: string | undefined;
    olcekSag?: string | undefined;
    duzen?: "liste" | "izgara" | undefined;
    medyaKoku: string;
} & YanitEtkilesimi): ReactNode {
    // V5: 1–5 katılma ölçeği — tek dokunuş geçer (tekli gibi); uç etiketleri altta.
    if (secimTuru === "olcek")
        return (
            <div className="olcek">
                <div className="olcek-dugmeler">
                    {OLCEK.map((n, i) => (
                        <button
                            key={n}
                            type="button"
                            aria-label={
                                i === 0 && olcekSol
                                    ? `${n} — ${olcekSol}`
                                    : i === OLCEK.length - 1 && olcekSag
                                      ? `${n} — ${olcekSag}`
                                      : n
                            }
                            onClick={onSecim ? () => onSecim(i) : undefined}
                        >
                            {n}
                        </button>
                    ))}
                </div>
                <div className="olcek-etiketler" aria-hidden="true">
                    <span>{olcekSol}</span>
                    <span>{olcekSag}</span>
                </div>
            </div>
        );
    if (secimTuru === "eposta")
        return (
            <>
                <label className="serbest-yanit">
                    E-posta adresiniz{yanitZorunlu ? "" : " (isteğe bağlı)"}
                    <input
                        type="email"
                        aria-label="E-posta adresiniz"
                        autoComplete="email"
                        inputMode="email"
                        required={yanitZorunlu}
                        maxLength={254}
                        value={cevapMetni}
                        readOnly={!onMetinDegistir}
                        onChange={(e) =>
                            onMetinDegistir?.(e.currentTarget.value)
                        }
                    />
                </label>
                {izinMetni ? (
                    <label className="eposta-izni">
                        <input
                            type="checkbox"
                            checked={izinVar}
                            readOnly={!onIzinDegistir}
                            onChange={(e) =>
                                onIzinDegistir?.(e.currentTarget.checked)
                            }
                        />
                        <span>{izinMetni}</span>
                    </label>
                ) : null}
            </>
        );
    return secimTuru === "metin" ? (
        <label className="serbest-yanit">
            Yanıtınız{yanitZorunlu ? "" : " (isteğe bağlı)"}
            <textarea
                aria-label="Yanıtınız"
                required={yanitZorunlu}
                maxLength={500}
                rows={4}
                value={cevapMetni}
                readOnly={!onMetinDegistir}
                onChange={(e) => onMetinDegistir?.(e.currentTarget.value)}
            />
        </label>
    ) : (
        <div
            className="secenekler"
            data-tur={secimTuru}
            data-duzen={duzen === "liste" ? undefined : duzen}
        >
            {secenekler.map((s, i) => (
                <button
                    key={i}
                    type="button"
                    aria-pressed={
                        secimTuru === "coklu" ? secili.includes(i) : undefined
                    }
                    onClick={
                        secimTuru === "coklu"
                            ? onSecenekDegistir
                                ? () => onSecenekDegistir(i)
                                : undefined
                            : onSecim
                              ? () => onSecim(i)
                              : undefined
                    }
                >
                    {s.gorsel ? (
                        <Gorsel
                            id={s.gorsel}
                            medyaKoku={medyaKoku}
                            sinif="secenek-gorseli"
                            alt={s.gorselMetni}
                        />
                    ) : null}
                    {s.simge ? <Simge simge={s.simge} /> : null}
                    <span>{s.metin}</span>
                    <span className="isaret" aria-hidden="true" />
                </button>
            ))}
        </div>
    );
}

/** Ç61 (3): yasal bağlantı adları sabit; adresi çekirdek yalnız https ise kabul eder. */
const YASAL = [
    ["sartlar", "Kullanım şartları"],
    ["gizlilik", "Gizlilik politikası"],
    ["iptal", "İptal ve iade"],
] as const;

export function Paywall({
    baslik,
    metin,
    dugme,
    fiyat,
    faydalar = [],
    sartlar,
    gizlilik,
    iptal,
    onDugme,
    kapali = false,
    hata = "",
}: {
    baslik: string;
    metin: string;
    dugme: string;
    /** Ç19/Ç21: biçimlenmiş Stripe fiyatı ("€9,99 / ay"); yoksa satır yok. */
    fiyat?: string | undefined;
    faydalar?: string[] | undefined;
    sartlar?: string | undefined;
    gizlilik?: string | undefined;
    iptal?: string | undefined;
    /** Önizlemede ve ödeme isteği sürerken (çift tıklama yok, D13). */
    kapali?: boolean;
    hata?: string;
} & Tiklama): ReactNode {
    const adresler = { sartlar, gizlilik, iptal };
    const baglantilar = YASAL.filter(([alan]) => adresler[alan]);
    return (
        <section>
            <h1 tabIndex={-1}>{baslik}</h1>
            <p>{metin}</p>
            {faydalar.length ? (
                <ul className="faydalar">
                    {faydalar.map((f, i) => (
                        <li key={i}>{f}</li>
                    ))}
                </ul>
            ) : null}
            {fiyat ? (
                // Tek plan: kart seçili görünür (ödeme adımındaki plan bu).
                <div className="plan-karti">
                    <span className="isaret" aria-hidden="true" />
                    <p className="fiyat">{fiyat}</p>
                </div>
            ) : null}
            <Eylem>
                {hata ? <p role="alert">{hata}</p> : null}
                <button
                    type="button"
                    className="ana"
                    onClick={onDugme}
                    disabled={kapali}
                >
                    {dugme}
                </button>
            </Eylem>
            {baglantilar.length ? (
                <p className="yasal">
                    {baglantilar.map(([alan, ad], i) => (
                        <span key={alan}>
                            {i ? " · " : null}
                            <a
                                href={adresler[alan]}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {ad}
                            </a>
                        </span>
                    ))}
                </p>
            ) : null}
        </section>
    );
}

/**
 * Mobil görünüm 4: "planın hazırlanıyor" — düğmesiz; çubuk süre boyunca
 * dolar, maddeler sırayla belirir (CSS). Sonraki adıma geçişi oynatıcı
 * yapar (zamanlayıcı); Puck önizlemesi beklemez.
 */
export function Yukleme({
    baslik,
    maddeler = [],
    sure = 4,
}: {
    baslik: string;
    maddeler?: string[] | undefined;
    sure?: number | undefined;
}): ReactNode {
    return (
        <section
            className="yukleme"
            aria-busy="true"
            style={
                {
                    "--sure": `${sure}s`,
                    "--n": maddeler.length,
                } as CSSProperties
            }
        >
            <h1 tabIndex={-1}>{baslik}</h1>
            <div className="yukleme-cubuk" aria-hidden="true">
                <span />
            </div>
            {maddeler.length ? (
                <ul className="yukleme-maddeler">
                    {maddeler.map((m, i) => (
                        <li key={i} style={{ "--i": i } as CSSProperties}>
                            {m}
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}

/** T1: blok kataloğu (sözleşme EkranBlogu); her blok düz metin. */
export type EkranBlogu =
    | { tur: "baslik"; metin: string }
    | { tur: "metin"; metin: string }
    | { tur: "gorsel"; gorsel: string; gorselMetni?: string | undefined }
    | { tur: "maddeler"; maddeler: string[] }
    | {
          tur: "yorum";
          metin: string;
          yazar: string;
          unvan?: string | undefined;
          puan?: number | undefined;
      }
    | { tur: "istatistik"; deger: string; metin: string }
    | { tur: "kutu"; metin: string; simge?: string | undefined }
    | {
          tur: "grafik";
          baslangic: string;
          hedef: string;
          egri?: "artan" | "azalan" | undefined;
      }
    | {
          tur: "onceSonra";
          once: string;
          sonra: string;
          onceEtiketi: string;
          sonraEtiketi: string;
      }
    | {
          tur: "soru";
          secimTuru?: SecimTuru | undefined;
          duzen?: "liste" | "izgara" | undefined;
          yanitZorunlu?: boolean | undefined;
          izinMetni?: string | undefined;
          olcekSol?: string | undefined;
          olcekSag?: string | undefined;
          secenekler?: SoruSecenegi[] | undefined;
      };

/** Puck her bloğu ayrı bileşen olarak çizer; huni ekranın içinde. */
export function Blok({
    blok,
    medyaKoku,
    yanit = {},
}: {
    blok: EkranBlogu;
    medyaKoku: string;
    /** T1b: soru bloğunun durumu ve olayları (oynatıcıdan; Puck geçmez). */
    yanit?: YanitEtkilesimi | undefined;
}): ReactNode {
    switch (blok.tur) {
        case "soru":
            return (
                <Yanit
                    secenekler={blok.secenekler}
                    secimTuru={blok.secimTuru}
                    yanitZorunlu={blok.yanitZorunlu}
                    izinMetni={blok.izinMetni}
                    olcekSol={blok.olcekSol}
                    olcekSag={blok.olcekSag}
                    duzen={blok.duzen}
                    medyaKoku={medyaKoku}
                    {...yanit}
                />
            );
        case "baslik":
            // Ara başlık: sayfanın h1'i ekranın kendi başlığı.
            return <h2>{blok.metin}</h2>;
        case "metin":
            return <p>{blok.metin}</p>;
        case "gorsel":
            return (
                <Gorsel
                    id={blok.gorsel}
                    medyaKoku={medyaKoku}
                    sinif="blok-gorseli"
                    alt={blok.gorselMetni}
                />
            );
        case "maddeler":
            return (
                <ul className="maddeler">
                    {blok.maddeler.map((m, i) => (
                        <li key={i}>{m}</li>
                    ))}
                </ul>
            );
        case "yorum":
            return (
                <figure className="blok-yorum">
                    {blok.puan ? (
                        // Yıldızlar görsel; anlamı etiket söyler.
                        <p
                            className="yildizlar"
                            role="img"
                            aria-label={`5 üzerinden ${blok.puan} puan`}
                        >
                            {"★".repeat(blok.puan) + "☆".repeat(5 - blok.puan)}
                        </p>
                    ) : null}
                    <blockquote>
                        <p>{blok.metin}</p>
                    </blockquote>
                    <figcaption>
                        <strong>{blok.yazar}</strong>
                        {blok.unvan ? ` · ${blok.unvan}` : null}
                    </figcaption>
                </figure>
            );
        case "istatistik":
            return (
                <p className="istatistik">
                    <Sayac deger={blok.deger} /> <span>{blok.metin}</span>
                </p>
            );
        case "kutu":
            return (
                <p className="kutu">
                    {blok.simge ? <Simge simge={blok.simge} /> : null}
                    <span>{blok.metin}</span>
                </p>
            );
        case "grafik":
            return (
                <Grafik
                    baslangic={blok.baslangic}
                    hedef={blok.hedef}
                    egri={blok.egri ?? "artan"}
                />
            );
        case "onceSonra":
            return (
                <div className="once-sonra">
                    {(
                        [
                            [blok.once, blok.onceEtiketi],
                            [blok.sonra, blok.sonraEtiketi],
                        ] as const
                    ).map(([gorsel, etiket], i) => (
                        <figure key={i}>
                            <Gorsel
                                id={gorsel}
                                medyaKoku={medyaKoku}
                                sinif="once-sonra-gorseli"
                                alt={etiket}
                            />
                            <figcaption>{etiket}</figcaption>
                        </figure>
                    ))}
                </div>
            );
    }
}

/**
 * V5: projeksiyon eğrisi — ana renkte, sayısız (uydurma sonuç yazılamaz);
 * etiketler HTML (ölçeklenen metin, ekran okuyucu). Canlı harekette eğri
 * çizilerek gelir (huni.css).
 */
function Grafik({
    baslangic,
    hedef,
    egri,
}: {
    baslangic: string;
    hedef: string;
    egri: "artan" | "azalan";
}): ReactNode {
    const [y0, y1] = egri === "artan" ? [112, 28] : [28, 112];
    const yol = `M24 ${y0} C124 ${y0} 176 ${y1} 276 ${y1}`;
    return (
        <figure
            className="grafik"
            role="img"
            aria-label={`${baslangic} → ${hedef}`}
        >
            <svg viewBox="0 0 300 140" aria-hidden="true">
                <path className="grafik-alan" d={`${yol} L276 132 L24 132 Z`} />
                <line
                    className="grafik-taban"
                    x1="24"
                    y1="132"
                    x2="276"
                    y2="132"
                />
                <path className="grafik-egri" d={yol} pathLength={1} />
                <circle className="grafik-bas" cx="24" cy={y0} r="6" />
                <circle className="grafik-hedef" cx="276" cy={y1} r="9" />
            </svg>
            <figcaption>
                <span>{baslangic}</span>
                <span>{hedef}</span>
            </figcaption>
        </figure>
    );
}

/**
 * T1: ekran = başlık + katalogdaki bloklar yukarıdan aşağı + altta tek ana
 * düğme. yuva: Puck blokları kendi bölgesinde çizer — yuvası da
 * div.bloklar (ölçüldü), iki yolda DOM aynı.
 */
export function Ekran({
    baslik,
    gorsel,
    gorselMetni,
    duzen = "ust",
    bloklar = [],
    dugme,
    medyaKoku,
    onDugme,
    yuva,
    yanit,
    kapali = false,
}: {
    baslik: string;
    /** T1c: kapak ve yeri — tanıtımla aynı öğe ve sınıf (huni.css G5). */
    gorsel?: string | undefined;
    gorselMetni?: string | undefined;
    duzen?: "ust" | "kahraman" | "tamEkran" | undefined;
    bloklar?: EkranBlogu[] | undefined;
    /** T1b: tek seçimli soru bloğunda yok — seçenek geçirir. */
    dugme?: string | undefined;
    medyaKoku: string;
    yuva?: ReactNode;
    yanit?: YanitEtkilesimi | undefined;
    /** T1b: çoklu seçimde seçim yokken / zorunlu yanıt boşken. */
    kapali?: boolean;
} & Tiklama): ReactNode {
    return (
        <section
            className="ekran"
            data-duzen={duzen === "ust" ? undefined : duzen}
        >
            {gorsel ? (
                <Gorsel
                    id={gorsel}
                    medyaKoku={medyaKoku}
                    sinif="kapak"
                    alt={gorselMetni}
                />
            ) : null}
            <h1 tabIndex={-1}>{baslik}</h1>
            {yuva ?? (
                <div className="bloklar">
                    {bloklar.map((b, i) => (
                        <Blok
                            key={i}
                            blok={b}
                            medyaKoku={medyaKoku}
                            yanit={yanit}
                        />
                    ))}
                </div>
            )}
            {dugme ? (
                <Eylem>
                    <button
                        type="button"
                        className="ana"
                        disabled={kapali}
                        onClick={onDugme}
                    >
                        {dugme}
                    </button>
                </Eylem>
            ) : null}
        </section>
    );
}

/** Tanımdaki adım (sözleşme HuniTanimi.adimlar[]); fazla alanlar (id, plan) yok sayılır. */
export type TanimAdimi =
    | {
          tur: "yukleme";
          baslik: string;
          maddeler?: string[];
          sure?: number;
      }
    | {
          tur: "ekran";
          baslik: string;
          gorsel?: string;
          gorselMetni?: string;
          duzen?: "ust" | "kahraman" | "tamEkran";
          bloklar: EkranBlogu[];
          dugme?: string;
      }
    | {
          tur: "paywall";
          baslik: string;
          metin: string;
          dugme: string;
          faydalar?: string[];
          sartlar?: string;
          gizlilik?: string;
          iptal?: string;
      };

/**
 * Tanımdan adım (B4/H3): huni oynatıcısı bunu çizer. Puck kendi alanlarından
 * aynı bileşenleri ayrı yoldan çağırır; core/panel/src/onizleme.test.tsx
 * iki yolun aynı DOM'u verdiğini ölçer (önizleme = huni).
 */
export function Adim({
    adim,
    medyaKoku,
    onIleri,
    onSecim,
    secili,
    onSecenekDegistir,
    onCokluDevam,
    cevapMetni,
    onMetinDegistir,
    izinVar,
    onIzinDegistir,
    odeme = {},
}: {
    adim: TanimAdimi;
    medyaKoku: string;
    onIleri?: () => void;
    onSecim?: (sira: number) => void;
    secili?: number[] | undefined;
    onSecenekDegistir?: ((sira: number) => void) | undefined;
    onCokluDevam?: (() => void) | undefined;
    cevapMetni?: string | undefined;
    onMetinDegistir?: ((metin: string) => void) | undefined;
    izinVar?: boolean | undefined;
    onIzinDegistir?: ((izin: boolean) => void) | undefined;
    odeme?: {
        onDugme?: () => void;
        kapali?: boolean;
        hata?: string;
        /** Ç19/Ç21: biçimlenmiş Stripe fiyatı. */
        fiyat?: string | undefined;
    };
}): ReactNode {
    switch (adim.tur) {
        case "paywall":
            return (
                <Paywall
                    baslik={adim.baslik}
                    metin={adim.metin}
                    dugme={adim.dugme}
                    faydalar={adim.faydalar}
                    sartlar={adim.sartlar}
                    gizlilik={adim.gizlilik}
                    iptal={adim.iptal}
                    {...odeme}
                />
            );
        case "yukleme":
            return (
                <Yukleme
                    baslik={adim.baslik}
                    maddeler={adim.maddeler}
                    sure={adim.sure}
                />
            );
        case "ekran": {
            // T1b: çoklu/serbest yanıtlı soru bloğunda düğme gönderir.
            const soru = adim.bloklar.find((b) => b.tur === "soru");
            const tur = soru ? (soru.secimTuru ?? "tekli") : null;
            return (
                <Ekran
                    baslik={adim.baslik}
                    gorsel={adim.gorsel}
                    gorselMetni={adim.gorselMetni}
                    duzen={adim.duzen}
                    bloklar={adim.bloklar}
                    dugme={adim.dugme}
                    medyaKoku={medyaKoku}
                    yanit={{
                        secili,
                        cevapMetni,
                        onSecim,
                        onSecenekDegistir,
                        onMetinDegistir,
                        izinVar,
                        onIzinDegistir,
                    }}
                    kapali={
                        (tur === "coklu" && !secili?.length) ||
                        (tur === "metin" &&
                            !!soru?.yanitZorunlu &&
                            !cevapMetni?.trim()) ||
                        // E1: zorunluysa geçerli adres; isteğe bağlıda boş
                        // geçer ama yazılmış bozuk adres geçmez.
                        (tur === "eposta" &&
                            (cevapMetni?.trim()
                                ? !epostaGecerli(cevapMetni)
                                : !!soru?.yanitZorunlu))
                    }
                    onDugme={
                        tur === "coklu" || tur === "metin" || tur === "eposta"
                            ? onCokluDevam
                            : onIleri
                    }
                />
            );
        }
    }
}
