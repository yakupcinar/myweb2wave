// Puck yapılandırması: adım türleri (çekirdeğin Tanim'i ile aynı) + kök
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
import {
    Blok,
    Ekran,
    Logo,
    HareketBaglami,
    Paywall,
    SimgeBaglami,
    Yukleme,
} from "../../../huni-bilesenleri/bilesenler";
import "../../../huni-bilesenleri/huni.css";
import {
    kontrast,
    temaDegiskenleri,
    VARSAYILAN_TEMA,
} from "../../../huni-bilesenleri/tema";
import {
    BLOK_BILESENLERI,
    kokOnAyarlari,
    kokTemasi,
    kokZemini,
    type Tema,
} from "./donustur";
import type {
    Bilesenler,
    KokProps,
    SecenekProps,
    YonlendirmeProps,
    DegerProps,
} from "./donustur";

export type Ortam = {
    planlar: string[];
    yaziTipleri: string[];
    medyaAdresi: string; // panel listesi + yükleme ucu
    jeton: string;
    /** Ç91: koşul özellikleri sunucudan (T2 sözlüğü) — elle kopya yok. */
    ozellikler: { ad: string; aciklama: string }[];
    /** V2: hazır temalar sunucudan (Tema::HAZIR_TEMALAR) — elle kopya yok. */
    hazirTemalar: { anahtar: string; ad: string; uygun: string; tema: Tema }[];
};

/**
 * V2: hazır tema seçici — her düğme temanın kendi zemini ve ana rengiyle
 * çizilir; tıklanınca anahtar yazılır, kökün resolveData'sı alanları doldurur.
 */
const hazirTemaAlani = (
    temalar: Ortam["hazirTemalar"],
): CustomField<string> => ({
    type: "custom",
    label: "Hazır tema",
    render: ({ onChange }) => (
        <FieldLabel label="Hazır tema (tıkla: alanları doldurur)" el="div">
            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(3, 1fr)",
                    gap: 6,
                }}
            >
                {temalar.map((h) => (
                    <button
                        key={h.anahtar}
                        type="button"
                        title={h.uygun}
                        onClick={() => onChange(h.anahtar)}
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 6,
                            padding: "6px 8px",
                            borderRadius: 8,
                            border: "1px solid #d4d4d8",
                            background: h.tema.arkaPlan,
                            color: h.tema.metinRengi,
                            font: "inherit",
                            fontSize: 12,
                            cursor: "pointer",
                        }}
                    >
                        <span
                            aria-hidden="true"
                            style={{
                                width: 12,
                                height: 12,
                                borderRadius: 99,
                                flex: "none",
                                background: h.tema.anaRenk,
                            }}
                        />
                        {h.ad}
                    </button>
                ))}
            </div>
        </FieldLabel>
    ),
});

type Gorsel = {
    id: string;
    ozgun_ad: string;
    genislik: number;
    yukseklik: number;
    /** V3b: stok görselin kaynağı ve lisansı (yüklenende null). */
    kaynak?: {
        adres: string;
        yazar: string;
        lisans: string;
        lisansAdresi: string;
    } | null;
};
type StokSonucu = {
    kimlik: number;
    kucuk: string;
    yazar: string;
    aciklama: string;
};

/**
 * V3b: Pexels'te ara, tıklananı içe al (çekirdek kaynağı ve lisansı saklar).
 * Pexels API kuralı: aramanın yanında Pexels'e görünür bağlantı.
 */
function StokArama({
    ortam,
    onAlindi,
}: {
    ortam: Ortam;
    onAlindi: (id: string) => void;
}): ReactNode {
    const [sorgu, setSorgu] = useState("");
    const [sonuclar, setSonuclar] = useState<StokSonucu[]>([]);
    const [durum, setDurum] = useState("");
    const ara = async (): Promise<void> => {
        setDurum("Aranıyor…");
        const y = await fetch(
            `${ortam.medyaAdresi}/stok?${new URLSearchParams({ q: sorgu })}`,
        );
        const j = (await y.json()) as {
            sonuclar?: StokSonucu[];
            hatalar?: string[];
        };
        setSonuclar(j.sonuclar ?? []);
        setDurum(
            j.sonuclar
                ? j.sonuclar.length
                    ? ""
                    : "Sonuç yok."
                : (j.hatalar ?? ["arama başarısız"]).join(" · "),
        );
    };
    const al = async (kimlik: number): Promise<void> => {
        setDurum("Alınıyor…");
        const y = await fetch(`${ortam.medyaAdresi}/stok/${kimlik}`, {
            method: "POST",
            headers: { "X-CSRF-Token": ortam.jeton },
        });
        const j = (await y.json()) as { id?: string; hatalar?: string[] };
        if (!y.ok || !j.id) {
            setDurum((j.hatalar ?? ["alınamadı"]).join(" · "));
            return;
        }
        setDurum("");
        setSonuclar([]);
        onAlindi(j.id);
    };
    return (
        <div style={{ display: "grid", gap: 6 }}>
            <form
                style={{ display: "flex", gap: 6 }}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (sorgu.trim()) void ara();
                }}
            >
                <input
                    aria-label="Stok fotoğraf ara"
                    placeholder="Stok fotoğraf ara (İngilizce)"
                    value={sorgu}
                    onChange={(e) => setSorgu(e.target.value)}
                    style={{ flex: 1, minWidth: 0 }}
                />
                <button type="submit">Ara</button>
            </form>
            {sonuclar.length ? (
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns: "repeat(3, 1fr)",
                        gap: 4,
                    }}
                >
                    {sonuclar.map((s) => (
                        <button
                            key={s.kimlik}
                            type="button"
                            title={`${s.aciklama} — ${s.yazar}`}
                            onClick={() => void al(s.kimlik)}
                            style={{ padding: 0, border: 0, cursor: "pointer" }}
                        >
                            <img
                                src={s.kucuk}
                                alt={s.aciklama}
                                style={{
                                    width: "100%",
                                    aspectRatio: "1",
                                    objectFit: "cover",
                                    borderRadius: 4,
                                }}
                            />
                        </button>
                    ))}
                </div>
            ) : null}
            {durum ? <small>{durum}</small> : null}
            <small>
                <a
                    href="https://www.pexels.com"
                    target="_blank"
                    rel="noreferrer"
                >
                    Fotoğraflar: Pexels
                </a>
            </small>
        </div>
    );
}

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

