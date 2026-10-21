// Puck yapılandırması: üç adım türü (çekirdeğin Tanim'i ile aynı) + kök
// (sayfa başlığı ve tema). Adımları huni sayfasıyla ORTAK bileşenler ve
// huni.css çizer (B4/H1); gerçek doğrulama çekirdekte — burada yalnız giriş
// kolaylığı.
import {
    FieldLabel,
    type AppState,
    type Config,
    type CustomField,
    type SelectField,
} from "@puckeditor/core";
import { useEffect, useState, type CSSProperties, type ReactNode } from "react";
import { Paywall, Soru, Tanitim } from "../../../huni-bilesenleri/bilesenler";
import "../../../huni-bilesenleri/huni.css";
import {
    kontrast,
    temaDegiskenleri,
    VARSAYILAN_TEMA,
} from "../../../huni-bilesenleri/tema";
import type { Bilesenler, KokProps, SecenekProps } from "./donustur";

export type Ortam = {
    planlar: string[];
    yaziTipleri: string[];
    medyaAdresi: string; // panel listesi + yükleme ucu
    jeton: string;
};

type Gorsel = {
    id: string;
    ozgun_ad: string;
    genislik: number;
    yukseklik: number;
};

// Puck özel alanın etiketini kendisi çizmiyor (tarayıcıda görüldü) — FieldLabel ile.
const renkAlani = (label: string): CustomField<string> => ({
    type: "custom",
    label,
    render: ({ value, onChange, id }) => (
        <FieldLabel label={label} el="div">
            <input
                id={id}
                type="color"
                value={value}
                onChange={(e) => onChange(e.target.value.toLowerCase())}
                style={{ width: "100%", height: 36 }}
            />
        </FieldLabel>
    ),
});

/** Görsel seç ya da yükle; yükleme çekirdekte doğrulanıp WebP'ye çevrilir. */
function GorselSecici({
    value,
    onChange,
    ortam,
}: {
    value: string;
    onChange: (v: string) => void;
    ortam: Ortam;
}): ReactNode {
    const [liste, setListe] = useState<Gorsel[]>([]);
    const [hata, setHata] = useState("");
    const yenile = async (): Promise<void> => {
        const y = await fetch(ortam.medyaAdresi);
        setListe(
            y.ok ? ((await y.json()) as { gorseller: Gorsel[] }).gorseller : [],
        );
    };
    useEffect(() => {
        void yenile();
    }, []);
    return (
        <div style={{ display: "grid", gap: 6 }}>
            {value ? (
                <img
                    src={`/medya/${value}.webp`}
                    alt=""
                    style={{
                        width: 64,
                        height: 64,
                        objectFit: "cover",
                        borderRadius: 6,
                    }}
                />
            ) : null}
            <select value={value} onChange={(e) => onChange(e.target.value)}>
                <option value="">— görsel yok —</option>
                {liste.map((g) => (
                    <option key={g.id} value={g.id}>
                        {g.ozgun_ad} ({g.genislik}×{g.yukseklik})
                    </option>
                ))}
            </select>
            <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={async (e) => {
                    const f = e.target.files?.[0];
                    if (!f) return;
                    const govde = new FormData();
                    govde.append("dosya", f);
                    const y = await fetch(ortam.medyaAdresi, {
                        method: "POST",
                        headers: { "X-CSRF-Token": ortam.jeton },
                        body: govde,
                    });
                    const j = (await y.json()) as {
                        id?: string;
                        hatalar?: string[];
                    };
                    if (!y.ok || !j.id) {
                        setHata((j.hatalar ?? ["yüklenemedi"]).join(" · "));
                        return;
                    }
                    setHata("");
                    await yenile();
                    onChange(j.id);
                }}
            />
            {hata ? <small style={{ color: "#cf222e" }}>{hata}</small> : null}
        </div>
    );
}

