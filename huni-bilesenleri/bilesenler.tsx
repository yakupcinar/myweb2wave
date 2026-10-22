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
    secenekler,
    secimTuru = "tekli",
    secili = [],
    devamDugmesi = "Devam",
    medyaKoku,
    onSecim,
    onSecenekDegistir,
    onDevam,
}: {
    soru: string;
    secenekler: ({ metin: string } & Omit<GorselAlani, "medyaKoku">)[];
    secimTuru?: "tekli" | "coklu" | undefined;
    secili?: number[] | undefined;
    devamDugmesi?: string | undefined;
    medyaKoku: string;
    /** Seçeneğin sırası; kimliği çağıran bilir. */
    onSecim?: ((sira: number) => void) | undefined;
    onSecenekDegistir?: ((sira: number) => void) | undefined;
    onDevam?: (() => void) | undefined;
}): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{soru}</h1>
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
            {secimTuru === "coklu" ? (
                <button
                    type="button"
                    className="ana"
                    disabled={secili.length === 0}
                    onClick={onDevam}
                >
                    {devamDugmesi}
                </button>
            ) : null}
        </section>
    );
}

export function Paywall({
    baslik,
    metin,
    dugme,
    onDugme,
    kapali = false,
    hata = "",
}: {
    baslik: string;
    metin: string;
    dugme: string;
    /** Önizlemede ve ödeme isteği sürerken (çift tıklama yok, D13). */
    kapali?: boolean;
    hata?: string;
} & Tiklama): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{baslik}</h1>
            <p>{metin}</p>
            <button
                type="button"
                className="ana"
                onClick={onDugme}
                disabled={kapali}
            >
                {dugme}
            </button>
            {hata ? <p role="alert">{hata}</p> : null}
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
          secimTuru?: "tekli" | "coklu";
          devamDugmesi?: string;
          secenekler: { metin: string; gorsel?: string }[];
      }
    | { tur: "paywall"; baslik: string; metin: string; dugme: string };

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
    odeme = {},
}: {
    adim: TanimAdimi;
    medyaKoku: string;
    onIleri?: () => void;
    onSecim?: (sira: number) => void;
    secili?: number[] | undefined;
    onSecenekDegistir?: ((sira: number) => void) | undefined;
    onCokluDevam?: (() => void) | undefined;
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
                    secenekler={adim.secenekler}
                    secimTuru={adim.secimTuru}
                    devamDugmesi={adim.devamDugmesi}
                    secili={secili}
                    medyaKoku={medyaKoku}
                    onSecim={onSecim}
                    onSecenekDegistir={onSecenekDegistir}
                    onDevam={onCokluDevam}
                />
            );
        case "paywall":
            return (
                <Paywall
                    baslik={adim.baslik}
                    metin={adim.metin}
                    dugme={adim.dugme}
                    {...odeme}
                />
            );
    }
}