const ilerlemeAlani: CustomField<boolean> = {
    type: "custom",
    label: "İlerleme çubuğu",
    render: ({ value, onChange, id }) => (
        <FieldLabel label="Bu adımda göster" el="label">
            <input
                id={id}
                type="checkbox"
                checked={value ?? true}
                onChange={(e) => onChange(e.target.checked)}
            />
        </FieldLabel>
    ),
};

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
    const kaynak = liste.find((g) => g.id === value)?.kaynak;
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
            {kaynak ? (
                <small>
                    Foto:{" "}
                    <a href={kaynak.adres} target="_blank" rel="noreferrer">
                        {kaynak.yazar}
                    </a>{" "}
                    ·{" "}
                    <a
                        href={kaynak.lisansAdresi}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {kaynak.lisans}
                    </a>
                </small>
            ) : null}
            <StokArama
                ortam={ortam}
                onAlindi={async (id) => {
                    await yenile();
                    onChange(id);
                }}
            />
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

/** Ç44: boşsa görsel dekoratif sayılır; görselsiz yazılmaz. */
const gorselMetniAlani = {
    type: "text" as const,
    label: "Görsel açıklaması (alt metin, isteğe bağlı)",
};

const kimlikAlani = {
    type: "text" as const,
    label: "Kimlik (panelde görünür; yayından sonra değiştirmek eski veriyle eşleşmeyi koparır)",
};

// H5: düğme / seçenek nereye gider — yalnız SONRAKİ adımlar (çekirdek de
// yalnız ileriyi kabul eder; sıra sonradan değişirse kaydederken hata verir).
const SIRADAKI = { label: "Sıradaki adım", value: "" };
const hedefAlani: SelectField = {
    type: "select",
    label: "Bu seçilince gidilecek adım",
    options: [{ label: "Sıradaki adım", value: "" }],
};
const tanitimHedefAlani: SelectField = {
    ...hedefAlani,
    label: "Devam düğmesiyle gidilecek adım",
};
const yanitHedefAlani: SelectField = {
    ...hedefAlani,
    label: "Bu yanıt seçilince gidilecek adım",
};
export function hedefSecenekleri(
    appState: AppState,
    bilesen: string,
): SelectField["options"] {
    const icerik = appState.data.content;
    // T1b: soru bloğu kökte değil, ekranın yuvasında — sıra ekranınki.
    const icinde = (o: (typeof icerik)[number]): boolean =>
        o.props["id"] === bilesen ||
        (Array.isArray(o.props["bloklar"]) &&
            (o.props["bloklar"] as { props: { id: string } }[]).some(
                (b) => b.props.id === bilesen,
            ));
    const sira = icerik.findIndex(icinde);
    return [
        SIRADAKI,
        // T1: kökte kalmış blok adım değil, hedef olamaz.
        ...icerik
            .slice(sira + 1)
            .flatMap((o) => ("kimlik" in o.props ? [o] : []))
            .map((o) => ({
                label: `${String(o.props["kimlik"])} (${o.type})`,
                value: String(o.props["kimlik"]),
            })),
    ];
}

/**
 * T3a: koşulun özellik seçenekleri — sunucunun verdiği sözlük (Ç91; kayıtta
 * çekirdek yine denetler) + bu ve önceki ekranların soruları.
 */
export function ozellikSecenekleri(
    appState: AppState,
    bilesen: string,
    /** T3b: gösterim koşulu ekran çizilmeden hesaplanır — bu ekranın cevabı yok. */
    buEkranDahil: boolean,
    sabit: Ortam["ozellikler"],
): SelectField["options"] {
    const icerik = appState.data.content;
    const sira = icerik.findIndex((o) => o.props["id"] === bilesen);
    return [
        ...sabit.map((o) => ({
            label: `${o.aciklama} (${o.ad})`,
            value: o.ad,
        })),
        // Ekranı bulunamayan (ekran dışına bırakılmış) blokta cevap yok:
        // -1 ile slice "sondan biri hariç hepsi" verirdi (Ç91 testinde ölçüldü).
        ...(sira < 0 ? [] : icerik)
            .slice(0, buEkranDahil ? sira + 1 : sira)
            // E1: e-posta cevabı koşul özelliği değil (çekirdek reddeder).
            .filter(
                (o) =>
                    Array.isArray(o.props["bloklar"]) &&
                    (
                        o.props["bloklar"] as {
                            type: string;
                            props?: { secimTuru?: string };
                        }[]
                    ).some(
                        (b) =>
                            b.type === "SoruBlogu" &&
                            b.props?.secimTuru !== "eposta",
                    ),
            )
            .map((o) => ({
                label: `Cevap: ${String(o.props["kimlik"])}`,
                value: `cevap.${String(o.props["kimlik"])}`,
            })),
    ];
}

const yonlendirmeAlani = {
    type: "array" as const,
    label: "Koşullu yönlendirme (ilk tutan kural; tutmazsa düğmenin hedefi)",
    max: 10,
    getItemSummary: (k: YonlendirmeProps) =>
        `${k.kosullar?.[0]?.ozellik ?? "koşul"}${(k.kosullar?.length ?? 0) > 1 ? " …" : ""} → ${k.hedef || "?"}`,
    defaultItemProps: {
        baglac: "hepsi" as const,
        kosullar: [
            { ozellik: "utm.source", islem: "esit" as const, deger: "" },
        ],
        hedef: "",
    },
    arrayFields: {
        baglac: {
            type: "radio" as const,
            label: "Koşullar",
            options: [
                { label: "Hepsi tutmalı (VE)", value: "hepsi" },
                { label: "Biri yeter (VEYA)", value: "herhangi" },
            ],
        },
        kosullar: {
            type: "array" as const,
            label: "Koşullar (1–5)",
            max: 5,
            getItemSummary: (c: YonlendirmeProps["kosullar"][number]) =>
                `${c.ozellik} ${c.islem} ${c.deger}`.trim(),
            defaultItemProps: {
                ozellik: "utm.source",
                islem: "esit" as const,
                deger: "",
            },
            arrayFields: {
                ozellik: {
                    type: "select" as const,
                    label: "Özellik",
                    // Ç91: seçenekler bileşenin resolveFields'ında (sunucu + ekranlar).
                    options: [] as SelectField["options"],
                },
                islem: {
                    type: "select" as const,
                    label: "İşlem (cevaplarda içerir/içermez)",
                    options: [
                        { label: "eşittir", value: "esit" },
                        { label: "eşit değil", value: "esit_degil" },
                        { label: "içerir (cevap)", value: "icerir" },
                        { label: "içermez (cevap)", value: "icermez" },
                        { label: "var", value: "var" },
                        { label: "yok", value: "yok" },
                    ],
                },
                deger: {
                    type: "text" as const,
                    label: "Değer (cevapta seçenek kimliği; var/yok'ta boş)",
                },
            },
        },
        hedef: { ...hedefAlani, label: "Gidilecek adım" },
    },
};