const gorselAlani = (ortam: Ortam, label: string): CustomField<string> => ({
    type: "custom",
    label,
    render: ({ value, onChange }) => (
        <FieldLabel label={label} el="div">
            <GorselSecici
                value={value ?? ""}
                onChange={onChange}
                ortam={ortam}
            />
        </FieldLabel>
    ),
});

// Düzenleyicide bütün adımlar alt alta görünür; ayırıcı yalnız burada.
const kart: CSSProperties = {
    padding: "16px 0 24px",
    borderBottom:
        "1px dashed color-mix(in srgb, var(--metin) 25%, transparent)",
};

// H5: düğme / seçenek nereye gider — yalnız SONRAKİ adımlar (çekirdek de
// yalnız ileriyi kabul eder; sıra sonradan değişirse kaydederken hata verir).
const SIRADAKI = { label: "— sıradaki adım —", value: "" };
const hedefAlani: SelectField = {
    type: "select",
    label: "Nereye gider (dallanma)",
    options: [SIRADAKI],
};
export function hedefSecenekleri(
    appState: AppState,
    bilesen: string,
): SelectField["options"] {
    const icerik = appState.data.content;
    const sira = icerik.findIndex((o) => o.props["id"] === bilesen);
    return [
        SIRADAKI,
        ...icerik.slice(sira + 1).map((o) => ({
            label: `${String(o.props["kimlik"])} (${o.type})`,
            value: String(o.props["kimlik"]),
        })),
    ];
}

