// Huni adımları (B4/H1) — Puck önizlemesi bugün, huni oynatıcısı H2'de bu
// bileşenleri çizer. DOM huni sayfasındakiyle aynı (HuniKabugu.astro):
// huni.css aynı sınıfları her iki yerde boyar. Metinler yalnız JSX metni —
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

export function Tanitim({
    baslik,
    metin,
    dugme,
    gorsel,
    medyaKoku,
}: {
    baslik: string;
    metin: string;
    dugme: string;
} & GorselAlani): ReactNode {
    return (
        <section>
            {gorsel ? (
                <Gorsel id={gorsel} medyaKoku={medyaKoku} sinif="kapak" />
            ) : null}
            <h1 tabIndex={-1}>{baslik}</h1>
            <p>{metin}</p>
            <button type="button" className="ana">
                {dugme}
            </button>
        </section>
    );
}

export function Soru({
    soru,
    secenekler,
    medyaKoku,
}: {
    soru: string;
    secenekler: ({ metin: string } & Omit<GorselAlani, "medyaKoku">)[];
    medyaKoku: string;
}): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{soru}</h1>
            <div className="secenekler">
                {secenekler.map((s, i) => (
                    <button key={i} type="button">
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
}: {
    baslik: string;
    metin: string;
    dugme: string;
}): ReactNode {
    return (
        <section>
            <h1 tabIndex={-1}>{baslik}</h1>
            <p>{metin}</p>
            <button type="button" className="ana">
                {dugme}
            </button>
        </section>
    );
}
