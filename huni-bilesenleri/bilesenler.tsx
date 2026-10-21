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
type Tiklama = { onDugme?: () => void };

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
    medyaKoku,
    onSecim,
}: {
    soru: string;
    secenekler: ({ metin: string } & Omit<GorselAlani, "medyaKoku">)[];
    medyaKoku: string;
    /** Seçeneğin sırası; kimliği çağıran bilir. */
    onSecim?: (sira: number) => void;
}): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{soru}</h1>
            <div className="secenekler">
                {secenekler.map((s, i) => (
                    <button
                        key={i}
                        type="button"
                        onClick={onSecim ? () => onSecim(i) : undefined}
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