/**
 * T3c: koşullu değer — kural = koşullar + bir alanın yeni değeri; alan listesi
 * bileşenin değiştirilebilir alanları (çekirdek aynı listeyi denetler).
 */
function degerAlani(alanlar: [string, string][]) {
    return {
        type: "array" as const,
        label: "Koşullu değerler (alan başına ilk tutan kural)",
        max: 20,
        getItemSummary: (k: DegerProps) =>
            `${k.alan || "?"} ← ${k.kosullar?.[0]?.ozellik ?? "koşul"}`,
        defaultItemProps: {
            baglac: "hepsi" as const,
            kosullar: [
                { ozellik: "utm.source", islem: "esit" as const, deger: "" },
            ],
            alan: alanlar[0]![0],
            deger: "",
        },
        arrayFields: {
            baglac: yonlendirmeAlani.arrayFields.baglac,
            kosullar: yonlendirmeAlani.arrayFields.kosullar,
            alan: {
                type: "select" as const,
                label: "Alan",
                options: alanlar.map(([value, label]) => ({ label, value })),
            },
            deger: { type: "textarea" as const, label: "Yeni değer" },
        },
    };
}

/** Ç91: "Koşullu değerler" alanına özellik seçenekleri (koşullar dizisinin içinde). */
function degerSecenekli(
    alan: ReturnType<typeof degerAlani>,
    secenekler: SelectField["options"],
): ReturnType<typeof degerAlani> {
    const k = alan.arrayFields.kosullar;
    return {
        ...alan,
        arrayFields: {
            ...alan.arrayFields,
            kosullar: {
                ...k,
                arrayFields: {
                    ...k.arrayFields,
                    ozellik: { ...k.arrayFields.ozellik, options: secenekler },
                },
            },
        },
    };
}

// T3b: gösterim koşulu — yönlendirmenin koşul alanları; boş liste = hep gösterilir.
const gosterBaglacAlani = {
    ...yonlendirmeAlani.arrayFields.baglac,
    label: "Gösterim koşulları",
};
const gosterAlani = {
    ...yonlendirmeAlani.arrayFields.kosullar,
    label: "Gösterim koşulu (tutmazsa ekran atlanır; boş = hep gösterilir)",
};

/**
 * Ekleme anında kimliğe Puck blok kimliğinden kısa ek (Ç61): aynı türden
 * ikinci blok kayıtta "kimlik tekrar ediyor" ile reddedilmesin. Yalnız
 * eklemede — yüklenen ya da yayındaki kimlik değişmez (eski veriyle eşleşme).
 * Puck'ın "çoğalt" eylemi resolveData çağırmıyor; kopya kimliği kayıtta
 * reddedilir (Ç65).
 */
export function benzersizKimlik(kimlik: string, puckKimligi: string): string {
    // Puck kimliği "Tür-uuid": son 4 karakter küçük harf onaltılık.
    return `${kimlik}-${puckKimligi.slice(-4)}`;
}
const eklenince = <P extends { id: string; kimlik: string }>(
    veri: { props: P },
    { trigger }: { trigger: string },
) =>
    trigger === "insert"
        ? {
              ...veri,
              props: {
                  ...veri.props,
                  kimlik: benzersizKimlik(veri.props.kimlik, veri.props.id),
              },
          }
        : veri;

/** T1b/T1c: soru bloğunun yanıt alanları (soru metni ekranın başlığı). */
function soruAlanlari(ortam: Ortam) {
    return {
        secimTuru: {
            type: "radio",
            label: "Yanıt biçimi",
            options: [
                { label: "Tek seçim", value: "tekli" },
                { label: "Çoklu seçim", value: "coklu" },
                { label: "Serbest yanıt", value: "metin" },
                { label: "E-posta", value: "eposta" },
                { label: "Ölçek (1–5)", value: "olcek" },
            ],
        },
        yanitZorunlu: {
            type: "radio",
            label: "Yanıt zorunluluğu",
            options: [
                { label: "İsteğe bağlı", value: "istege_bagli" },
                { label: "Zorunlu", value: "zorunlu" },
            ],
        },
        // E1: boşsa kutu yok, pazarlama izni alınmaz.
        izinMetni: {
            type: "text",
            label: "Pazarlama izni kutusu (boşsa yok)",
        },
        // V5: ölçeğin uç etiketleri (seçenekler 1–5 kendiliğinden).
        olcekSol: {
            type: "text",
            label: "1'in etiketi (ör. Hiç katılmıyorum)",
        },
        olcekSag: {
            type: "text",
            label: "5'in etiketi (ör. Tamamen katılıyorum)",
        },
        // G4: kısa ya da görselli seçenekler için iki sütun.
        duzen: {
            type: "radio",
            label: "Seçenek dizilişi",
            options: [
                { label: "Liste", value: "liste" },
                { label: "Izgara (2 sütun)", value: "izgara" },
            ],
        },
        secenekler: {
            type: "array",
            label: "Seçenekler",
            getItemSummary: (s: SecenekProps) =>
                s.metin || s.kimlik || "seçenek",
            defaultItemProps: {
                kimlik: "secenek",
                metin: "",
                simge: "",
                gorsel: "",
                gorselMetni: "",
                hedef: "",
            },
            arrayFields: {
                metin: { type: "text", label: "Metin" },
                // Tek emoji; kayıtta çekirdek denetler (görselle birlikte olmaz).
                simge: { type: "text", label: "Simge (tek emoji)" },
                kimlik: { type: "text", label: "Kimlik" },
                gorsel: gorselAlani(ortam, "Görsel"),
                gorselMetni: gorselMetniAlani,
                hedef: yanitHedefAlani,
            },
        },
    } satisfies NonNullable<
        Config<Bilesenler, KokProps>["components"]["SoruBlogu"]["fields"]
    >;
}

