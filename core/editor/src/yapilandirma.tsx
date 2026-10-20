// Puck yapılandırması: üç adım türü (çekirdeğin Tanim'i ile aynı) + kök
// (sayfa başlığı ve tema). Önizleme huni sayfasının görünümünü taklit eder;
// gerçek doğrulama çekirdekte — burada yalnız giriş kolaylığı.
import { FieldLabel, type Config, type CustomField } from "@puckeditor/core";
import { useEffect, useState, type CSSProperties, type ReactNode } from "react";
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

const YAZI_YIGINI: Record<string, string> = {
    sistem: "system-ui, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    yuvarlak: "ui-rounded, 'SF Pro Rounded', 'Nunito', system-ui, sans-serif",
    mono: "ui-monospace, 'SF Mono', Menlo, monospace",
};

/** WCAG parlaklığı — huni sayfasındaki ve çekirdekteki formülle aynı. */
function parlaklik(hex: string): number {
    const k = (i: number): number => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * k(1) + 0.7152 * k(3) + 0.0722 * k(5);
}
export const kontrast = (a: string, b: string): number => {
    const [x, y] = [parlaklik(a), parlaklik(b)];
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
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

const dugmeStili: CSSProperties = {
    font: "inherit",
    padding: "12px 16px",
    borderRadius: "var(--kose)",
    border: "1px solid var(--ana)",
    background: "var(--ana)",
    color: "var(--ana-yazi)",
    width: "100%",
};
const secenekStili: CSSProperties = {
    ...dugmeStili,
    background: "transparent",
    color: "inherit",
    textAlign: "left",
    display: "flex",
    alignItems: "center",
    gap: 12,
    border: "1px solid color-mix(in srgb, var(--metin) 30%, transparent)",
};
const kart: CSSProperties = {
    padding: "16px 0 24px",
    borderBottom:
        "1px dashed color-mix(in srgb, var(--metin) 25%, transparent)",
};

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
                const ana = tema ? anaRenk : "#2563eb";
                const l = parlaklik(ana);
                const degiskenler = {
                    "--ana": ana,
                    "--ana-yazi":
                        1.05 / (l + 0.05) >= (l + 0.05) / 0.05
                            ? "#ffffff"
                            : "#000000",
                    "--arka": tema ? arkaPlan : "#ffffff",
                    "--metin": tema ? metinRengi : "#111827",
                    "--kose": `${tema ? kose : 10}px`,
                    fontFamily: YAZI_YIGINI[tema ? yaziTipi : "sistem"],
                    background: "var(--arka)",
                    color: "var(--metin)",
                    minHeight: "100vh",
                    padding: 16,
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
                        <div style={{ maxWidth: 520, margin: "0 auto" }}>
                            {children}
                        </div>
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
                },
                defaultProps: {
                    kimlik: "tanitim",
                    baslik: "Yeni tanıtım",
                    metin: "",
                    dugme: "Devam",
                    gorsel: "",
                },
                render: ({ baslik, metin, dugme, gorsel }) => (
                    <section style={kart}>
                        {gorsel ? (
                            <img
                                src={`/medya/${gorsel}.webp`}
                                alt=""
                                style={{
                                    width: "100%",
                                    borderRadius: "var(--kose)",
                                }}
                            />
                        ) : null}
                        <h1>{baslik}</h1>
                        <p>{metin}</p>
                        <button type="button" style={dugmeStili}>
                            {dugme}
                        </button>
                    </section>
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
                        },
                        arrayFields: {
                            metin: { type: "text", label: "Metin" },
                            kimlik: { type: "text", label: "Kimlik" },
                            gorsel: gorselAlani(ortam, "Görsel"),
                        },
                    },
                },
                defaultProps: {
                    kimlik: "soru",
                    soru: "Yeni soru",
                    secenekler: [
                        { kimlik: "secenek-1", metin: "Seçenek 1", gorsel: "" },
                        { kimlik: "secenek-2", metin: "Seçenek 2", gorsel: "" },
                    ],
                },
                render: ({ soru, secenekler }) => (
                    <section style={kart}>
                        <h1>{soru}</h1>
                        <div style={{ display: "grid", gap: 10 }}>
                            {(secenekler ?? []).map((s, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    style={secenekStili}
                                >
                                    {s.gorsel ? (
                                        <img
                                            src={`/medya/${s.gorsel}.webp`}
                                            alt=""
                                            style={{
                                                width: 48,
                                                height: 48,
                                                objectFit: "cover",
                                            }}
                                        />
                                    ) : null}
                                    <span>{s.metin}</span>
                                </button>
                            ))}
                        </div>
                    </section>
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
                    <section style={kart}>
                        <h1>{baslik}</h1>
                        <p>{metin}</p>
                        <button type="button" style={dugmeStili}>
                            {dugme}
                        </button>
                    </section>
                ),
            },
        },
    };
}
