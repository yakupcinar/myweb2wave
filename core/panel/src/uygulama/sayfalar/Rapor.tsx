// Rapor (A6/2, B5): reklamdan mı geldi, ödedi mi; izin oranı; anket.
// Veri /panel/api/rapor (sözleşme PanelRaporu); büyüyen üç liste (ziyaretler,
// kaynaklar, serbest yanıtlar) kendi uçlarından, sayfalı (P7a2); hesaplama
// yalnız gösterim (toplam, yüzde, şerit) — sayımların kendisi çekirdekte.
// P7b: düzen onaylı taslakla bire bir (p7b-rapor.md) — Özet (önceki döneme
// göre), Ziyaretten aboneliğe, Anket, Kaynaklar, Reklam harcaması; günlük
// grafikler ve ayrıntılar altta (kullanıcı). P7i (A27): sayfa 6.604 px'e
// uzamıştı — web2wave gibi sekmeler (Genel bakış · Anket · Kaynaklar · Gelir ·
// Ziyaretler); sekme ve Anket'in hunisi adreste.
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { lazy, Suspense, useState, type ReactNode } from "react";
import { Link, useNavigate, useSearchParams } from "react-router";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { sayi, tutarlar, yuzde, zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { bolmeliSinif, bolmeSinifi } from "../ui/bolmeli";
import { cn } from "../ui/cn";
import { dugmeSinifi } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Sayfalama } from "../ui/Sayfalama";
import { Tablo, Td, Th } from "../ui/Tablo";

// P4: grafik kitaplığı (Recharts) ayrı parça — Huniler/düzenleyici indirmez.
const GunlukGrafikler = lazy(async () => ({
    default: (await import("./GunlukGrafikler")).GunlukGrafikler,
}));

type PanelRaporu = components["schemas"]["PanelRaporu"];
type Sayim = components["schemas"]["PanelSayimi"];
type Tutar = components["schemas"]["PanelTutar"];
type Gun = PanelRaporu["gun"];

const GUNLER: Gun[] = [7, 30, 90];

function gunOku(deger: string | null): Gun {
    const n = Number(deger);
    return GUNLER.find((g) => g === n) ?? 30;
}

const SEKMELER = [
    ["genel", "Genel bakış"],
    ["anket", "Anket"],
    ["kaynaklar", "Kaynaklar"],
    ["gelir", "Gelir"],
    ["ziyaretler", "Ziyaretler"],
] as const;
type Sekme = (typeof SEKMELER)[number][0];

function sekmeOku(deger: string | null): Sekme {
    return SEKMELER.find(([s]) => s === deger)?.[0] ?? "genel";
}

/** R1b: süzgeç alanları (çekirdek RaporSuzgeci::ALANLAR ile aynı sıra). */
const SUZGECLER = [
    "huni",
    "kaynak",
    "kampanya",
    "ulke",
    "deney",
    "varyant",
] as const;
type SuzgecAdi = (typeof SUZGECLER)[number];
type Suzgec = Partial<Record<SuzgecAdi, string>>;

/** Adresteki süzgeç (boşlar yok). */
function suzgecOku(sorgu: URLSearchParams): Suzgec {
    const s: Suzgec = {};
    for (const ad of SUZGECLER) {
        const v = sorgu.get(ad);
        if (v) s[ad] = v;
    }
    return s;
}

/**
 * Adres: gün, sekme, Anket hunisi ve süzgeç birbirini korur; sabit sırayla
 * yazılır, varsayılanlar (30 gün, Genel bakış, boş) yazılmaz.
 */
function adres(
    mevcut: URLSearchParams,
    degis: Record<string, string | null>,
): string {
    const p = new URLSearchParams();
    for (const ad of ["gun", "sekme", "anket", ...SUZGECLER]) {
        const v = ad in degis ? degis[ad] : mevcut.get(ad);
        if (
            v &&
            !(ad === "gun" && v === "30") &&
            !(ad === "sekme" && v === "genel")
        )
            p.set(ad, v);
    }
    const q = p.toString();
    return q ? `/panel?${q}` : "/panel";
}

const sekmeSinifi =
    "-mb-px shrink-0 border-b-2 border-transparent px-3 pb-2.5 text-[15px] whitespace-nowrap text-soluk hover:text-metin aria-[current=page]:border-vurgu aria-[current=page]:font-medium aria-[current=page]:text-metin";