const VARSAYILAN_SECENEKLER: SecenekProps[] = [
    {
        kimlik: "secenek-1",
        metin: "Seçenek 1",
        gorsel: "",
        gorselMetni: "",
        hedef: "",
    },
    {
        kimlik: "secenek-2",
        metin: "Seçenek 2",
        gorsel: "",
        gorselMetni: "",
        hedef: "",
    },
];

/** T3c: koşullu değer alanı olan bloklar (soru ve maddeler dışı). */
const DEGERLI_BLOKLAR = [
    "BaslikBlogu",
    "MetinBlogu",
    "GorselBlogu",
    "YorumBlogu",
    "IstatistikBlogu",
    "KutuBlogu",
    "GrafikBlogu",
] as const;

export function yapilandirma(ortam: Ortam): Config<Bilesenler, KokProps> {
    const soru = soruAlanlari(ortam);
    const yap: Config<Bilesenler, KokProps> = {
        root: {
            fields: {
                sayfaBasligi: {
                    type: "text",
                    label: "Sayfa başlığı (tarayıcı sekmesi)",
                },
                logo: gorselAlani(ortam, "Logo (isteğe bağlı)"),
                hazirTema: hazirTemaAlani(ortam.hazirTemalar),
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
                // G3: zemin — okunurluğu kayıtta çekirdek ölçer (karartma eşiği dahil).
                zeminTuru: {
                    type: "radio",
                    label: "Zemin",
                    options: [
                        { label: "Düz renk", value: "yok" },
                        { label: "Geçiş", value: "gecis" },
                        { label: "Görsel", value: "gorsel" },
                    ],
                },
                ikinciRenk: renkAlani("Geçişin alt rengi"),
                zeminGorseli: gorselAlani(ortam, "Zemin görseli"),
                karartma: {
                    type: "number",
                    label: "Görsel üstü karartma (%)",
                    min: 0,
                    max: 95,
                },
                // G6: kart varsa okunurluk metin ↔ kart rengi ölçülür.
                kart: {
                    type: "radio",
                    label: "İçerik kartı",
                    options: [
                        { label: "Yok", value: "yok" },
                        { label: "Var", value: "var" },
                    ],
                },
                kartRengi: renkAlani("Kart rengi"),
                // G7: ön ayarlar (serbest piksel değil).
                baslikBoyu: {
                    type: "radio",
                    label: "Başlık boyu",
                    options: [
                        { label: "Küçük", value: "kucuk" },
                        { label: "Normal", value: "normal" },
                        { label: "Büyük", value: "buyuk" },
                    ],
                },
                yogunluk: {
                    type: "radio",
                    label: "Boşluk",
                    options: [
                        { label: "Sık", value: "sik" },
                        { label: "Normal", value: "normal" },
                        { label: "Ferah", value: "ferah" },
                    ],
                },
                hizalama: {
                    type: "radio",
                    label: "Başlık hizası",
                    options: [
                        { label: "Sol", value: "sol" },
                        { label: "Orta", value: "orta" },
                    ],
                },
                dugmeBicimi: {
                    type: "radio",
                    label: "Düğme biçimi",
                    options: [
                        { label: "Dolu", value: "dolu" },
                        { label: "Çerçeve", value: "cerceve" },
                        { label: "Hap", value: "hap" },
                    ],
                },
                // V4: hareket düzeyi (önizlemede adım geçişi yok — giriş
                // hareketi yalnız huni sayfasında görülür).
                hareket: {
                    type: "radio",
                    label: "Hareket",
                    options: [
                        { label: "Yok", value: "yok" },
                        { label: "Sakin", value: "sakin" },
                        { label: "Canlı", value: "canli" },
                    ],
                },
                // V3a: Fluent simgeleri çekirdekten (önizlemede kök "").
                simgeler: {
                    type: "radio",
                    label: "Emoji simgeleri",
                    options: [
                        { label: "Cihazın", value: "cihaz" },
                        { label: "Renkli 3B", value: "renkli" },
                        { label: "Tek renk", value: "tekrenk" },
                    ],
                },
            },
            // V2: hazır tema seçilince alanlar açılıştaki eşlemeyle dolar,
            // tema özel olur; seçim alanı boşalır (tanıma yazılmaz).
            resolveData: (veri) => {
                const h = ortam.hazirTemalar.find(
                    (t) => t.anahtar === veri.props?.hazirTema,
                );
                return h
                    ? {
                          props: {
                              ...veri.props,
                              ...kokTemasi(h.tema),
                              ozelTema: "evet" as const,
                              hazirTema: "",
                          },
                      }
                    : veri;
            },
            render: ({
                children,
                logo,
                ozelTema,
                anaRenk,
                arkaPlan,
                metinRengi,
                yaziTipi,
                kose,
                zeminTuru,
                ikinciRenk,
                zeminGorseli,
                karartma,
                kart,
                kartRengi,
                baslikBoyu,
                yogunluk,
                hizalama,
                dugmeBicimi,
                simgeler,
                hareket,
            }) => {
                const tema = ozelTema === "evet";
                // Dış kutu huni sayfasındaki <body>'nin yerine: arka plan
                // bütün önizlemeyi kaplar, sütun .huni.
                const degiskenler = {
                    ...temaDegiskenleri(
                        tema
                            ? {
                                  anaRenk,
                                  arkaPlan,
                                  metinRengi,
                                  yaziTipi,
                                  kose,
                                  ...(kart === "var" ? { kartRengi } : {}),
                                  ...kokOnAyarlari({
                                      baslikBoyu,
                                      yogunluk,
                                      hizalama,
                                      dugmeBicimi,
                                      simgeler,
                                      hareket,
                                  }),
                                  ...kokZemini({
                                      zeminTuru,
                                      ikinciRenk,
                                      zeminGorseli,
                                      karartma,
                                  }),
                              }
                            : VARSAYILAN_TEMA,
                    ),
                    background: "var(--zemin, var(--arka))",
                    minHeight: "100vh",
                } as CSSProperties;
                // Çekirdekle aynı kural (Tema.php): kart varsa metin kartın üstünde.
                const oran = tema
                    ? kontrast(
                          metinRengi,
                          kart === "var" ? kartRengi : arkaPlan,
                      )
                    : 21;
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
                        <SimgeBaglami.Provider
                            value={{
                                stil: tema ? (simgeler ?? "cihaz") : "cihaz",
                                kok: "",
                            }}
                        >
                            <HareketBaglami.Provider
                                value={tema ? (hareket ?? "sakin") : "sakin"}
                            >
                                <div
                                    className="huni"
                                    data-hareket={
                                        tema ? (hareket ?? "sakin") : "sakin"
                                    }
                                >
                                    {logo ? (
                                        <Logo gorsel={logo} medyaKoku="" />
                                    ) : null}
                                    {children}
                                </div>
                            </HareketBaglami.Provider>
                        </SimgeBaglami.Provider>
                    </div>
                );
            },
        },
        // T1: bloklar ayrı kategoride; yalnız Ekran'ın yuvasına bırakılır
        // (yuva allow). Kök bölge kısıtlanamıyor — dışarıda kalan blok
        // için düzenleyici uyarır (akis.ts), çekirdek reddeder.
        categories: {
            adimlar: {
                title: "Adımlar",
                components: ["Ekran", "Yukleme", "Paywall"],
            },
            bloklar: {
                title: "Ekran blokları (Ekran adımının içine)",
                components: BLOK_BILESENLERI as (keyof Bilesenler)[],
            },
        },
        components: {
            Ekran: {
                label: "Ekran (bloklardan)",
                resolveData: eklenince,
                fields: {
                    kimlik: kimlikAlani,
                    // T1c: kapak — tanıtımın görseli ve düzeni.
                    gorsel: gorselAlani(ortam, "Kapak görseli (isteğe bağlı)"),
                    gorselMetni: gorselMetniAlani,
                    duzen: {
                        type: "radio",
                        label: "Kapak düzeni (tam ekranda yalnız metin blokları)",
                        options: [
                            { label: "Üstte", value: "ust" },
                            { label: "Büyük", value: "kahraman" },
                            { label: "Tam ekran", value: "tamEkran" },
                        ],
                    },
                    baslik: { type: "text", label: "Başlık" },
                    bloklar: { type: "slot", allow: BLOK_BILESENLERI },
                    dugme: {
                        type: "text",
                        label: "Düğme (tek seçimli soru bloğunda boş — seçenek geçirir)",
                    },
                    ilerlemeGoster: ilerlemeAlani,
                    hedef: tanitimHedefAlani,
                    yonlendirme: yonlendirmeAlani,
                    gosterBaglac: gosterBaglacAlani,
                    gosterKosullar: gosterAlani,
                    degerler: degerAlani([
                        ["baslik", "Başlık"],
                        ["dugme", "Düğme"],
                        ["gorsel", "Kapak görseli (kimlik)"],
                        ["gorselMetni", "Kapak alt metni"],
                    ]),
                },
                resolveFields: (veri, { fields, appState }) => {
                    const hedefler = hedefSecenekleri(appState, veri.props.id);
                    return {
                        ...fields,
                        hedef: { ...tanitimHedefAlani, options: hedefler },
                        // T3a: kural hedefi zorunlu ("sıradaki" yok); özellikler bu ekrana göre.
                        yonlendirme: {
                            ...yonlendirmeAlani,
                            arrayFields: {
                                ...yonlendirmeAlani.arrayFields,
                                hedef: {
                                    ...yonlendirmeAlani.arrayFields.hedef,
                                    options: hedefler.filter(
                                        (o) => o.value !== "",
                                    ),
                                },
                                kosullar: {
                                    ...yonlendirmeAlani.arrayFields.kosullar,
                                    arrayFields: {
                                        ...yonlendirmeAlani.arrayFields.kosullar
                                            .arrayFields,
                                        ozellik: {
                                            ...yonlendirmeAlani.arrayFields
                                                .kosullar.arrayFields.ozellik,
                                            options: ozellikSecenekleri(
                                                appState,
                                                veri.props.id,
                                                true,
                                                ortam.ozellikler,
                                            ),
                                        },
                                    },
                                },
                            },
                        },
                        gosterKosullar: {
                            ...gosterAlani,
                            arrayFields: {
                                ...gosterAlani.arrayFields,
                                ozellik: {
                                    ...gosterAlani.arrayFields.ozellik,
                                    options: ozellikSecenekleri(
                                        appState,
                                        veri.props.id,
                                        false,
                                        ortam.ozellikler,
                                    ),
                                },
                            },
                        },
                        // Ç91: koşullu değer gösterimde hesaplanır — önceki ekranlar.
                        degerler: degerSecenekli(
                            fields.degerler as ReturnType<typeof degerAlani>,
                            ozellikSecenekleri(
                                appState,
                                veri.props.id,
                                false,
                                ortam.ozellikler,
                            ),
                        ),
                    };
                },
                defaultProps: {
                    kimlik: "ekran",
                    yonlendirme: [],
                    gosterBaglac: "hepsi",
                    gosterKosullar: [],
                    degerler: [],
                    baslik: "Yeni ekran",
                    gorsel: "",
                    gorselMetni: "",
                    duzen: "ust",
                    bloklar: [
                        {
                            type: "MetinBlogu",
                            props: { metin: "Metni buraya yaz." },
                        },
                    ],
                    dugme: "Devam",
                    ilerlemeGoster: true,
                    hedef: "",
                },
                render: ({
                    baslik,
                    gorsel,
                    gorselMetni,
                    duzen,
                    bloklar: Bloklar,
                    dugme,
                    onizlemeKapali,
                }) => (
                    <div style={kart}>
                        <Ekran
                            baslik={baslik}
                            gorsel={gorsel}
                            gorselMetni={gorselMetni}
                            duzen={duzen}
                            dugme={dugme}
                            kapali={onizlemeKapali ?? false}
                            medyaKoku=""
                            yuva={<Bloklar className="bloklar" />}
                        />
                    </div>
                ),
            },
            // T1b: soru adımının yanıt alanları; soru metni ekranın başlığı.
            SoruBlogu: {
                label: "Soru (ekranda bir tane)",
                fields: soru,
                resolveFields: (veri, { fields, appState }) => {
                    const secenekler = fields.secenekler;
                    if (secenekler.type !== "array") return fields;
                    // E1: e-posta da serbest yanıt ailesinde (seçeneksiz).
                    // E1: e-posta, V5: ölçek de seçeneksiz.
                    const metin =
                        veri.props.secimTuru === "metin" ||
                        veri.props.secimTuru === "eposta";
                    const olcek = veri.props.secimTuru === "olcek";
                    return {
                        ...fields,
                        yanitZorunlu: {
                            ...fields.yanitZorunlu,
                            visible: metin,
                        },
                        izinMetni: {
                            ...fields.izinMetni,
                            visible: veri.props.secimTuru === "eposta",
                        },
                        olcekSol: { ...fields.olcekSol, visible: olcek },
                        olcekSag: { ...fields.olcekSag, visible: olcek },
                        duzen: { ...fields.duzen, visible: !metin && !olcek },
                        secenekler: {
                            ...secenekler,
                            visible: !metin,
                            arrayFields: {
                                ...secenekler.arrayFields,
                                hedef: {
                                    ...yanitHedefAlani,
                                    options: hedefSecenekleri(
                                        appState,
                                        veri.props.id,
                                    ),
                                    visible: veri.props.secimTuru === "tekli",
                                },
                            },
                        },
                    };
                },
                defaultProps: {
                    secimTuru: "tekli",
                    yanitZorunlu: "istege_bagli",
                    izinMetni: "",
                    olcekSol: "Hiç katılmıyorum",
                    olcekSag: "Tamamen katılıyorum",
                    duzen: "liste",
                    secenekler: VARSAYILAN_SECENEKLER,
                },
                render: ({
                    secimTuru,
                    duzen,
                    yanitZorunlu,
                    izinMetni,
                    olcekSol,
                    olcekSag,
                    secenekler,
                }) => (
                    <Blok
                        blok={{
                            tur: "soru",
                            secimTuru,
                            duzen,
                            yanitZorunlu: yanitZorunlu === "zorunlu",
                            izinMetni:
                                secimTuru === "eposta" && izinMetni?.trim()
                                    ? izinMetni
                                    : undefined,
                            olcekSol,
                            olcekSag,
                            secenekler: secenekler ?? [],
                        }}
                        medyaKoku=""
                    />
                ),
            },
            BaslikBlogu: {
                label: "Ara başlık",
                fields: {
                    degerler: degerAlani([["metin", "Metin"]]),
                    metin: { type: "text", label: "Başlık" },
                },
                defaultProps: { metin: "Ara başlık" },
                render: ({ metin }) => (
                    <Blok blok={{ tur: "baslik", metin }} medyaKoku="" />
                ),
            },
            MetinBlogu: {
                label: "Paragraf",
                fields: {
                    degerler: degerAlani([["metin", "Metin"]]),
                    metin: { type: "textarea", label: "Metin" },
                },
                defaultProps: { metin: "Metni buraya yaz." },
                render: ({ metin }) => (
                    <Blok blok={{ tur: "metin", metin }} medyaKoku="" />
                ),
            },
            GorselBlogu: {
                label: "Görsel",
                fields: {
                    degerler: degerAlani([
                        ["gorsel", "Görsel (kimlik)"],
                        ["gorselMetni", "Alt metin"],
                    ]),
                    gorsel: gorselAlani(ortam, "Görsel"),
                    gorselMetni: gorselMetniAlani,
                },
                defaultProps: { gorsel: "", gorselMetni: "" },
                // Görsel seçilmeden kayıt çekirdekte reddedilir; yer tutucu yalnız burada.
                render: ({ gorsel, gorselMetni }) =>
                    gorsel ? (
                        <Blok
                            blok={{ tur: "gorsel", gorsel, gorselMetni }}
                            medyaKoku=""
                        />
                    ) : (
                        <p>Görsel seç (sağdaki alandan).</p>
                    ),
            },
            MaddelerBlogu: {
                label: "Madde listesi",
                fields: {
                    maddeler: {
                        type: "array",
                        label: "Maddeler (1–6)",
                        max: 6,
                        getItemSummary: (m: { metin: string }) =>
                            m.metin || "madde",
                        defaultItemProps: { metin: "" },
                        arrayFields: {
                            metin: { type: "text", label: "Madde" },
                        },
                    },
                },
                defaultProps: {
                    maddeler: [{ metin: "Birinci fayda" }],
                },
                render: ({ maddeler }) => (
                    <Blok
                        blok={{
                            tur: "maddeler",
                            maddeler: (maddeler ?? [])
                                .map((m) => m.metin)
                                .filter(Boolean),
                        }}
                        medyaKoku=""
                    />
                ),
            },
            YorumBlogu: {
                label: "Kısa yorum",
                fields: {
                    degerler: degerAlani([
                        ["metin", "Metin"],
                        ["yazar", "Yazar"],
                        ["unvan", "Unvan"],
                    ]),
                    metin: { type: "textarea", label: "Yorum" },
                    yazar: { type: "text", label: "Ad" },
                    unvan: { type: "text", label: "Açıklama (isteğe bağlı)" },
                    puan: {
                        type: "select",
                        label: "Puan",
                        options: [
                            { label: "Yok", value: 0 },
                            ...[1, 2, 3, 4, 5].map((n) => ({
                                label: "★".repeat(n),
                                value: n,
                            })),
                        ],
                    },
                },
                defaultProps: {
                    metin: "Çok işime yaradı.",
                    yazar: "Ayşe",
                    unvan: "",
                    puan: 5,
                },
                render: ({ metin, yazar, unvan, puan }) => (
                    <Blok
                        blok={{
                            tur: "yorum",
                            metin,
                            yazar,
                            unvan,
                            puan: Number(puan) || undefined,
                        }}
                        medyaKoku=""
                    />
                ),
            },
            IstatistikBlogu: {
                label: "İstatistik",
                fields: {
                    degerler: degerAlani([
                        ["deger", "Değer"],
                        ["metin", "Satır"],
                    ]),
                    deger: {
                        type: "text",
                        label: "Değer (en çok 12 karakter)",
                    },
                    metin: { type: "text", label: "Açıklama" },
                },
                defaultProps: { deger: "%87", metin: "Gerçek bir ölçüm yaz" },
                render: ({ deger, metin }) => (
                    <Blok
                        blok={{ tur: "istatistik", deger, metin }}
                        medyaKoku=""
                    />
                ),
            },
            KutuBlogu: {
                label: "Not kutusu",
                fields: {
                    degerler: degerAlani([
                        ["metin", "Metin"],
                        ["simge", "Simge"],
                    ]),
                    metin: { type: "textarea", label: "Metin" },
                    simge: {
                        type: "text",
                        label: "Simge (tek emoji, isteğe bağlı)",
                    },
                },
                defaultProps: {
                    metin: "İstediğin zaman iptal edebilirsin.",
                    simge: "",
                },
                render: ({ metin, simge }) => (
                    <Blok blok={{ tur: "kutu", metin, simge }} medyaKoku="" />
                ),
            },
            // V5: projeksiyon eğrisi — sayı yok, uç etiketleri.
            GrafikBlogu: {
                label: "İlerleme grafiği",
                fields: {
                    degerler: degerAlani([
                        ["baslangic", "Başlangıç"],
                        ["hedef", "Hedef"],
                    ]),
                    baslangic: {
                        type: "text",
                        label: "Başlangıç etiketi (ör. Bugün)",
                    },
                    hedef: {
                        type: "text",
                        label: "Hedef etiketi (ör. 4. hafta)",
                    },
                    egri: {
                        type: "radio",
                        label: "Eğri",
                        options: [
                            { label: "Artan", value: "artan" },
                            { label: "Azalan", value: "azalan" },
                        ],
                    },
                },
                defaultProps: {
                    baslangic: "Bugün",
                    hedef: "4. hafta",
                    egri: "artan",
                },
                render: ({ baslangic, hedef, egri }) => (
                    <Blok
                        blok={{ tur: "grafik", baslangic, hedef, egri }}
                        medyaKoku=""
                    />
                ),
            },
            // V5: önce/sonra — iki yüklenmiş görsel.
            OnceSonraBlogu: {
                label: "Önce / sonra",
                fields: {
                    once: gorselAlani(ortam, "Önce görseli"),
                    onceEtiketi: { type: "text", label: "Önce etiketi" },
                    sonra: gorselAlani(ortam, "Sonra görseli"),
                    sonraEtiketi: { type: "text", label: "Sonra etiketi" },
                },
                defaultProps: {
                    once: "",
                    sonra: "",
                    onceEtiketi: "Önce",
                    sonraEtiketi: "Sonra",
                },
                render: ({ once, sonra, onceEtiketi, sonraEtiketi }) =>
                    once && sonra ? (
                        <Blok
                            blok={{
                                tur: "onceSonra",
                                once,
                                sonra,
                                onceEtiketi,
                                sonraEtiketi,
                            }}
                            medyaKoku=""
                        />
                    ) : (
                        <p className="onizleme">
                            Önce/sonra: iki görsel seçin.
                        </p>
                    ),
            },
            Paywall: {
                // M2c: birden çok olabilir; son adım ödeme olmalı, öndekine yönlendirmeyle gidilir.
                label: "Ödeme (son adım ödeme olmalı)",
                fields: {
                    kimlik: kimlikAlani,
                    baslik: { type: "text", label: "Başlık" },
                    metin: { type: "text", label: "Fiyat satırı" },
                    dugme: { type: "text", label: "Düğme" },
                    ilerlemeGoster: ilerlemeAlani,
                    plan: {
                        type: "select",
                        label: "Plan (seçicide varsayılan seçili)",
                        options: ortam.planlar.map((p) => ({
                            label: p,
                            value: p,
                        })),
                    },
                    // M2a: 2–3 plan → seçici; varsayılan plan da listede olmalı (çekirdek doğrular).
                    planlar: {
                        type: "array",
                        label: "Plan seçici (2–3 plan, isteğe bağlı)",
                        max: 3,
                        getItemSummary: (p: { plan: string }) =>
                            p.plan || "plan",
                        defaultItemProps: { plan: ortam.planlar[0] ?? "" },
                        arrayFields: {
                            plan: {
                                type: "select",
                                label: "Plan",
                                options: ortam.planlar.map((p) => ({
                                    label: p,
                                    value: p,
                                })),
                            },
                        },
                    },
                    // M2b: yalnız kurgusu gereği doğru rozetler; şart onayı şart adresi ister.
                    guvenceler: {
                        type: "array",
                        label: "Güven rozetleri (isteğe bağlı)",
                        max: 2,
                        getItemSummary: (g: { rozet: string }) =>
                            g.rozet || "rozet",
                        defaultItemProps: { rozet: "guvenli-odeme" },
                        arrayFields: {
                            rozet: {
                                type: "select",
                                label: "Rozet",
                                options: [
                                    {
                                        label: "Güvenli ödeme · Stripe",
                                        value: "guvenli-odeme",
                                    },
                                    {
                                        label: "Kart bilgin bizde saklanmaz",
                                        value: "kart-saklanmaz",
                                    },
                                ],
                            },
                        },
                    },
                    // M3a: geri tuşu / ödeme panelini kapatınca bir kez sunulan plan.
                    downsellPlan: {
                        type: "select",
                        label: "Downsell planı (geri dönünce bir kez)",
                        options: [
                            { label: "yok", value: "" },
                            ...ortam.planlar.map((p) => ({
                                label: p,
                                value: p,
                            })),
                        ],
                    },
                    downsellBaslik: { type: "text", label: "Downsell başlığı" },
                    downsellMetin: {
                        type: "text",
                        label: "Downsell metni (isteğe bağlı)",
                    },
                    downsellDugme: { type: "text", label: "Downsell düğmesi" },
                    sartOnayi: {
                        type: "radio",
                        label: "Şart onay kutusu (kullanım şartları adresi gerekir)",
                        options: [
                            { label: "Yok", value: "hayir" },
                            { label: "Var", value: "evet" },
                        ],
                    },
                    onerilen: {
                        type: "select",
                        label: "Önerilen (seçicide)",
                        options: [
                            { label: "yok", value: "" },
                            ...ortam.planlar.map((p) => ({
                                label: p,
                                value: p,
                            })),
                        ],
                    },
                    faydalar: {
                        type: "array",
                        label: "Faydalar (en çok 6, isteğe bağlı)",
                        max: 6,
                        getItemSummary: (f: { metin: string }) =>
                            f.metin || "fayda",
                        defaultItemProps: { metin: "" },
                        arrayFields: {
                            metin: { type: "text", label: "Fayda" },
                        },
                    },
                    sartlar: {
                        type: "text",
                        label: "Kullanım şartları adresi (https://, isteğe bağlı)",
                    },
                    gizlilik: {
                        type: "text",
                        label: "Gizlilik politikası adresi (https://, isteğe bağlı)",
                    },
                    iptal: {
                        type: "text",
                        label: "İptal ve iade adresi (https://, isteğe bağlı)",
                    },
                },
                defaultProps: {
                    kimlik: "paywall",
                    baslik: "Pro",
                    metin: "",
                    dugme: "Abone ol",
                    ilerlemeGoster: true,
                    plan: "monthly",
                    planlar: [],
                    onerilen: "",
                    guvenceler: [],
                    sartOnayi: "hayir",
                    downsellPlan: "",
                    downsellBaslik: "",
                    downsellMetin: "",
                    downsellDugme: "",
                    faydalar: [],
                    sartlar: "",
                    gizlilik: "",
                    iptal: "",
                },
                render: ({
                    baslik,
                    metin,
                    dugme,
                    plan,
                    planlar,
                    onerilen,
                    guvenceler,
                    sartOnayi,
                    faydalar,
                    sartlar,
                    gizlilik,
                    iptal,
                }) => (
                    <div style={kart}>
                        <Paywall
                            baslik={baslik}
                            metin={metin}
                            dugme={dugme}
                            // M2a: önizlemede fiyat yok (Stripe'tan huni okur); kartlar plan anahtarıyla.
                            planlar={(planlar ?? [])
                                .filter((p) => p.plan)
                                .map((p) => ({
                                    anahtar: p.plan,
                                    ad: p.plan,
                                    onerilen: p.plan === onerilen,
                                }))}
                            secili={plan}
                            guvenceler={(guvenceler ?? []).map((g) => g.rozet)}
                            sartOnayi={
                                sartOnayi === "evet"
                                    ? { onay: false, onDegistir: () => {} }
                                    : undefined
                            }
                            faydalar={(faydalar ?? [])
                                .map((f) => f.metin)
                                .filter(Boolean)}
                            sartlar={sartlar || undefined}
                            gizlilik={gizlilik || undefined}
                            iptal={iptal || undefined}
                        />
                    </div>
                ),
            },
            // Mobil görünüm 4: düğmesiz; süre dolunca huni sıradakine geçer
            // (önizlemede beklemez). Son adım ödeme olmalı (M2c), yükleme son olamaz.
            Yukleme: {
                label: "Plan hazırlanıyor (yükleme)",
                resolveData: eklenince,
                fields: {
                    kimlik: kimlikAlani,
                    baslik: { type: "text", label: "Başlık" },
                    maddeler: {
                        type: "array",
                        label: "Maddeler (en çok 5, sırayla işaretlenir)",
                        max: 5,
                        getItemSummary: (m: { metin: string }) =>
                            m.metin || "madde",
                        defaultItemProps: { metin: "" },
                        arrayFields: {
                            metin: { type: "text", label: "Metin" },
                        },
                    },
                    sure: {
                        type: "number",
                        label: "Süre (saniye, 2–10)",
                        min: 2,
                        max: 10,
                    },
                    ilerlemeGoster: ilerlemeAlani,
                },
                defaultProps: {
                    kimlik: "hazirlik",
                    baslik: "Planın hazırlanıyor",
                    maddeler: [
                        { metin: "Cevapların inceleniyor" },
                        { metin: "Sana uygun plan seçiliyor" },
                    ],
                    sure: 4,
                    ilerlemeGoster: true,
                },
                render: ({ baslik, maddeler, sure }) => (
                    <div style={kart}>
                        <Yukleme
                            baslik={baslik}
                            maddeler={(maddeler ?? []).map((m) => m.metin)}
                            sure={Number(sure)}
                        />
                    </div>
                ),
            },
        },
    };
    // Ç91: bloğun koşullu değerinde, bloğun EKRANINDAN önceki ekranların
    // cevapları (Puck yuvadaki bloğa üst bileşeni `parent` olarak verir).
    for (const ad of DEGERLI_BLOKLAR) {
        // Bileşen yapılandırmaları türce ayrı (prop'lar farklı); burada yalnız
        // ortak koşullu değer alanına dokunulur.
        const bilesen = yap.components[ad] as unknown as {
            resolveFields?: (
                veri: unknown,
                p: {
                    fields: Record<string, unknown>;
                    appState: AppState;
                    parent: { props: { id?: unknown } } | null;
                },
            ) => Record<string, unknown>;
        };
        bilesen.resolveFields = (_, { fields, appState, parent }) => ({
            ...fields,
            degerler: degerSecenekli(
                fields["degerler"] as ReturnType<typeof degerAlani>,
                ozellikSecenekleri(
                    appState,
                    String(parent?.props.id ?? ""),
                    false,
                    ortam.ozellikler,
                ),
            ),
        });
    }
    return yap;
}
