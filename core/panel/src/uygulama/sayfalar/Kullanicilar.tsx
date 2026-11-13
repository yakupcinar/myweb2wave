// Aboneler (P5, CRM): abonelik başına satır — kim, durumu, nereden geldi,
// son ödemesi. Arama, durum, sayfa ve sıralama adreste
// (?ara=&durum=&sayfa=&sirala=&yon=); P7a2'den beri hepsi sunucuda, 25'er satır.
// M6a (A19): satırda dönem sonunda iptal / geri al ve son faturanın iadesi —
// onay satırın altında açılır (tarayıcı iletişim kutusu yok).
// M6c (A28): duraklat (1–3 ay, onayda seçilir) / sürdür.
// P7a: görünüm onaylı taslakla bire bir (p7a-aboneler.md) — durum Türkçe ve
// tarihli, durum süzgeci bölmeli seçici, onay sonucu anlatır.
import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from "@tanstack/react-query";
import { Fragment, useState, type ReactNode } from "react";
import { useSearchParams } from "react-router";
import { api } from "../api";
import { ekliTarih, kisaTarih, tutarlar, uzunTarih } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { bolmeliSinif, bolmeSinifi } from "../ui/bolmeli";
import { cn } from "../ui/cn";
import { Dugme, dugmeSinifi } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Sayfalama } from "../ui/Sayfalama";
import { Tablo, Td, Th } from "../ui/Tablo";

type Liste = Awaited<ReturnType<typeof listeAl>>;
type Satir = Liste["satirlar"][number];
type Sutun = "email" | "durum" | "kaynak" | "sonOdeme";
type Sira = { sutun: Sutun; artan: boolean } | null;

async function listeAl(ara: string, durum: string, sayfa: number, sira: Sira) {
    const { data } = await api.GET("/panel/api/kullanicilar", {
        params: {
            query: {
                ara,
                durum,
                sayfa,
                ...(sira
                    ? {
                          sirala: sira.sutun,
                          yon: sira.artan
                              ? ("artan" as const)
                              : ("azalan" as const),
                      }
                    : {}),
            },
        },
    });
    if (!data) throw new Error("kullanıcılar alınamadı");
    return data;
}

const SUTUNLAR: [Sutun, string][] = [
    ["email", "Abone"],
    ["durum", "Durum"],
    ["kaynak", "Geldiği yer"],
    ["sonOdeme", "Son ödeme"],
];

// Stripe durumu → süzgeç. "Ödeme sorunlu" past_due; unpaid az görülür, Tümü'nde.
const DURUMLAR: [string, string][] = [
    ["", "Tümü"],
    ["active", "Aktif"],
    ["trialing", "Denemede"],
    ["past_due", "Ödeme sorunlu"],
    ["canceled", "Bitti"],
];

const SURER = ["active", "trialing", "past_due"];

/** Durum noktası rengi + Türkçe, tarihli açıklama. */
function durumBilgisi(s: Satir): [string, string] {
    if (s.duraklatildi && SURER.includes(s.durum))
        return [
            "bg-uyari",
            s.duraklatmaBitis
                ? `Duraklatıldı · ${ekliTarih(s.duraklatmaBitis, "e")} kadar`
                : "Duraklatıldı",
        ];
    if (s.iptalDonemSonu && SURER.includes(s.durum))
        return [
            "bg-uyari",
            s.donemSonu
                ? `İptal edildi · ${ekliTarih(s.donemSonu, "e")} kadar açık`
                : "İptal edildi",
        ];
    switch (s.durum) {
        case "active":
            return ["bg-iyi", "Aktif"];
        case "trialing":
            return [
                "bg-vurgu",
                s.donemSonu
                    ? `Denemede · ${ekliTarih(s.donemSonu, "de")} biter`
                    : "Denemede",
            ];
        case "past_due":
        case "unpaid":
            return ["bg-tehlike", "Ödeme sorunlu"];
        case "canceled":
        case "incomplete_expired":
            return ["bg-soluk", "Bitti"];
        default:
            return ["bg-soluk", "Bekliyor"];
    }
}

const tutar = (paraBirimi: string, t: number): string =>
    tutarlar([{ paraBirimi, tutar: t }]);