export function Rapor(): ReactNode {
    const [sorgu] = useSearchParams();
    const gun = gunOku(sorgu.get("gun"));
    const sekme = sekmeOku(sorgu.get("sekme"));
    const suzgec = suzgecOku(sorgu);
    const { data, isError } = useQuery({
        queryKey: ["rapor", gun, suzgec],
        queryFn: async () => {
            const { data } = await api.GET("/panel/api/rapor", {
                params: { query: { gun, ...suzgec } },
            });
            if (!data) throw new Error("rapor alınamadı");
            return data;
        },
    });

    return (
        <>
            <SayfaBasi
                baslik="Rapor"
                aciklama="Ziyaretçilerin kaçı abone oldu ve ne kadar kazandırdı."
            >
                <nav aria-label="Zaman aralığı" className={bolmeliSinif}>
                    {GUNLER.map((g) => (
                        <Link
                            key={g}
                            to={adres(sorgu, { gun: String(g) })}
                            aria-current={g === gun ? "page" : undefined}
                            className={cn(bolmeSinifi, "px-3.5 py-1.5")}
                        >
                            {g} gün
                        </Link>
                    ))}
                </nav>
            </SayfaBasi>
            <nav
                aria-label="Rapor bölümleri"
                className="mb-7 flex gap-1 overflow-x-auto border-b border-kenar"
            >
                {SEKMELER.map(([s, ad]) => (
                    <Link
                        key={s}
                        to={adres(sorgu, { sekme: s })}
                        aria-current={s === sekme ? "page" : undefined}
                        className={sekmeSinifi}
                    >
                        {ad}
                    </Link>
                ))}
            </nav>
            <SuzgecSatiri gun={gun} suzgec={suzgec} />
            {isError ? (
                <p role="alert" className="text-tehlike">
                    Rapor yüklenemedi. Sayfayı yenileyin.
                </p>
            ) : !data ? (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            ) : (
                <RaporIcerik
                    r={data}
                    sekme={sekme}
                    huni={sorgu.get("anket")}
                    suzgec={suzgec}
                />
            )}
        </>
    );
}

const hapSinifi =
    "min-h-8 max-w-[220px] rounded-full border border-kenar-2 bg-yuzey px-3 text-[13px] text-metin";
// Seçili hap halkayla (çerçeve rengi ortak alan denetiminde border-kenar-2 kalsın).
const etkinHapSinifi = "bg-vurgu/10 font-medium text-vurgu ring-1 ring-vurgu";

const ulkeAdlari = new Intl.DisplayNames(["tr"], { type: "region" });

/**
 * R1b: süzgeç satırı — seçenekler dönemde görülen değerlerden (çekirdek
 * rapor/secenekler). Seçim adrese yazılır; rapor ve listeler aynı süzgeçle
 * istenir. Deney değişince varyant sıfırlanır.
 */
function SuzgecSatiri({
    gun,
    suzgec,
}: {
    gun: Gun;
    suzgec: Suzgec;
}): ReactNode {
    const [sorgu] = useSearchParams();
    const git = useNavigate();
    const { data } = useQuery({
        queryKey: ["rapor-secenekler", gun],
        queryFn: async () =>
            (
                await api.GET("/panel/api/rapor/secenekler", {
                    params: { query: { gun } },
                })
            ).data,
    });
    const degistir = (ad: SuzgecAdi, deger: string): void => {
        void git(
            adres(
                sorgu,
                ad === "deney"
                    ? { deney: deger || null, varyant: null }
                    : { [ad]: deger || null },
            ),
        );
    };
    const secim = (
        ad: SuzgecAdi,
        etiket: string,
        tumu: string,
        secenekler: string[],
        yazi: (v: string) => string = (v) => v,
    ): ReactNode => {
        const deger = suzgec[ad] ?? "";
        // Seçili değer bu dönemde yoksa da listede kalsın (kaybolup "Tümü" görünmesin).
        const liste =
            deger && !secenekler.includes(deger)
                ? [deger, ...secenekler]
                : secenekler;
        return (
            <select
                aria-label={etiket}
                value={deger}
                onChange={(e) => degistir(ad, e.currentTarget.value)}
                className={cn(hapSinifi, deger && etkinHapSinifi)}
            >
                <option value="">{tumu}</option>
                {liste.map((v) => (
                    <option key={v} value={v}>
                        {yazi(v)}
                    </option>
                ))}
            </select>
        );
    };
    const deneyler = data?.deneyler ?? [];
    const varyantlar =
        deneyler.find((d) => d.deney === suzgec.deney)?.varyantlar ?? [];
    return (
        <div
            role="group"
            aria-label="Süzgeç"
            className="-mt-3 mb-7 flex flex-wrap items-center gap-2"
        >
            {secim("huni", "Huni", "Tüm huniler", data?.huniler ?? [])}
            {secim("kaynak", "Kaynak", "Tüm kaynaklar", data?.kaynaklar ?? [])}
            {secim(
                "kampanya",
                "Kampanya",
                "Tüm kampanyalar",
                data?.kampanyalar ?? [],
            )}
            {secim(
                "ulke",
                "Ülke",
                "Tüm ülkeler",
                data?.ulkeler ?? [],
                (v) => ulkeAdlari.of(v) ?? v,
            )}
            {secim(
                "deney",
                "Deney",
                "Deney fark etmez",
                deneyler.map((d) => d.deney),
            )}
            {suzgec.deney
                ? secim("varyant", "Varyant", "Tüm varyantlar", varyantlar)
                : null}
            {Object.keys(suzgec).length ? (
                <button
                    type="button"
                    className="px-2 text-[13px] text-vurgu"
                    onClick={() =>
                        void git(
                            adres(
                                sorgu,
                                Object.fromEntries(
                                    SUZGECLER.map((a) => [a, null]),
                                ),
                            ),
                        )
                    }
                >
                    Süzgeçleri temizle
                </button>
            ) : null}
        </div>
    );
}

