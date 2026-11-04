// Fiyat kataloğu (M1a): plan açınca Stripe'ta ürün + fiyat oluşur; tutar
// Stripe'tan okunur (L10). Stripe fiyatı değiştirilemez — "Fiyatı değiştir"
// yeni fiyat açar, mevcut aboneler eskisinde kalır. Anahtar sonradan
// değişmez (huni paywall'u onunla seçer), plan silinmez. Tutar alanı
// step="any": ondalık sınırı çekirdekte (en çok 2 hane) — step="0.01" +
// min="0.01" happy-dom'da kayan nokta yüzünden 59'u bile geçersiz sayıp
// formu göndermiyordu (ölçüldü).
// M1b: planın en çok bir teklifi (kartlı deneme, ücretli giriş, ilk dönem
// indirimi); giriş ücreti de Stripe'tan okunur. Teklifi gösterilemeyen planın
// fiyatı da gelmez ("Stripe'tan okunamadı").
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Tablo, Td, Th } from "../ui/Tablo";

type PanelPlanlari = components["schemas"]["PanelPlanlari"];
type PlanFiyati = components["schemas"]["PlanFiyati"];

const ARALIK = { day: "gün", week: "hafta", month: "ay", year: "yıl" } as const;
const PARA_BIRIMLERI = ["EUR", "USD", "GBP", "TRY"] as const;
const girdiSinifi =
    "min-h-9 rounded-lg border border-kenar bg-zemin px-3 max-w-full";

function fiyatYazisi(f: PlanFiyati | null): string {
    if (!f) return "Stripe'tan okunamadı";
    const tutar = new Intl.NumberFormat("tr-TR", {
        style: "currency",
        currency: f.paraBirimi,
    }).format(f.tutar);
    return `${tutar} / ${ARALIK[f.aralik]}`;
}

const TEKLIF_TURLERI = {
    "": "Teklif yok",
    deneme: "Kartlı ücretsiz deneme",
    giris: "Ücretli giriş dönemi",
    indirim: "İlk dönem indirimi",
} as const;

function teklifYazisi(f: PlanFiyati | null): string {
    const t = f?.teklif;
    if (!f || !t) return "—";
    if (t.tur === "deneme") return `${t.gun} gün ücretsiz`;
    if (t.tur === "giris")
        return `${t.gun} gün ${new Intl.NumberFormat("tr-TR", {
            style: "currency",
            currency: f.paraBirimi,
        }).format(t.tutar)}`;
    return `İlk ${ARALIK[f.aralik]} %${t.yuzde} indirim`;
}

