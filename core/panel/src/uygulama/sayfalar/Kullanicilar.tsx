// Aboneler (P5, CRM): abonelik başına satır — kim, durumu, nereden geldi,
// son ödemesi. Arama ve durum adreste (?ara=&durum=), sunucuda süzülür;
// sıralama istemcide (en çok 200 satır — tablo kitaplığı gerekmedi).
// M6a (A19): satırda dönem sonunda iptal / geri al ve son faturanın iadesi —
// onay satırın altında açılır (tarayıcı iletişim kutusu yok).
// P7a: görünüm onaylı taslakla bire bir (p7a-aboneler.md) — durum Türkçe ve
// tarihli, durum süzgeci bölmeli seçici, onay sonucu anlatır.
import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from "@tanstack/react-query";
import { Fragment, useMemo, useState, type ReactNode } from "react";
import { useSearchParams } from "react-router";
import { api } from "../api";
import { ekliTarih, kisaTarih, tutarlar, uzunTarih } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { bolmeliSinif, bolmeSinifi } from "../ui/bolmeli";
import { cn } from "../ui/cn";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Satir = Awaited<ReturnType<typeof listeAl>>["satirlar"][number];
type Sutun = "email" | "durum" | "kaynak" | "sonOdeme";

async function listeAl(ara: string, durum: string) {
    const { data } = await api.GET("/panel/api/kullanicilar", {
        params: { query: { ara, durum } },
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
    const [taslak, setTaslak] = useState(ara);
    const sorgu = useQuery({
        queryKey: ["kullanicilar", ara, durum],
        queryFn: () => listeAl(ara, durum),
        // Yeni arama yüklenirken önceki sonuç kalır — tablo boşalıp zıplamasın.
        placeholderData: keepPreviousData,
    });
    const sec = (ara2: string, durum2: string): void =>
        setAdres(
            Object.fromEntries(
                Object.entries({ ara: ara2.trim(), durum: durum2 }).filter(
                    ([, v]) => v,
                ),
            ),
        );

    return (
        <>
            <SayfaBasi
                baslik="Aboneler"
                aciklama="Kimin aboneliği sürüyor, kim nereden geldi. Tam e-posta adresi yalnız Stripe'ta görünür."
            />
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <form
                    role="search"
                    className="flex min-w-0 flex-[1_1_320px]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        sec(taslak, durum);
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
                            onClick={() => sec(ara, d)}
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
                    satirlar={sorgu.data.satirlar}
                    azami={sorgu.data.azami}
                />
            ) : (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            )}
        </>
    );
}

type Is = "iptal" | "geriAl" | "iade";

const hucre = "px-[18px] py-3.5";

function Liste({
    satirlar,
    azami,
}: {
    satirlar: Satir[];
    azami: number;
}): ReactNode {
    const istemci = useQueryClient();
    // Varsayılan: sunucunun sırası (en yeni güncellenen önce).
    const [sira, setSira] = useState<{ sutun: Sutun; artan: boolean } | null>(
        null,
    );
    const [onay, setOnay] = useState<{ abonelik: string; is: Is } | null>(null);
    const [hata, setHata] = useState<{
        abonelik: string;
        metin: string;
    } | null>(null);
    const [calisiyor, setCalisiyor] = useState(false);
    const sirali = useMemo(() => {
        if (!sira) return satirlar;
        const deger = (s: Satir): string =>
            (sira.sutun === "sonOdeme"
                ? s.sonFatura?.odendi
                : sira.sutun === "durum"
                  ? durumBilgisi(s)[1]
                  : s[sira.sutun]) ?? "";
        return [...satirlar].sort(
            (a, b) =>
                (deger(a) < deger(b) ? -1 : deger(a) > deger(b) ? 1 : 0) *
                (sira.artan ? 1 : -1),
        );
    }, [satirlar, sira]);

    const yap = async (abonelik: string, is: Is): Promise<void> => {
        setHata(null);
        setCalisiyor(true);
        const yol = {
            params: {
                path: { abonelik },
                header: { "X-CSRF-Token": "" },
            },
        };
        const { error, response } =
            is === "iade"
                ? await api.POST("/panel/api/abonelikler/{abonelik}/iade", yol)
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
                <Tablo className="min-w-[860px]">
                    <thead>
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
                                            setSira((o) => ({
                                                sutun,
                                                artan:
                                                    o?.sutun === sutun
                                                        ? !o.artan
                                                        : true,
                                            }))
                                        }
                                    >
                                        {ad}
                                    </button>
                                </Th>
                            ))}
                            <Th className="px-[18px] text-right">İşlem</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {sirali.map((s) => (
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
                                        onayla={() =>
                                            void yap(s.abonelik, onay.is)
                                        }
                                    />
                                ) : null}
                                {hata?.abonelik === s.abonelik ? (
                                    <tr>
                                        <Td
                                            colSpan={5}
                                            className={cn(
                                                hucre,
                                                "whitespace-normal",
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
                            <tr>
                                <Td
                                    colSpan={5}
                                    className={cn(hucre, "text-soluk")}
                                >
                                    Eşleşen abonelik yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <p className="mt-3 text-[13px] text-soluk">
                {satirlar.length} abonelik ·{" "}
                {satirlar.length >= azami
                    ? `İlk ${azami} gösteriliyor — aramayı daralt.`
                    : "Başlığa tıklayarak sırala."}
            </p>
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

    return (
        <tr data-abonelik={s.abonelik}>
            <Td className={hucre}>
                <div className="flex flex-col gap-0.5">
                    <span>{s.email ?? "—"}</span>
                    <span className="text-xs text-soluk">
                        {alt.join(" · ")}
                    </span>
                </div>
            </Td>
            <Td className={hucre}>
                <span
                    className="inline-flex items-center gap-1.5 text-[13px]"
                    title={s.durum || undefined}
                >
                    <span
                        aria-hidden="true"
                        className={cn("size-2 rounded-full", nokta)}
                    />
                    {durumMetni}
                </span>
            </Td>
            <Td className={hucre}>
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
            <Td className={hucre}>
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
                    <span className="text-soluk">Henüz yok</span>
                )}
            </Td>
            <Td className={cn(hucre, "text-right")}>
                <div className="inline-flex gap-2">
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
    onayla: () => void;
}): ReactNode {
    const kim = s.email ?? "Abone";
    const deneme = s.durum === "trialing";
    const [soru, aciklama, dugme]: [string, string, string] =
        is === "iade"
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
        <tr className="bg-zemin/50">
            <Td colSpan={5} className={cn(hucre, "whitespace-normal")}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span>
                        <span className="font-semibold">{soru}</span>{" "}
                        <span className="text-soluk">{aciklama}</span>
                    </span>
                    <span className="inline-flex gap-2">
                        <Dugme
                            tur="sade"
                            className="min-h-8"
                            disabled={calisiyor}
                            onClick={vazgec}
                        >
                            Vazgeç
                        </Dugme>
                        <Dugme
                            tur={is === "geriAl" ? "ana" : "yikici"}
                            className="min-h-8"
                            disabled={calisiyor}
                            onClick={onayla}
                        >
                            {dugme}
                        </Dugme>
                    </span>
                </div>
            </Td>
        </tr>
    );
}