/** Taslaktaki bölüm: başlık (20 px), kısa açıklama, altında kart. */
function Bolum({
    baslik,
    aciklama,
    sag,
    children,
}: {
    baslik: string;
    aciklama?: ReactNode;
    sag?: ReactNode;
    children: ReactNode;
}): ReactNode {
    return (
        <section aria-label={baslik} className="flex flex-col gap-2.5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[20px] font-semibold">{baslik}</h2>
                {sag}
            </div>
            {aciklama ? (
                <p className="text-[13px] text-soluk">{aciklama}</p>
            ) : null}
            {children}
        </section>
    );
}

const topla = (a: Sayim, b: Sayim): Sayim => ({
    ziyaret: a.ziyaret + b.ziyaret,
    odemeye_gecti: a.odemeye_gecti + b.odemeye_gecti,
    deneme: a.deneme + b.deneme,
    odedi: a.odedi + b.odedi,
});

// Ç118: abone = abone olan ziyaret (deneme dahil) — deneylerle aynı tanım (A17).
const abone = (s: Pick<Sayim, "deneme" | "odedi">): number =>
    s.deneme + s.odedi;

export function RaporIcerik({
    r,
    sekme = "genel",
    huni = null,
    suzgec = {},
}: {
    r: PanelRaporu;
    sekme?: Sekme;
    huni?: string | null;
    suzgec?: Suzgec;
}): ReactNode {
    const { reklam, organik } = r.sayimlar;
    const toplam = topla(reklam, organik);
    const onceki = topla(r.onceki.sayimlar.reklam, r.onceki.sayimlar.organik);
    if (sekme === "anket")
        return (
            <div className="flex flex-col gap-7">
                {r.adimlar.length ? (
                    <Anket r={r} secili={huni} />
                ) : (
                    <p className="text-soluk">Bu aralıkta anket verisi yok.</p>
                )}
                <SerbestYanitlar
                    key={`s${r.gun}`}
                    gun={r.gun}
                    suzgec={suzgec}
                />
            </div>
        );
    if (sekme === "kaynaklar")
        return (
            <div className="flex flex-col gap-7">
                <Kaynaklar key={`k${r.gun}`} gun={r.gun} suzgec={suzgec} />
                <Izinler r={r} />
            </div>
        );
    if (sekme === "gelir")
        return (
            <div className="flex flex-col gap-7">
                <Kohort r={r} />
            </div>
        );
    if (sekme === "ziyaretler")
        return (
            <div className="flex flex-col gap-7">
                <Ziyaretler key={`z${r.gun}`} gun={r.gun} suzgec={suzgec} />
            </div>
        );
    return (
        <div className="flex flex-col gap-7">
            <Bolum baslik="Özet" aciklama={`Önceki ${r.gun} güne göre.`}>
                <Ozet r={r} toplam={toplam} onceki={onceki} />
            </Bolum>

            <Bolum baslik="Günlük">
                <Suspense
                    fallback={
                        <p role="status" className="text-soluk">
                            Grafikler yükleniyor…
                        </p>
                    }
                >
                    <GunlukGrafikler gunluk={r.gunluk} />
                </Suspense>
            </Bolum>

            <Bolum
                baslik="Ziyaretten aboneliğe"
                aciklama="Her adımda kaç kişi kaldı. Reklam: Meta / TikTok tıklaması ya da ücretli kampanya."
            >
                <Kart className="overflow-hidden">
                    <Tablo aria-label="Ziyaretten aboneliğe">
                        <thead>
                            <tr>
                                <Th>Adım</Th>
                                <Th className="text-right">Reklamdan</Th>
                                <Th className="text-right">Organik</Th>
                                <Th className="text-right">Toplam</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {(
                                [
                                    ["Ziyaret", (s: Sayim) => s.ziyaret],
                                    [
                                        "Ödeme sayfasını açtı",
                                        (s: Sayim) => s.odemeye_gecti,
                                    ],
                                    [
                                        "Ücretsiz denemeye başladı",
                                        (s: Sayim) => s.deneme,
                                    ],
                                    ["Ödeme yaptı", (s: Sayim) => s.odedi],
                                    ["Abone oldu", abone],
                                ] as const
                            ).map(([ad, deger]) => (
                                <tr key={ad}>
                                    <Td>
                                        {ad}
                                        {ad === "Ödeme yaptı" ? (
                                            <span className="text-soluk">
                                                {" "}
                                                · deneme sonrası dahil
                                            </span>
                                        ) : null}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(deger(reklam))}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(deger(organik))}
                                    </Td>
                                    <Td className="text-right font-semibold tabular-nums">
                                        {sayi(deger(toplam))}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Tablo>
                </Kart>
            </Bolum>

            <ReklamHarcamasi r={r} />
        </div>
    );
}

type Yon = "artti" | "azaldi" | "ayni" | "yok";

/** Değişim: yön oku + Türkçe yüzde; önceki dönem boşsa söylenir. */
function Degisim({ metin, yon }: { metin: string; yon: Yon }): ReactNode {
    return (
        <span
            className={cn(
                "text-[13px]",
                yon === "artti"
                    ? "font-medium text-iyi"
                    : yon === "azaldi"
                      ? "font-medium text-tehlike"
                      : "text-soluk",
            )}
        >
            {metin}
        </span>
    );
}

const YOK = { metin: "Önceki dönemde veri yok", yon: "yok" as Yon };
const KARSILASTIRMA_YOK = { metin: "Karşılaştırma yok", yon: "yok" as Yon };

/** Önceki döneme göre yüzde değişim ("↑ %12"); önceki 0 ise karşılaştırma yok. */
function degisim(simdi: number, once: number): { metin: string; yon: Yon } {
    if (once <= 0) return YOK;
    const fark = Math.round(((simdi - once) / once) * 100);
    if (fark === 0) return { metin: "Değişmedi", yon: "ayni" };
    return fark > 0
        ? { metin: `↑ %${fark}`, yon: "artti" }
        : { metin: `↓ %${-fark}`, yon: "azaldi" };
}

/** Tek para birimiyse tutarı; değilse null (farklı birimler karşılaştırılmaz). */
const tekTutar = (liste: readonly Tutar[]): Tutar | null =>
    liste.length === 1 ? (liste[0] ?? null) : null;

function Ozet({
    r,
    toplam,
    onceki,
}: {
    r: PanelRaporu;
    toplam: Sayim;
    onceki: Sayim;
}): ReactNode {
    const gelir = tekTutar(r.gelir);
    const gelirOnce = tekTutar(r.onceki.gelir);
    const ayni =
        gelir !== null &&
        gelirOnce !== null &&
        gelir.paraBirimi === gelirOnce.paraBirimi
            ? { simdi: gelir.tutar, once: gelirOnce.tutar }
            : null;
    const oran = (s: Sayim): number =>
        s.ziyaret > 0 ? (abone(s) / s.ziyaret) * 100 : 0;
    // Oran değişimi yüzde puanı olarak (oranın yüzdesi yanıltır).
    const puan = Math.round((oran(toplam) - oran(onceki)) * 10) / 10;
    const kartlar: [string, string, { metin: string; yon: Yon }][] = [
        [
            "Gelir",
            r.gelir.length ? tutarlar(r.gelir) : "—",
            ayni ? degisim(ayni.simdi, ayni.once) : KARSILASTIRMA_YOK,
        ],
        [
            "Yeni abone",
            sayi(abone(toplam)),
            degisim(abone(toplam), abone(onceki)),
        ],
        [
            "Ziyaretten aboneliğe",
            yuzde(abone(toplam), toplam.ziyaret),
            onceki.ziyaret <= 0
                ? YOK
                : puan === 0
                  ? { metin: "Değişmedi", yon: "ayni" }
                  : {
                        metin: `${puan > 0 ? "↑" : "↓"} ${String(Math.abs(puan)).replace(".", ",")} puan`,
                        yon: puan > 0 ? "artti" : "azaldi",
                    },
        ],
        [
            "Ziyaretçi başına gelir",
            r.gelir.length && toplam.ziyaret > 0
                ? tutarlar(
                      r.gelir.map((t) => ({
                          paraBirimi: t.paraBirimi,
                          tutar: t.tutar / toplam.ziyaret,
                      })),
                  )
                : "—",
            ayni && toplam.ziyaret > 0 && onceki.ziyaret > 0
                ? degisim(
                      ayni.simdi / toplam.ziyaret,
                      ayni.once / onceki.ziyaret,
                  )
                : KARSILASTIRMA_YOK,
        ],
    ];
    return (
        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))] gap-3">
            {kartlar.map(([ad, deger, d]) => (
                <Kart
                    key={ad}
                    aria-label={ad}
                    className="flex flex-col gap-1.5 px-[18px] py-4"
                >
                    <span className="text-[13px] text-soluk">{ad}</span>
                    <span className="text-[28px] font-semibold tracking-tight tabular-nums">
                        {deger}
                    </span>
                    <Degisim {...d} />
                </Kart>
            ))}
        </div>
    );
}