export function Planlar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const app = uygulamalar.data?.[0];
    return (
        <>
            <SayfaBasi
                baslik="Planlar"
                aciklama="Paywall'un sattığı abonelikler. Plan açınca Stripe'ta ürün ve fiyat oluşur."
            />
            {app ? (
                <PlanListesi app={app} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </>
    );
}

function PlanListesi({ app }: { app: string }): ReactNode {
    const istemci = useQueryClient();
    const anahtar = ["planlar", app];
    const sorgu = useQuery({
        queryKey: anahtar,
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/planlar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("planlar alınamadı");
            return data;
        },
    });
    const yaz = (d: PanelPlanlari): void => {
        istemci.setQueryData(anahtar, d);
    };
    const [yeni, setYeni] = useState({
        anahtar: "",
        ad: "",
        tutar: "",
        paraBirimi: "EUR" as (typeof PARA_BIRIMLERI)[number],
        aralik: "month" as "week" | "month" | "year",
    });
    const [teklif, setTeklif] = useState({
        anahtar: "",
        tur: "" as keyof typeof TEKLIF_TURLERI,
        gun: "7",
        tutar: "",
        yuzde: "50",
    });
    const [degisen, setDegisen] = useState<{
        anahtar: string;
        tutar: string;
    } | null>(null);

    const olustur = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.POST(
                "/panel/api/uygulamalar/{app}/planlar",
                {
                    params: { path: { app }, header: { "X-CSRF-Token": "" } },
                    body: { ...yeni, tutar: Number(yeni.tutar) },
                },
            );
            if (!data)
                throw new Error(
                    (error?.hatalar ?? ["Oluşturulmadı."]).join(" "),
                );
            return data;
        },
        onSuccess: (d) => {
            yaz(d);
            setYeni({ ...yeni, anahtar: "", ad: "", tutar: "" });
        },
    });
    const fiyat = useMutation({
        mutationFn: async (d: { anahtar: string; tutar: string }) => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/planlar/{anahtar}/fiyat",
                {
                    params: {
                        path: { app, anahtar: d.anahtar },
                        header: { "X-CSRF-Token": "" },
                    },
                    body: { tutar: Number(d.tutar) },
                },
            );
            if (!data)
                throw new Error((error?.hatalar ?? ["Değişmedi."]).join(" "));
            return data;
        },
        onSuccess: (d) => {
            yaz(d);
            setDegisen(null);
        },
    });

    const teklifKaydet = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/planlar/{anahtar}/teklif",
                {
                    params: {
                        path: { app, anahtar: teklif.anahtar },
                        header: { "X-CSRF-Token": "" },
                    },
                    // Türün almadığı alan gönderilmez (çekirdek fazlayı reddeder).
                    body:
                        teklif.tur === ""
                            ? { tur: null }
                            : teklif.tur === "indirim"
                              ? { tur: "indirim", yuzde: Number(teklif.yuzde) }
                              : {
                                    tur: teklif.tur,
                                    gun: Number(teklif.gun),
                                    ...(teklif.tur === "giris"
                                        ? { tutar: Number(teklif.tutar) }
                                        : {}),
                                },
                },
            );
            if (!data)
                throw new Error((error?.hatalar ?? ["Değişmedi."]).join(" "));
            return data;
        },
        onSuccess: yaz,
    });

    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Planlar yüklenemedi.
            </p>
        );
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );

    return (
        <div className="grid gap-5">
            <Kart aria-label="Planlar">
                <KartBasi
                    baslik="Planlar"
                    aciklama="Fiyat değişince mevcut aboneler eski fiyatta kalır; yeni ödemeler yeni fiyattan."
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Anahtar",
                                "Ad",
                                "Fiyat",
                                "Teklif",
                                "Stripe fiyatı",
                                "",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sorgu.data.planlar.map((p) => (
                            <tr key={p.anahtar}>
                                <Td className="font-mono text-[12.5px]">
                                    {p.anahtar}
                                </Td>
                                <Td>{p.ad}</Td>
                                <Td>{fiyatYazisi(p.fiyat)}</Td>
                                <Td>{teklifYazisi(p.fiyat)}</Td>
                                <Td className="font-mono text-[12.5px]">
                                    {p.stripeFiyat}
                                </Td>
                                <Td className="text-right">
                                    {degisen?.anahtar === p.anahtar ? (
                                        <form
                                            className="inline-flex flex-wrap items-center justify-end gap-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                fiyat.mutate(degisen);
                                            }}
                                        >
                                            <input
                                                aria-label={`${p.anahtar} yeni tutar`}
                                                type="number"
                                                min="0.01"
                                                step="any"
                                                value={degisen.tutar}
                                                onChange={(e) =>
                                                    setDegisen({
                                                        ...degisen,
                                                        tutar: e.currentTarget
                                                            .value,
                                                    })
                                                }
                                                className={`${girdiSinifi} w-28`}
                                            />
                                            <Dugme
                                                tur="ana"
                                                type="submit"
                                                disabled={
                                                    !degisen.tutar ||
                                                    fiyat.isPending
                                                }
                                            >
                                                Kaydet
                                            </Dugme>
                                            <Dugme
                                                onClick={() => setDegisen(null)}
                                            >
                                                Vazgeç
                                            </Dugme>
                                        </form>
                                    ) : (
                                        <Dugme
                                            onClick={() => {
                                                fiyat.reset();
                                                setDegisen({
                                                    anahtar: p.anahtar,
                                                    tutar: "",
                                                });
                                            }}
                                        >
                                            Fiyatı değiştir
                                        </Dugme>
                                    )}
                                </Td>
                            </tr>
                        ))}
                        {sorgu.data.planlar.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Henüz plan yok — paywall bir plan seçmeden
                                    yayınlanamaz.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
                {fiyat.isError ? (
                    <p role="alert" className="px-4.5 py-3 text-tehlike">
                        {fiyat.error.message}
                    </p>
                ) : null}
            </Kart>
            {sorgu.data.planlar.length ? (
                <Kart>
                    <KartBasi
                        baslik="Teklif"
                        aciklama="Ödeme sayfası ve paywall planın teklifini kullanır. Değişiklik yalnız yeni ödemelere uygulanır."
                    />
                    <form
                        aria-label="Teklif"
                        className="flex flex-wrap items-end gap-3 px-4.5 py-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            teklifKaydet.mutate();
                        }}
                    >
                        <label className="grid gap-1.5">
                            <span className="font-semibold">Plan</span>
                            <select
                                value={teklif.anahtar}
                                onChange={(e) =>
                                    setTeklif({
                                        ...teklif,
                                        anahtar: e.currentTarget.value,
                                    })
                                }
                                className={girdiSinifi}
                            >
                                <option value="">seç</option>
                                {sorgu.data.planlar.map((p) => (
                                    <option key={p.anahtar} value={p.anahtar}>
                                        {p.ad}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5">
                            <span className="font-semibold">Tür</span>
                            <select
                                value={teklif.tur}
                                onChange={(e) =>
                                    setTeklif({
                                        ...teklif,
                                        tur: e.currentTarget
                                            .value as keyof typeof TEKLIF_TURLERI,
                                    })
                                }
                                className={girdiSinifi}
                            >
                                {Object.entries(TEKLIF_TURLERI).map(
                                    ([deger, ad]) => (
                                        <option key={deger} value={deger}>
                                            {ad}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                        {teklif.tur === "deneme" || teklif.tur === "giris" ? (
                            <label className="grid gap-1.5">
                                <span className="font-semibold">Gün</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="90"
                                    value={teklif.gun}
                                    onChange={(e) =>
                                        setTeklif({
                                            ...teklif,
                                            gun: e.currentTarget.value,
                                        })
                                    }
                                    className={`${girdiSinifi} w-20`}
                                />
                            </label>
                        ) : null}
                        {teklif.tur === "giris" ? (
                            <label className="grid gap-1.5">
                                <span className="font-semibold">
                                    Giriş ücreti
                                </span>
                                <input
                                    type="number"
                                    min="0.01"
                                    step="any"
                                    value={teklif.tutar}
                                    placeholder="0.99"
                                    onChange={(e) =>
                                        setTeklif({
                                            ...teklif,
                                            tutar: e.currentTarget.value,
                                        })
                                    }
                                    className={`${girdiSinifi} w-28`}
                                />
                            </label>
                        ) : null}
                        {teklif.tur === "indirim" ? (
                            <label className="grid gap-1.5">
                                <span className="font-semibold">
                                    İndirim (%)
                                </span>
                                <input
                                    type="number"
                                    min="1"
                                    max="99"
                                    value={teklif.yuzde}
                                    onChange={(e) =>
                                        setTeklif({
                                            ...teklif,
                                            yuzde: e.currentTarget.value,
                                        })
                                    }
                                    className={`${girdiSinifi} w-20`}
                                />
                            </label>
                        ) : null}
                        <Dugme
                            tur="ana"
                            type="submit"
                            disabled={
                                !teklif.anahtar ||
                                (teklif.tur === "giris" && !teklif.tutar) ||
                                teklifKaydet.isPending
                            }
                        >
                            Teklifi kaydet
                        </Dugme>
                        {teklifKaydet.isError ? (
                            <span role="alert" className="text-tehlike">
                                {teklifKaydet.error.message}
                            </span>
                        ) : null}
                    </form>
                </Kart>
            ) : null}
            <Kart>
                <KartBasi
                    baslik="Yeni plan"
                    aciklama="Anahtar sonradan değişmez; huninin paywall adımı planı bununla seçer."
                />
                <form
                    className="flex flex-wrap items-end gap-3 px-4.5 py-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        olustur.mutate();
                    }}
                >
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Anahtar</span>
                        <input
                            value={yeni.anahtar}
                            maxLength={32}
                            placeholder="yearly"
                            onChange={(e) =>
                                setYeni({
                                    ...yeni,
                                    anahtar: e.currentTarget.value,
                                })
                            }
                            className={`${girdiSinifi} w-40 font-mono`}
                        />
                    </label>
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Ad</span>
                        <input
                            value={yeni.ad}
                            maxLength={100}
                            placeholder="Yıllık"
                            onChange={(e) =>
                                setYeni({ ...yeni, ad: e.currentTarget.value })
                            }
                            className={`${girdiSinifi} w-48`}
                        />
                    </label>
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Tutar</span>
                        <input
                            type="number"
                            min="0.01"
                            step="any"
                            value={yeni.tutar}
                            placeholder="9.99"
                            onChange={(e) =>
                                setYeni({
                                    ...yeni,
                                    tutar: e.currentTarget.value,
                                })
                            }
                            className={`${girdiSinifi} w-28`}
                        />
                    </label>
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Para birimi</span>
                        <select
                            value={yeni.paraBirimi}
                            onChange={(e) =>
                                setYeni({
                                    ...yeni,
                                    paraBirimi: e.currentTarget
                                        .value as (typeof PARA_BIRIMLERI)[number],
                                })
                            }
                            className={girdiSinifi}
                        >
                            {PARA_BIRIMLERI.map((p) => (
                                <option key={p}>{p}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Aralık</span>
                        <select
                            value={yeni.aralik}
                            onChange={(e) =>
                                setYeni({
                                    ...yeni,
                                    aralik: e.currentTarget.value as
                                        "week" | "month" | "year",
                                })
                            }
                            className={girdiSinifi}
                        >
                            <option value="week">Haftalık</option>
                            <option value="month">Aylık</option>
                            <option value="year">Yıllık</option>
                        </select>
                    </label>
                    <Dugme
                        tur="ana"
                        type="submit"
                        disabled={
                            !yeni.anahtar.trim() ||
                            !yeni.ad.trim() ||
                            !yeni.tutar ||
                            olustur.isPending
                        }
                    >
                        Planı oluştur
                    </Dugme>
                    {olustur.isError ? (
                        <span role="alert" className="text-tehlike">
                            {olustur.error.message}
                        </span>
                    ) : null}
                </form>
            </Kart>
        </div>
    );
}