export function yapilandirma(ortam: Ortam): Config<Bilesenler, KokProps> {
    const kimlikAlani = {
        type: "text" as const,
        label: "Kimlik (panelde görünür; yayından sonra değiştirmek eski veriyle eşleşmeyi koparır)",
    };
    return {
        root: {
            fields: {
                sayfaBasligi: {
                    type: "text",
                    label: "Sayfa başlığı (tarayıcı sekmesi)",
                },
                ozelTema: {
                    type: "radio",
                    label: "Bu huniye özel renk ve yazı tipi",
                    options: [
                        { label: "Hayır (varsayılan)", value: "hayir" },
                        { label: "Evet", value: "evet" },
                    ],
                },
                anaRenk: renkAlani("Ana renk (düğme)"),
                arkaPlan: renkAlani("Arka plan"),
                metinRengi: renkAlani("Metin"),
                yaziTipi: {
                    type: "select",
                    label: "Yazı tipi",
                    options: ortam.yaziTipleri.map((y) => ({
                        label: y,
                        value: y,
                    })) as { label: string; value: KokProps["yaziTipi"] }[],
                },
                kose: { type: "number", label: "Köşe (px)", min: 0, max: 32 },
            },
            render: ({
                children,
                ozelTema,
                anaRenk,
                arkaPlan,
                metinRengi,
                yaziTipi,
                kose,
            }) => {
                const tema = ozelTema === "evet";
                // Dış kutu huni sayfasındaki <body>'nin yerine: arka plan
                // bütün önizlemeyi kaplar, sütun .huni.
                const degiskenler = {
                    ...temaDegiskenleri(
                        tema
                            ? { anaRenk, arkaPlan, metinRengi, yaziTipi, kose }
                            : VARSAYILAN_TEMA,
                    ),
                    background: "var(--arka)",
                    minHeight: "100vh",
                } as CSSProperties;
                const oran = tema ? kontrast(metinRengi, arkaPlan) : 21;
                return (
                    <div style={degiskenler}>
                        {oran < 4.5 ? (
                            <p
                                style={{
                                    background: "#fde68a",
                                    color: "#000",
                                    padding: 8,
                                    borderRadius: 6,
                                }}
                            >
                                Metin okunurluğu {oran.toFixed(2)}:1 — en az
                                4,5:1 olmalı, kaydedilemez.
                            </p>
                        ) : null}
                        <div className="huni">{children}</div>
                    </div>
                );
            },
        },
        components: {
            Tanitim: {
                label: "Tanıtım",
                fields: {
                    kimlik: kimlikAlani,
                    baslik: { type: "text", label: "Başlık" },
                    metin: { type: "textarea", label: "Metin" },
                    dugme: { type: "text", label: "Düğme" },
                    gorsel: gorselAlani(ortam, "Kapak görseli"),
                    hedef: hedefAlani,
                },
                resolveFields: (veri, { fields, appState }) => ({
                    ...fields,
                    hedef: {
                        ...hedefAlani,
                        options: hedefSecenekleri(appState, veri.props.id),
                    },
                }),
                defaultProps: {
                    kimlik: "tanitim",
                    baslik: "Yeni tanıtım",
                    metin: "",
                    dugme: "Devam",
                    gorsel: "",
                    hedef: "",
                },
                render: ({ baslik, metin, dugme, gorsel }) => (
                    <div style={kart}>
                        <Tanitim
                            baslik={baslik}
                            metin={metin}
                            dugme={dugme}
                            gorsel={gorsel}
                            medyaKoku=""
                        />
                    </div>
                ),
            },
            Soru: {
                label: "Soru",
                fields: {
                    kimlik: kimlikAlani,
                    soru: { type: "text", label: "Soru" },
                    secenekler: {
                        type: "array",
                        label: "Seçenekler",
                        getItemSummary: (s: SecenekProps) =>
                            s.metin || s.kimlik || "seçenek",
                        defaultItemProps: {
                            kimlik: "secenek",
                            metin: "",
                            gorsel: "",
                            hedef: "",
                        },
                        arrayFields: {
                            metin: { type: "text", label: "Metin" },
                            kimlik: { type: "text", label: "Kimlik" },
                            gorsel: gorselAlani(ortam, "Görsel"),
                            hedef: hedefAlani,
                        },
                    },
                },
                resolveFields: (veri, { fields, appState }) => {
                    const secenekler = fields.secenekler;
                    if (secenekler.type !== "array") return fields;
                    return {
                        ...fields,
                        secenekler: {
                            ...secenekler,
                            arrayFields: {
                                ...secenekler.arrayFields,
                                hedef: {
                                    ...hedefAlani,
                                    options: hedefSecenekleri(
                                        appState,
                                        veri.props.id,
                                    ),
                                },
                            },
                        },
                    };
                },
                defaultProps: {
                    kimlik: "soru",
                    soru: "Yeni soru",
                    secenekler: [
                        {
                            kimlik: "secenek-1",
                            metin: "Seçenek 1",
                            gorsel: "",
                            hedef: "",
                        },
                        {
                            kimlik: "secenek-2",
                            metin: "Seçenek 2",
                            gorsel: "",
                            hedef: "",
                        },
                    ],
                },
                render: ({ soru, secenekler }) => (
                    <div style={kart}>
                        <Soru
                            soru={soru}
                            secenekler={secenekler ?? []}
                            medyaKoku=""
                        />
                    </div>
                ),
            },
            Paywall: {
                label: "Ödeme (en sonda olmalı)",
                fields: {
                    kimlik: kimlikAlani,
                    baslik: { type: "text", label: "Başlık" },
                    metin: { type: "text", label: "Fiyat satırı" },
                    dugme: { type: "text", label: "Düğme" },
                    plan: {
                        type: "select",
                        label: "Plan",
                        options: ortam.planlar.map((p) => ({
                            label: p,
                            value: p,
                        })) as { label: string; value: "monthly" }[],
                    },
                },
                defaultProps: {
                    kimlik: "paywall",
                    baslik: "Pro",
                    metin: "",
                    dugme: "Abone ol",
                    plan: "monthly",
                },
                render: ({ baslik, metin, dugme }) => (
                    <div style={kart}>
                        <Paywall baslik={baslik} metin={metin} dugme={dugme} />
                    </div>
                ),
            },
        },
    };
}