function Anket({
    r,
    secili,
}: {
    r: PanelRaporu;
    secili: string | null;
}): ReactNode {
    const [sorgu, setSorgu] = useSearchParams();
    // Huni başına adımlar; şerit ilk adıma göre, düşüş bir önceki adıma göre.
    const huniler = new Map<string, PanelRaporu["adimlar"]>();
    for (const a of r.adimlar) {
        const anahtar = `${a.app}/${a.huni}`;
        huniler.set(anahtar, [...(huniler.get(anahtar) ?? []), a]);
    }
    // P7i: tek huni gösterilir (beş huni alt alta 1.710 px'ti); varsayılan ilk
    // adımına en çok ziyaret gelen.
    const sirali = [...huniler].sort(
        ([, a], [, b]) => (b[0]?.ulasan ?? 0) - (a[0]?.ulasan ?? 0),
    );
    const anahtar =
        secili && huniler.has(secili) ? secili : (sirali[0]?.[0] ?? "");
    const adimlar = huniler.get(anahtar) ?? [];
    const [app, huniAdi] = [adimlar[0]?.app, adimlar[0]?.huni];
    const cevaplar = r.cevaplar.filter(
        (c) => c.app === app && c.huni === huniAdi,
    );
    const ad = (k: string, a: PanelRaporu["adimlar"]): string =>
        `${a[0]?.huni ?? k}${a[0] && a[0].app !== "keepchat" ? ` (${a[0].app})` : ""} · ${sayi(a[0]?.ulasan ?? 0)} ziyaret`;
    return (
        <>
            <Bolum
                baslik="Anket"
                aciklama="Ziyaretçilerin hangi ekranda ayrıldığı."
                sag={
                    huniler.size > 1 ? (
                        <select
                            aria-label="Anket hunisi"
                            value={anahtar}
                            onChange={(e) => {
                                const yeni = new URLSearchParams(sorgu);
                                yeni.set("anket", e.currentTarget.value);
                                setSorgu(yeni);
                            }}
                            className="min-h-9 rounded-lg border border-kenar-2 bg-yuzey px-2.5 text-[14px]"
                        >
                            {sirali.map(([k, a]) => (
                                <option key={k} value={k}>
                                    {ad(k, a)}
                                </option>
                            ))}
                        </select>
                    ) : null
                }
            >
                {[[anahtar, adimlar] as const].map(([anahtar, adimlar]) => {
                    const ilkAdim = adimlar[0];
                    const ilk = ilkAdim?.ulasan ?? 0;
                    return (
                        <Kart
                            key={anahtar}
                            aria-label={`Anket ${anahtar}`}
                            className="px-[18px] py-1.5"
                        >
                            {adimlar.map((a, i) => {
                                const once = adimlar[i - 1]?.ulasan ?? 0;
                                const oran =
                                    ilk > 0
                                        ? Math.min((a.ulasan / ilk) * 100, 100)
                                        : 0;
                                const dusus =
                                    i > 0 && once > 0
                                        ? Math.round(
                                              ((once - a.ulasan) / once) * 100,
                                          )
                                        : null;
                                return (
                                    <div
                                        key={a.sira}
                                        data-adim={a.adim}
                                        className="grid grid-cols-[minmax(0,140px)_minmax(0,1fr)_56px_56px] items-center gap-3.5 border-t border-kenar py-2.5 first:border-t-0 md:grid-cols-[220px_minmax(0,1fr)_70px_80px]"
                                    >
                                        <span className="truncate">
                                            {a.adim}
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            data-serit=""
                                            className="h-2 rounded bg-dolgu"
                                        >
                                            <span
                                                className="block h-2 rounded bg-vurgu"
                                                style={{
                                                    width: `${oran.toFixed(1)}%`,
                                                }}
                                            />
                                        </span>
                                        <span className="text-right tabular-nums">
                                            {sayi(a.ulasan)}
                                        </span>
                                        <span
                                            className={cn(
                                                "text-right text-[13px] tabular-nums",
                                                dusus && dusus > 0
                                                    ? "text-tehlike"
                                                    : "text-soluk",
                                            )}
                                        >
                                            {dusus === null
                                                ? "—"
                                                : dusus > 0
                                                  ? `−%${dusus}`
                                                  : `%${-dusus}`}
                                        </span>
                                    </div>
                                );
                            })}
                        </Kart>
                    );
                })}
            </Bolum>
            {cevaplar.length ? (
                <Bolum
                    baslik="Cevaplar"
                    aciklama="Her şıkkı kaç kişi seçti ve seçenlerin kaçı abone oldu (deneme dahil)."
                >
                    <Kart className="overflow-hidden">
                        <Tablo aria-label="Cevaplar">
                            <thead>
                                <tr>
                                    <Th>Soru</Th>
                                    <Th>Cevap</Th>
                                    <Th className="text-right">Seçen</Th>
                                    <Th className="text-right">Abone</Th>
                                    <Th className="text-right">Dönüşüm</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {cevaplar.map((c) => (
                                    <tr
                                        key={`${c.app}/${c.huni}/${c.soru}/${c.secenek}`}
                                    >
                                        <Td>{c.soru}</Td>
                                        <Td>{c.secenek}</Td>
                                        <Td className="text-right tabular-nums">
                                            {sayi(c.sayi)}
                                        </Td>
                                        <Td className="text-right tabular-nums">
                                            {sayi(c.abone)}
                                        </Td>
                                        {/* M5c: seçen ziyaretten abone olan (deneme dahil, A17). */}
                                        <Td className="text-right tabular-nums">
                                            {yuzde(c.abone, c.sayi)}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Tablo>
                    </Kart>
                </Bolum>
            ) : null}
        </>
    );
}

function ReklamHarcamasi({ r }: { r: PanelRaporu }): ReactNode {
    return (
        <Bolum
            baslik="Reklam harcaması"
            aciklama={
                r.kampanyalar.length
                    ? "Harcama Meta'dan (günlük çekilir); gelir aynı adlı utm_campaign ile Meta'dan gelen ziyaretlerin faturaları. ROAS hesabın para biriminde."
                    : undefined
            }
        >
            {r.kampanyalar.length ? (
                <Kart className="overflow-hidden">
                    <Tablo aria-label="Meta kampanyaları">
                        <thead>
                            <tr>
                                <Th>Kampanya</Th>
                                <Th className="text-right">Harcama</Th>
                                <Th className="text-right">Ziyaret</Th>
                                <Th className="text-right">Ödeyen</Th>
                                <Th className="text-right">Gelir</Th>
                                <Th className="text-right">ROAS</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {r.kampanyalar.map((k) => (
                                <tr
                                    key={`${k.kampanya}/${k.harcama.paraBirimi}`}
                                >
                                    <Td>{k.kampanya}</Td>
                                    <Td className="text-right tabular-nums">
                                        {tutarlar([k.harcama])}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(k.ziyaret)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(k.odedi)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {tutarlar(k.gelir)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {k.roas === null
                                            ? "—"
                                            : `${k.roas.toFixed(2).replace(".", ",")}×`}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Tablo>
                </Kart>
            ) : (
                // Harcama yoksa iki sebep olabilir; hangisi olduğu panelden bilinmez (Ç121).
                <Kart
                    aria-label="Meta harcaması yok"
                    className="flex flex-wrap items-center justify-between gap-4 p-7"
                >
                    <span className="flex flex-col gap-1">
                        <span className="text-[15px] font-semibold">
                            Bu dönemde Meta harcaması yok
                        </span>
                        <span className="text-soluk">
                            Meta reklam hesabı bağlı değilse harcama çekilmez.
                            Bağlayınca kampanya başına harcama ve getiri (ROAS)
                            burada görünür.
                        </span>
                    </span>
                    <Link
                        to="/panel/ayarlar"
                        className={cn(
                            dugmeSinifi({ tur: "ana" }),
                            "min-h-11 px-4.5 text-[14px]",
                        )}
                    >
                        Meta hesabını bağla
                    </Link>
                </Kart>
            )}
        </Bolum>
    );
}

function Kohort({ r }: { r: PanelRaporu }): ReactNode {
    return (
        <Bolum
            baslik="Kohort geliri"
            aciklama="Ziyaret haftası (UTC, pazartesi) · ziyaret başına birikimli gelir · — = pencere henüz dolmadı"
        >
            <Kart className="overflow-hidden">
                <Tablo aria-label="Kohort geliri">
                    <thead>
                        <tr>
                            <Th>Hafta</Th>
                            <Th className="text-right">Ziyaret</Th>
                            <Th className="text-right">İlk 24 saat</Th>
                            <Th className="text-right">7 gün</Th>
                            <Th className="text-right">30 gün</Th>
                            <Th className="text-right">60 gün</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {r.kohort.map((k) => (
                            <tr key={k.hafta}>
                                <Td>{k.hafta}</Td>
                                <Td className="text-right tabular-nums">
                                    {sayi(k.ziyaret)}
                                </Td>
                                {k.pencereler.map((p) => (
                                    <Td
                                        key={p.gun}
                                        className="text-right tabular-nums"
                                    >
                                        {p.olgun
                                            ? p.kisiBasi.length
                                                ? tutarlar(p.kisiBasi)
                                                : "0"
                                            : "—"}
                                    </Td>
                                ))}
                            </tr>
                        ))}
                        {r.kohort.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Bu aralıkta ziyaret yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
        </Bolum>
    );
}

/** Ç56 (3): yalnız sayı — kim kabul etti bilgisi yok. */
function Izinler({ r }: { r: PanelRaporu }): ReactNode {
    const toplam = r.izinler.kabul + r.izinler.red + r.izinler.secmeyen;
    return (
        <Bolum
            baslik="Reklam ölçümü izni"
            aciklama="Huniyi açanların ölçüm izni seçimi."
        >
            <div className="grid grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))] gap-3">
                {(
                    [
                        ["Kabul etti", r.izinler.kabul],
                        ["Reddetti", r.izinler.red],
                        ["Seçmedi", r.izinler.secmeyen],
                    ] as const
                ).map(([ad, n]) => (
                    <Kart
                        key={ad}
                        aria-label={ad}
                        className="flex flex-col gap-1.5 px-[18px] py-4"
                    >
                        <span className="text-[13px] text-soluk">{ad}</span>
                        <span className="text-[22px] font-semibold tabular-nums">
                            {sayi(n)}
                        </span>
                        <span className="text-[13px] text-soluk">
                            {yuzde(n, toplam)}
                        </span>
                    </Kart>
                ))}
            </div>
        </Bolum>
    );
}

/** Sayfalı liste kancası: sayfa yerel; gün değişince bileşen yeniden kurulur (key). */
function useListe<T>(
    anahtar: string,
    gun: Gun,
    suzgec: Suzgec,
    al: (sayfa: number) => Promise<T | undefined>,
) {
    const [sayfa, setSayfa] = useState(1);
    const { data } = useQuery({
        queryKey: ["rapor", gun, suzgec, anahtar, sayfa],
        queryFn: async () => {
            const veri = await al(sayfa);
            if (!veri) throw new Error("liste alınamadı");
            return veri;
        },
        // Yeni sayfa gelene dek önceki kalır — kart boşalıp zıplamasın.
        placeholderData: keepPreviousData,
    });
    return { data, setSayfa };
}

function Kaynaklar({ gun, suzgec }: { gun: Gun; suzgec: Suzgec }): ReactNode {
    const { data, setSayfa } = useListe(
        "kaynaklar",
        gun,
        suzgec,
        async (sayfa) =>
            (
                await api.GET("/panel/api/rapor/kaynaklar", {
                    params: { query: { gun, sayfa, ...suzgec } },
                })
            ).data,
    );
    // Bölüm veri gelince çizilir (boş tablo "ziyaret yok" demesin).
    if (!data) return null;
    return (
        <Bolum
            baslik="Kaynaklar"
            aciklama="Gelir, bu ziyaretçilerin bugüne kadar ödediği tutar (iadeler düşülmüş)."
        >
            <div>
                <Kart className="overflow-hidden">
                    <Tablo aria-label="Kaynaklar">
                        <thead>
                            <tr>
                                <Th>Kaynak</Th>
                                <Th>Kampanya</Th>
                                <Th className="text-right">Ziyaret</Th>
                                <Th className="text-right">Ödeyen</Th>
                                <Th className="text-right">Dönüşüm</Th>
                                <Th className="text-right">Gelir</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.satirlar.map((u) => (
                                <tr
                                    key={`${u.kaynak}/${u.ortam ?? ""}/${u.kampanya ?? ""}/${u.reklam}`}
                                >
                                    <Td>
                                        <span className="flex items-center gap-1.5">
                                            {u.kaynak}
                                            {u.ortam ? (
                                                <span className="text-soluk">
                                                    / {u.ortam}
                                                </span>
                                            ) : null}
                                            {u.reklam ? (
                                                <Rozet tur="uyari">
                                                    reklam
                                                </Rozet>
                                            ) : null}
                                        </span>
                                    </Td>
                                    <Td>{u.kampanya ?? "—"}</Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(u.ziyaret)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {sayi(u.odedi)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {yuzde(abone(u), u.ziyaret)}
                                    </Td>
                                    <Td className="text-right tabular-nums">
                                        {tutarlar(u.gelir)}
                                    </Td>
                                </tr>
                            ))}
                            {data.satirlar.length ? null : (
                                <tr>
                                    <Td colSpan={6} className="text-soluk">
                                        Bu aralıkta ziyaret yok.
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </Tablo>
                </Kart>
                <Sayfalama
                    sayfa={data.sayfa}
                    boyut={data.boyut}
                    toplam={data.toplam}
                    birim="kaynak"
                    sec={setSayfa}
                />
            </div>
        </Bolum>
    );
}

function Ziyaretler({ gun, suzgec }: { gun: Gun; suzgec: Suzgec }): ReactNode {
    const { data, setSayfa } = useListe(
        "ziyaretler",
        gun,
        suzgec,
        async (sayfa) =>
            (
                await api.GET("/panel/api/rapor/ziyaretler", {
                    params: { query: { gun, sayfa, ...suzgec } },
                })
            ).data,
    );
    if (!data) return null;
    return (
        <Bolum
            baslik="Ziyaretler"
            aciklama="En yeni önce. Zaman UTC; e-posta maskeli."
        >
            <div>
                <Kart className="overflow-hidden">
                    <Tablo aria-label="Ziyaretler">
                        <thead>
                            <tr>
                                {[
                                    "Zaman (UTC)",
                                    "Uygulama",
                                    "Huni",
                                    "Kaynak",
                                    "Kampanya",
                                    "Aşama",
                                    "Abonelik",
                                    "E-posta",
                                ].map((b) => (
                                    <Th key={b}>{b}</Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {data.satirlar.map((s, i) => (
                                <tr key={i}>
                                    <Td>{zaman(s.zaman)}</Td>
                                    <Td>{s.app}</Td>
                                    <Td>{s.huni ?? "—"}</Td>
                                    <Td>
                                        {s.reklam ? (
                                            <>
                                                <Rozet tur="uyari">
                                                    reklam
                                                </Rozet>{" "}
                                                ·{" "}
                                            </>
                                        ) : null}
                                        {s.kaynak}
                                    </Td>
                                    <Td>{s.kampanya ?? "—"}</Td>
                                    <Td>
                                        <Rozet
                                            tur={
                                                s.asama === "ödedi"
                                                    ? "iyi"
                                                    : "notr"
                                            }
                                        >
                                            {s.asama}
                                        </Rozet>
                                    </Td>
                                    <Td>{s.durum ?? "—"}</Td>
                                    <Td>{s.email ?? "—"}</Td>
                                </tr>
                            ))}
                            {data.satirlar.length ? null : (
                                <tr>
                                    <Td colSpan={8} className="text-soluk">
                                        Bu aralıkta ziyaret yok.
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </Tablo>
                </Kart>
                <Sayfalama
                    sayfa={data.sayfa}
                    boyut={data.boyut}
                    toplam={data.toplam}
                    birim="ziyaret"
                    sec={setSayfa}
                />
            </div>
        </Bolum>
    );
}

function SerbestYanitlar({
    gun,
    suzgec,
}: {
    gun: Gun;
    suzgec: Suzgec;
}): ReactNode {
    const { data, setSayfa } = useListe(
        "serbestYanitlar",
        gun,
        suzgec,
        async (sayfa) =>
            (
                await api.GET("/panel/api/rapor/serbestYanitlar", {
                    params: { query: { gun, sayfa, ...suzgec } },
                })
            ).data,
    );
    if (!data) return null;
    return (
        <Bolum
            baslik="Serbest yanıtlar"
            aciklama={`Son ${gun} gün, en yeni önce. 90 gün saklanır.`}
        >
            <div>
                <Kart className="overflow-hidden">
                    <Tablo aria-label="Serbest yanıtlar">
                        <thead>
                            <tr>
                                {[
                                    "Zaman (UTC)",
                                    "Uygulama",
                                    "Soru",
                                    "Yanıt",
                                ].map((b) => (
                                    <Th key={b}>{b}</Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {data.satirlar.map((c, i) => (
                                <tr key={i}>
                                    <Td>{zaman(c.zaman)}</Td>
                                    <Td>{c.app}</Td>
                                    <Td>{c.soru}</Td>
                                    <Td className="min-w-60 whitespace-normal">
                                        {c.cevap}
                                    </Td>
                                </tr>
                            ))}
                            {data.satirlar.length ? null : (
                                <tr>
                                    <Td colSpan={4} className="text-soluk">
                                        Henüz serbest yanıt yok.
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </Tablo>
                </Kart>
                <Sayfalama
                    sayfa={data.sayfa}
                    boyut={data.boyut}
                    toplam={data.toplam}
                    birim="yanıt"
                    sec={setSayfa}
                />
            </div>
        </Bolum>
    );
}
