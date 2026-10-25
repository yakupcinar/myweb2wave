// Huni adımları (B4/H1) — Puck önizlemesi ve huni oynatıcısı (H2,
// funnel/src/oynatici) aynı bileşenleri çizer; huni.css ikisini de boyar.
// Etkileşim alanları (on…, kapali, hata) isteğe bağlı: Puck geçmez. Metinler yalnız JSX metni —
// tanım HTML taşıyamaz (D1); ham HTML basan React özniteliği bin/kontrol'de
// yasak (html-yok adımı; adı burada yazılsa adım kendini yakalar).
import type { ReactNode } from "react";

/** E2.3: görsel çekirdekten, kimlikten kurulur (D2); kök huni sayfasında çekirdeğin adresi, panelde "". */
function Gorsel({
    id,
    medyaKoku,
    sinif,
}: {
    id: string;
    medyaKoku: string;
    sinif: string;
}): ReactNode {
    return (
        <img
            className={sinif}
            src={`${medyaKoku}/medya/${id}.webp`}
            alt=""
            decoding="async"
        />
    );
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

// Boş dize = görsel yok (Puck alanı boş değer taşır; tanımda alan hiç olmaz).
type GorselAlani = { gorsel?: string | undefined; medyaKoku: string };
type Tiklama = { onDugme?: (() => void) | undefined };

export function Tanitim({
    baslik,
    metin,
    dugme,
    gorsel,
    medyaKoku,
    onDugme,
}: {
    baslik: string;
    metin: string;
    dugme: string;
} & GorselAlani &
    Tiklama): ReactNode {
    return (
        <section>
            {gorsel ? (
                <Gorsel id={gorsel} medyaKoku={medyaKoku} sinif="kapak" />
            ) : null}
            <h1 tabIndex={-1}>{baslik}</h1>
            <p>{metin}</p>
            <button type="button" className="ana" onClick={onDugme}>
                {dugme}
            </button>
        </section>
    );
}

export function Soru({
    soru,
    aciklama = "",
    secenekler = [],
    secimTuru = "tekli",
    yanitZorunlu = false,
    cevapMetni = "",
    secili = [],
    devamDugmesi = "Devam",
    medyaKoku,
    onSecim,
    onSecenekDegistir,
    onMetinDegistir,
    onDevam,
}: {
    soru: string;
    aciklama?: string | undefined;
    secenekler?:
        ({ metin: string } & Omit<GorselAlani, "medyaKoku">)[] | undefined;
    secimTuru?: "tekli" | "coklu" | "metin" | undefined;
    yanitZorunlu?: boolean | undefined;
    cevapMetni?: string | undefined;
    secili?: number[] | undefined;
    devamDugmesi?: string | undefined;
    medyaKoku: string;
    /** Seçeneğin sırası; kimliği çağıran bilir. */
    onSecim?: ((sira: number) => void) | undefined;
    onSecenekDegistir?: ((sira: number) => void) | undefined;
    onMetinDegistir?: ((metin: string) => void) | undefined;
    onDevam?: (() => void) | undefined;
}): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{soru}</h1>
            {aciklama ? <p className="yardim-metni">{aciklama}</p> : null}
            {secimTuru === "metin" ? (
                <label className="serbest-yanit">
                    Yanıtınız{yanitZorunlu ? "" : " (isteğe bağlı)"}
                    <textarea
                        aria-label="Yanıtınız"
                        required={yanitZorunlu}
                        maxLength={500}
                        rows={4}
                        value={cevapMetni}
                        readOnly={!onMetinDegistir}
                        onChange={(e) =>
                            onMetinDegistir?.(e.currentTarget.value)
                        }
                    />
                </label>
            ) : (
                <div className="secenekler">
                    {secenekler.map((s, i) => (
                        <button
                            key={i}
                            type="button"
                            aria-pressed={
                                secimTuru === "coklu"
                                    ? secili.includes(i)
                                    : undefined
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
                                />
                            ) : null}
                            <span>{s.metin}</span>
                        </button>
                    ))}
                </div>
            )}
            {secimTuru === "coklu" || secimTuru === "metin" ? (
                <button
                    type="button"
                    className="ana"
                    disabled={
                        (secimTuru === "coklu" && secili.length === 0) ||
                        (secimTuru === "metin" &&
                            yanitZorunlu &&
                            !cevapMetni.trim())
                    }
                    onClick={onDevam}
                >
                    {devamDugmesi}
                </button>
            ) : null}
        </section>
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
            <button
                type="button"
                className="ana"
                onClick={onDugme}
                disabled={kapali}
            >
                {dugme}
            </button>
            {hata ? <p role="alert">{hata}</p> : null}
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

export function Yorum({
    baslik,
    metin,
    yazar,
    unvan,
    dugme,
    onDugme,
}: {
    baslik: string;
    metin: string;
    yazar: string;
    unvan: string;
    dugme: string;
} & Tiklama): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{baslik}</h1>
            <blockquote className="yorum-alinti">
                <p>{metin}</p>
                <footer>
                    <strong>{yazar}</strong>
                    {unvan ? ` · ${unvan}` : null}
                </footer>
            </blockquote>
            <button type="button" className="ana" onClick={onDugme}>
                {dugme}
            </button>
        </section>
    );
}

/** Tanımdaki adım (sözleşme HuniTanimi.adimlar[]); fazla alanlar (id, plan) yok sayılır. */
export type TanimAdimi =
    | {
          tur: "tanitim";
          baslik: string;
          metin: string;
          dugme: string;
          gorsel?: string;
      }
    | {
          tur: "soru";
          soru: string;
          aciklama?: string;
          secimTuru?: "tekli" | "coklu" | "metin";
          yanitZorunlu?: boolean;
          devamDugmesi?: string;
          secenekler?: { metin: string; gorsel?: string }[];
      }
    | {
          tur: "yorum";
          baslik: string;
          metin: string;
          yazar: string;
          unvan: string;
          dugme: string;
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
 * aynı bileşenleri ayrı yoldan çağırır; core/editor/src/onizleme.test.tsx
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
    odeme?: { onDugme?: () => void; kapali?: boolean; hata?: string };
}): ReactNode {
    switch (adim.tur) {
        case "tanitim":
            return (
                <Tanitim
                    baslik={adim.baslik}
                    metin={adim.metin}
                    dugme={adim.dugme}
                    gorsel={adim.gorsel}
                    medyaKoku={medyaKoku}
                    onDugme={onIleri}
                />
            );
        case "soru":
            return (
                <Soru
                    soru={adim.soru}
                    aciklama={adim.aciklama}
                    secenekler={adim.secenekler}
                    secimTuru={adim.secimTuru}
                    yanitZorunlu={adim.yanitZorunlu}
                    cevapMetni={cevapMetni}
                    devamDugmesi={adim.devamDugmesi}
                    secili={secili}
                    medyaKoku={medyaKoku}
                    onSecim={onSecim}
                    onSecenekDegistir={onSecenekDegistir}
                    onDevam={onCokluDevam}
                    onMetinDegistir={onMetinDegistir}
                />
            );
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
        case "yorum":
            return (
                <Yorum
                    baslik={adim.baslik}
                    metin={adim.metin}
                    yazar={adim.yazar}
                    unvan={adim.unvan}
                    dugme={adim.dugme}
                    onDugme={onIleri}
                />
            );
    }
}
