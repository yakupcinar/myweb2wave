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
    Paywall,
    Yukleme,
} from "../../../huni-bilesenleri/bilesenler";
import "../../../huni-bilesenleri/huni.css";
import {
    kontrast,
    temaDegiskenleri,
    VARSAYILAN_TEMA,
} from "../../../huni-bilesenleri/tema";
import { BLOK_BILESENLERI, kokOnAyarlari, kokZemini } from "./donustur";
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
            ],
        },
        yanitZorunlu: {
            type: "radio",
            label: "Serbest yanıt zorunluluğu",
            options: [
                { label: "İsteğe bağlı", value: "istege_bagli" },
                { label: "Zorunlu", value: "zorunlu" },
            ],
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

export function yapilandirma(ortam: Ortam): Config<Bilesenler, KokProps> {
    const soru = soruAlanlari(ortam);
    return {
        root: {
            fields: {
                sayfaBasligi: {
                    type: "text",
                    label: "Sayfa başlığı (tarayıcı sekmesi)",
                },
                logo: gorselAlani(ortam, "Logo (isteğe bağlı)"),
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
                        <div className="huni">
                            {logo ? <Logo gorsel={logo} medyaKoku="" /> : null}
                            {children}
                        </div>
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
                },
                resolveFields: (veri, { fields, appState }) => ({
                    ...fields,
                    hedef: {
                        ...tanitimHedefAlani,
                        options: hedefSecenekleri(appState, veri.props.id),
                    },
                }),
                defaultProps: {
                    kimlik: "ekran",
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
                }) => (
                    <div style={kart}>
                        <Ekran
                            baslik={baslik}
                            gorsel={gorsel}
                            gorselMetni={gorselMetni}
                            duzen={duzen}
                            dugme={dugme}
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
                    const metin = veri.props.secimTuru === "metin";
                    return {
                        ...fields,
                        yanitZorunlu: {
                            ...fields.yanitZorunlu,
                            visible: metin,
                        },
                        duzen: { ...fields.duzen, visible: !metin },
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
                    duzen: "liste",
                    secenekler: VARSAYILAN_SECENEKLER,
                },
                render: ({ secimTuru, duzen, yanitZorunlu, secenekler }) => (
                    <Blok
                        blok={{
                            tur: "soru",
                            secimTuru,
                            duzen,
                            yanitZorunlu: yanitZorunlu === "zorunlu",
                            secenekler: secenekler ?? [],
                        }}
                        medyaKoku=""
                    />
                ),
            },
            BaslikBlogu: {
                label: "Ara başlık",
                fields: { metin: { type: "text", label: "Başlık" } },
                defaultProps: { metin: "Ara başlık" },
                render: ({ metin }) => (
                    <Blok blok={{ tur: "baslik", metin }} medyaKoku="" />
                ),
            },
            MetinBlogu: {
                label: "Paragraf",
                fields: { metin: { type: "textarea", label: "Metin" } },
                defaultProps: { metin: "Metni buraya yaz." },
                render: ({ metin }) => (
                    <Blok blok={{ tur: "metin", metin }} medyaKoku="" />
                ),
            },
            GorselBlogu: {
                label: "Görsel",
                fields: {
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
            Paywall: {
                label: "Ödeme (en sonda olmalı)",
                fields: {
                    kimlik: kimlikAlani,
                    baslik: { type: "text", label: "Başlık" },
                    metin: { type: "text", label: "Fiyat satırı" },
                    dugme: { type: "text", label: "Düğme" },
                    ilerlemeGoster: ilerlemeAlani,
                    plan: {
                        type: "select",
                        label: "Plan",
                        options: ortam.planlar.map((p) => ({
                            label: p,
                            value: p,
                        })) as { label: string; value: "monthly" }[],
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
                    faydalar: [],
                    sartlar: "",
                    gizlilik: "",
                    iptal: "",
                },
                render: ({
                    baslik,
                    metin,
                    dugme,
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
            // (önizlemede beklemez). Ödeme adımı en sonda olduğu için son olamaz.
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
}