export function Kullanicilar(): ReactNode {
    const [adres, setAdres] = useSearchParams();
    const ara = adres.get("ara") ?? "";
    const durum = adres.get("durum") ?? "";
    const sayfa = Math.max(1, Number(adres.get("sayfa")) || 1);
    const sutun = SUTUNLAR.find(([s]) => s === adres.get("sirala"))?.[0];
    const sira: Sira = sutun
        ? { sutun, artan: adres.get("yon") !== "azalan" }
        : null;
    const [taslak, setTaslak] = useState(ara);
    const sorgu = useQuery({
        queryKey: ["kullanicilar", ara, durum, sayfa, sira],
        queryFn: () => listeAl(ara, durum, sayfa, sira),
        // Yeni sayfa yüklenirken önceki sonuç kalır — tablo boşalıp zıplamasın.
        placeholderData: keepPreviousData,
    });
    // Süzgeç ya da sıra değişince ilk sayfaya dönülür (sayfa 4 yeni listede olmayabilir).
    const sec = (
        degisen: Partial<{
            ara: string;
            durum: string;
            sayfa: number;
            sira: Sira;
        }>,
    ): void => {
        const y = { ara, durum, sira, sayfa: 1, ...degisen };
        setAdres(
            Object.fromEntries(
                Object.entries({
                    ara: y.ara.trim(),
                    durum: y.durum,
                    sayfa: y.sayfa > 1 ? String(y.sayfa) : "",
                    sirala: y.sira?.sutun ?? "",
                    yon: y.sira && !y.sira.artan ? "azalan" : "",
                }).filter(([, v]) => v),
            ),
        );
    };

    return (
        <>
            <SayfaBasi
                baslik="Aboneler"
                aciklama="Kimin aboneliği sürüyor, kim nereden geldi. Tam e-posta adresi yalnız Stripe'ta görünür."
            >
                {/* R2 (A29): o anki arama, durum ve sırayla; sayfasız, e-posta maskeli. */}
                <a
                    href={`/panel/api/kullanicilar.csv?${new URLSearchParams(
                        Object.entries({
                            ara,
                            durum,
                            sirala: sira?.sutun ?? "",
                            yon: sira && !sira.artan ? "azalan" : "",
                        }).filter(([, v]) => v),
                    ).toString()}`}
                    download
                    className={dugmeSinifi()}
                >
                    CSV indir
                </a>
            </SayfaBasi>
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <form
                    role="search"
                    className="flex min-w-0 flex-[1_1_320px]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        sec({ ara: taslak });
                    }}
                >
                    <label className="flex min-h-10 w-full items-center gap-2 rounded-[10px] border border-kenar-2 bg-yuzey px-3 focus-within:outline-2 focus-within:outline-vurgu">
                        <svg
                            aria-hidden="true"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            className="shrink-0 text-soluk"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-4-4" />
                        </svg>
                        <span className="sr-only">
                            E-posta ya da kampanya ara
                        </span>
                        <input
                            type="search"
                            value={taslak}
                            maxLength={100}
                            placeholder="E-posta ya da kampanya ara"
                            onChange={(e) => setTaslak(e.currentTarget.value)}
                            className="min-w-0 flex-1 bg-transparent text-metin outline-none placeholder:text-soluk"
                        />
                    </label>
                </form>
                <div role="group" aria-label="Durum" className={bolmeliSinif}>
                    {DURUMLAR.map(([d, ad]) => (
                        <button
                            key={d}
                            type="button"
                            aria-pressed={durum === d}
                            className={cn(bolmeSinifi, "px-3.5 py-1.5")}
                            onClick={() => sec({ durum: d })}
                        >
                            {ad}
                        </button>
                    ))}
                </div>
            </div>
            {sorgu.isError ? (
                <p role="alert" className="text-tehlike">
                    Aboneler yüklenemedi.
                </p>
            ) : sorgu.data ? (
                <Liste
                    liste={sorgu.data}
                    sira={sira}
                    sirala={(s) => sec({ sira: s })}
                    sayfaSec={(n) => sec({ sayfa: n })}
                />
            ) : (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            )}
        </>
    );
}

type Is = "iptal" | "geriAl" | "iade" | "duraklat" | "surdur";

const AYLAR = [1, 2, 3] as const;
type Ay = (typeof AYLAR)[number];

const hucre = "px-[18px] py-3.5";
/**
 * P7g (Ç136): telefonda aynı satır karta döner — e-posta solda, durum sağda,
 * altında geldiği yer, son ödeme ve işlemler. Tek DOM (ikinci bir kart
 * listesi yok): testler ve ekran okuyucu tek kaynak görür. Masaüstü tablo
 * olduğu gibi.
 */
const kartSatir =
    "max-md:grid max-md:grid-cols-[minmax(0,1fr)_auto] max-md:gap-x-3 max-md:gap-y-2 max-md:border-b max-md:border-kenar max-md:px-[18px] max-md:py-3.5 max-md:last:border-b-0";
const kartHucre = "max-md:block max-md:border-0 max-md:p-0";
const kartTam = "max-md:col-span-2 max-md:whitespace-normal";

function Liste({
    liste,
    sira,
    sirala,
    sayfaSec,
}: {
    liste: Liste;
    sira: Sira;
    sirala: (s: Sira) => void;
    sayfaSec: (sayfa: number) => void;
}): ReactNode {
    const istemci = useQueryClient();
    const { satirlar } = liste;
    const [onay, setOnay] = useState<{ abonelik: string; is: Is } | null>(null);
    const [hata, setHata] = useState<{
        abonelik: string;
        metin: string;
    } | null>(null);
    const [calisiyor, setCalisiyor] = useState(false);
    const yap = async (abonelik: string, is: Is, ay: Ay): Promise<void> => {
        setHata(null);
        setCalisiyor(true);
        const yol = {
            params: {
                path: { abonelik },
                header: { "X-CSRF-Token": "" },
            },
        };
        const { error, response } =
            is === "duraklat"
                ? await api.POST("/panel/api/abonelikler/{abonelik}/duraklat", {
                      ...yol,
                      body: { ay },
                  })
                : is === "surdur"
                  ? await api.DELETE(
                        "/panel/api/abonelikler/{abonelik}/duraklat",
                        yol,
                    )
                  : is === "iade"
                    ? await api.POST(
                          "/panel/api/abonelikler/{abonelik}/iade",
                          yol,
                      )
                    : is === "iptal"
                      ? await api.POST(
                            "/panel/api/abonelikler/{abonelik}/iptal",
                            yol,
                        )
                      : await api.DELETE(
                            "/panel/api/abonelikler/{abonelik}/iptal",
                            yol,
                        );
        setCalisiyor(false);
        setOnay(null);
        if (response.status !== 204)
            return setHata({
                abonelik,
                metin: (error?.hatalar ?? ["İşlem yapılamadı."]).join(" "),
            });
        await istemci.invalidateQueries({ queryKey: ["kullanicilar"] });
    };

    return (
        <>
            <Kart aria-label="Abonelikler" className="overflow-hidden">
                <Tablo className="max-md:block md:min-w-[860px]">
                    <thead className="max-md:hidden">
                        <tr>
                            {SUTUNLAR.map(([sutun, ad]) => (
                                <Th
                                    key={sutun}
                                    className="px-[18px]"
                                    aria-sort={
                                        sira?.sutun === sutun
                                            ? sira.artan
                                                ? "ascending"
                                                : "descending"
                                            : undefined
                                    }
                                >
                                    <button
                                        type="button"
                                        className="hover:text-metin"
                                        onClick={() =>
                                            sirala({
                                                sutun,
                                                artan:
                                                    sira?.sutun === sutun
                                                        ? !sira.artan
                                                        : true,
                                            })
                                        }
                                    >
                                        {ad}
                                    </button>
                                </Th>
                            ))}
                            <Th className="px-[18px] text-right">İşlem</Th>
                        </tr>
                    </thead>
                    <tbody className="max-md:block">
                        {satirlar.map((s) => (
                            <Fragment key={s.abonelik}>
                                <SatirGorunumu
                                    s={s}
                                    sec={(is) => {
                                        setHata(null);
                                        setOnay({ abonelik: s.abonelik, is });
                                    }}
                                />
                                {onay?.abonelik === s.abonelik ? (
                                    <OnaySatiri
                                        s={s}
                                        is={onay.is}
                                        calisiyor={calisiyor}
                                        vazgec={() => setOnay(null)}
                                        onayla={(ay) =>
                                            void yap(s.abonelik, onay.is, ay)
                                        }
                                    />
                                ) : null}
                                {hata?.abonelik === s.abonelik ? (
                                    <tr className="max-md:block">
                                        <Td
                                            colSpan={5}
                                            className={cn(
                                                hucre,
                                                "whitespace-normal max-md:block",
                                            )}
                                        >
                                            <span
                                                role="alert"
                                                className="text-tehlike"
                                            >
                                                {hata.metin}
                                            </span>
                                        </Td>
                                    </tr>
                                ) : null}
                            </Fragment>
                        ))}
                        {satirlar.length ? null : (
                            <tr className="max-md:block">
                                <Td
                                    colSpan={5}
                                    className={cn(
                                        hucre,
                                        "text-soluk max-md:block",
                                    )}
                                >
                                    Eşleşen abonelik yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <Sayfalama
                sayfa={liste.sayfa}
                boyut={liste.boyut}
                toplam={liste.toplam}
                birim="abonelik"
                sec={sayfaSec}
            />
        </>
    );
}

/** Kalan iade edilebilir tutar (ana birim); 0 → iade düğmesi yok. */
const kalan = (s: Satir): number =>
    s.sonFatura
        ? Math.round((s.sonFatura.tutar - s.sonFatura.iade) * 100) / 100
        : 0;

function SatirGorunumu({
    s,
    sec,
}: {
    s: Satir;
    sec: (is: Is) => void;
}): ReactNode {
    const [nokta, durumMetni] = durumBilgisi(s);
    const alt = [
        // Ödeme anı yoksa (olay henüz gelmedi) tarih iddia edilmez.
        s.odeme ? `${ekliTarih(s.odeme, "den")} beri` : null,
        s.bagli
            ? `uygulamaya bağlı (${s.app ?? "?"})`
            : s.kisi
              ? "kayıtlı"
              : null,
    ].filter(Boolean);
    const nereden = [s.kampanya, s.huni].filter(Boolean).join(" · ");
    const f = s.sonFatura;
    const iadeEdilebilir = kalan(s);
    const dugme = "min-h-8 px-3 font-normal";
    // Telefonda işlemsiz kartın altında boş satır kalmasın.
    const islemVar =
        !!s.duraklatildi ||
        !!s.iptalDonemSonu ||
        SURER.includes(s.durum) ||
        (!!f && iadeEdilebilir > 0);

    return (
        <tr data-abonelik={s.abonelik} className={kartSatir}>
            <Td className={cn(hucre, kartHucre, "max-md:whitespace-normal")}>
                <div className="flex flex-col gap-0.5">
                    <span className="max-md:break-all">{s.email ?? "—"}</span>
                    <span className="text-xs text-soluk">
                        {alt.join(" · ")}
                    </span>
                </div>
            </Td>
            {/* M6c: tarihli durum uzun ("Duraklatıldı · 12 Ocak 2027'ye kadar"); tek satırda tablo karttan taşıyordu. */}
            <Td
                className={cn(
                    hucre,
                    kartHucre,
                    "whitespace-normal max-md:max-w-44 max-md:text-right",
                )}
            >
                <span
                    className="inline-flex items-center gap-1.5 text-[13px]"
                    title={s.durum || undefined}
                >
                    <span
                        aria-hidden="true"
                        className={cn("size-2 shrink-0 rounded-full", nokta)}
                    />
                    {durumMetni}
                </span>
            </Td>
            <Td className={cn(hucre, kartHucre, kartTam)}>
                <div className="flex flex-col gap-0.5">
                    <span className="flex items-center gap-1.5">
                        {s.kaynak
                            ? `${s.kaynak}${s.ortam ? ` / ${s.ortam}` : ""}`
                            : "Doğrudan"}
                        {s.reklam ? <Rozet tur="uyari">reklam</Rozet> : null}
                    </span>
                    <span className="text-xs text-soluk">{nereden || "—"}</span>
                </div>
            </Td>
            <Td className={cn(hucre, kartHucre, kartTam)}>
                {f ? (
                    <div className="flex flex-col gap-0.5">
                        <span>
                            {tutar(f.paraBirimi, f.tutar)}
                            {f.odendi ? ` · ${kisaTarih(f.odendi)}` : ""}
                        </span>
                        {f.iade > 0 ? (
                            <span className="text-xs text-soluk">
                                {iadeEdilebilir > 0
                                    ? `${tutar(f.paraBirimi, f.iade)} iade edildi`
                                    : "İade edildi"}
                            </span>
                        ) : null}
                    </div>
                ) : (
                    <span className="text-soluk">
                        {/* Telefonda sütun başlığı yok; ne olduğunu söyle. */}
                        <span className="md:hidden">Son ödeme henüz yok</span>
                        <span className="max-md:hidden">Henüz yok</span>
                    </span>
                )}
            </Td>
            <Td
                className={cn(
                    hucre,
                    kartHucre,
                    kartTam,
                    "text-right max-md:text-left",
                    !islemVar && "max-md:hidden",
                )}
            >
                <div className="inline-flex flex-wrap gap-2">
                    {s.iptalDonemSonu ? (
                        <Dugme
                            className={cn(dugme, "text-vurgu")}
                            onClick={() => sec("geriAl")}
                        >
                            İptali geri al
                        </Dugme>
                    ) : SURER.includes(s.durum) ? (
                        <Dugme className={dugme} onClick={() => sec("iptal")}>
                            {s.durum === "trialing"
                                ? "Deneme sonunda iptal et"
                                : "Dönem sonunda iptal et"}
                        </Dugme>
                    ) : null}
                    {s.duraklatildi ? (
                        <Dugme
                            className={cn(dugme, "text-vurgu")}
                            onClick={() => sec("surdur")}
                        >
                            Sürdür
                        </Dugme>
                    ) : SURER.includes(s.durum) && !s.iptalDonemSonu ? (
                        <Dugme
                            className={dugme}
                            onClick={() => sec("duraklat")}
                        >
                            Duraklat
                        </Dugme>
                    ) : null}
                    {f && iadeEdilebilir > 0 ? (
                        <Dugme
                            tur="tehlike"
                            className={dugme}
                            onClick={() => sec("iade")}
                        >
                            {tutar(f.paraBirimi, iadeEdilebilir)} iade et
                        </Dugme>
                    ) : null}
                </div>
            </Td>
        </tr>
    );
}

/** Onay satırı: ne olacağını söyler; geri alınamaz adım kırmızı (A19). */
function OnaySatiri({
    s,
    is,
    calisiyor,
    vazgec,
    onayla,
}: {
    s: Satir;
    is: Is;
    calisiyor: boolean;
    vazgec: () => void;
    onayla: (ay: Ay) => void;
}): ReactNode {
    const [ay, setAy] = useState<Ay>(1);
    const kim = s.email ?? "Abone";
    const deneme = s.durum === "trialing";
    const [soru, aciklama, dugme]: [string, string, string] =
        is === "duraklat"
            ? [
                  "Kaç ay duraklatılsın?",
                  `${kim} · bu sürede ücret çekilmez ve uygulamaya erişemez; süre bitince kendiliğinden sürer.`,
                  "Duraklat",
              ]
            : is === "surdur"
              ? [
                    "Abonelik sürdürülsün mü?",
                    `${kim} · erişim hemen açılır; tahsilat sonraki yenilemede başlar.`,
                    "Sürdür",
                ]
              : is === "iade"
                ? [
                      `${tutar(s.sonFatura?.paraBirimi ?? "EUR", kalan(s))} iade edilsin mi?`,
                      `${kim} · ${s.sonFatura?.odendi ? `${uzunTarih(s.sonFatura.odendi)} faturası` : "son fatura"}. Geri alınamaz; abonelik sürer.`,
                      "İade et",
                  ]
                : is === "iptal"
                  ? [
                        deneme
                            ? "Deneme sonunda iptal edilsin mi?"
                            : "Dönem sonunda iptal edilsin mi?",
                        `${kim} · ${s.donemSonu ? `${ekliTarih(s.donemSonu, "e")} kadar` : "Dönem sonuna kadar"} açık kalır, sonra ${deneme ? "ücret çekilmez" : "yenilenmez"}. O güne dek geri alınabilir.`,
                        "İptal et",
                    ]
                  : [
                        "İptal geri alınsın mı?",
                        `${kim} · abonelik dönem sonunda yenilenmeye devam eder.`,
                        "Geri al",
                    ];
    return (
        <tr className="bg-zemin/50 max-md:block">
            <Td
                colSpan={5}
                className={cn(hucre, "whitespace-normal max-md:block")}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span>
                        <span className="font-semibold">{soru}</span>{" "}
                        <span className="text-soluk">{aciklama}</span>
                    </span>
                    <span className="inline-flex flex-wrap gap-2">
                        {is === "duraklat" ? (
                            <span
                                role="group"
                                aria-label="Süre"
                                className={bolmeliSinif}
                            >
                                {AYLAR.map((a) => (
                                    <button
                                        key={a}
                                        type="button"
                                        aria-pressed={a === ay}
                                        onClick={() => setAy(a)}
                                        className={bolmeSinifi}
                                    >
                                        {a} ay
                                    </button>
                                ))}
                            </span>
                        ) : null}
                        <Dugme
                            tur="sade"
                            className="min-h-8"
                            disabled={calisiyor}
                            onClick={vazgec}
                        >
                            Vazgeç
                        </Dugme>
                        <Dugme
                            tur={
                                is === "geriAl" || is === "surdur"
                                    ? "ana"
                                    : "yikici"
                            }
                            className="min-h-8"
                            disabled={calisiyor}
                            onClick={() => onayla(ay)}
                        >
                            {dugme}
                        </Dugme>
                    </span>
                </div>
            </Td>
        </tr>
    );
}
