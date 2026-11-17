// M4a (A17): deneyler — yönlendirme trafiği hunilere böler, içerik aynı
// huninin içinde koşulda deney.<ad> olur. Taslak dışında varyantlar kilitli.
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type FormEvent, type ReactNode } from "react";
import { api } from "../api";
import { yuzde } from "../bicim";
import { SayfaBasi } from "../Duzen";
import type { components } from "../../sozlesme";
import { alanSinifi, Satir } from "../ui/Alan";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Liste = components["schemas"]["PanelDeneyleri"];
type Deney = Liste["deneyler"][number];

// P7-temel: durum rozeti Türkçe (taslakta ham "durdu" yazıyordu).
const DURUMLAR: Record<Deney["durum"], ["iyi" | "uyari" | "notr", string]> = {
    taslak: ["notr", "Taslak"],
    aktif: ["iyi", "Çalışıyor"],
    durdu: ["uyari", "Durdu"],
    bitti: ["notr", "Bitti"],
};
type Varyant = components["schemas"]["DeneyVaryanti"];

export function Deneyler(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const app = uygulamalar.data?.[0];
    if (!app) return <p className="text-soluk">Yükleniyor…</p>;
    return <DeneyListesi app={app} />;
}

function DeneyListesi({ app }: { app: string }): ReactNode {
    const anahtar = ["deneyler", app];
    const istemci = useQueryClient();
    const sorgu = useQuery({
        queryKey: anahtar,
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/deneyler",
                {
                    params: { path: { app } },
                },
            );
            if (!data) throw new Error("Deneyler yüklenemedi.");
            return data;
        },
    });
    const [hata, setHata] = useState("");
    const [yeni, setYeni] = useState({
        ad: "",
        tur: "icerik" as Deney["tur"],
        huni: "",
        a: "kontrol",
        b: "varyant",
        oran: "50",
        hedef: "",
    });
    const yaz = (d: Liste) => istemci.setQueryData(anahtar, d);
    const kaydet = async (e: FormEvent) => {
        e.preventDefault();
        setHata("");
        const yonlendirme = yeni.tur === "yonlendirme";
        const varyantlar: Varyant[] = [
            {
                ad: yeni.a,
                pay: Number(yeni.oran),
                ...(yonlendirme ? { huni: yeni.huni } : {}),
            },
            {
                ad: yeni.b,
                pay: 100 - Number(yeni.oran),
                ...(yonlendirme ? { huni: yeni.hedef } : {}),
            },
        ];
        const { data, error } = await api.POST(
            "/panel/api/uygulamalar/{app}/deneyler",
            {
                params: { path: { app }, header: { "X-CSRF-Token": "" } },
                body: {
                    ad: yeni.ad,
                    tur: yeni.tur,
                    huni: yeni.huni,
                    varyantlar,
                },
            },
        );
        if (!data)
            return setHata((error?.hatalar ?? ["Kaydedilmedi."]).join(" "));
        yaz(data);
        setYeni({ ...yeni, ad: "" });
    };
    const durum = async (ad: string, durum: "aktif" | "durdu" | "bitti") => {
        setHata("");
        const { data, error } = await api.PUT(
            "/panel/api/uygulamalar/{app}/deneyler/{ad}/durum",
            {
                params: { path: { app, ad }, header: { "X-CSRF-Token": "" } },
                body: { durum },
            },
        );
        if (!data)
            return setHata((error?.hatalar ?? ["Durum değişmedi."]).join(" "));
        yaz(data);
    };
    return (
        <>
            <SayfaBasi
                baslik="Deneyler"
                aciklama="İki huniyi ya da iki metni karşılaştır. Başlayan deneyin varyantları sonuçlar karışmasın diye kilitlenir."
            />
            <Kart aria-label="Deneyler">
                <KartBasi
                    baslik="Deneyler"
                    aciklama="Yönlendirme trafiği hunilere böler, içerik ise koşullarda deney.ad olarak kullanılır."
                />
                {sorgu.isError ? (
                    <p role="alert" className="px-[18px] py-4 text-tehlike">
                        Deneyler yüklenemedi.
                    </p>
                ) : null}
                {sorgu.data?.deneyler.map((d) => (
                    // Ad, durum ve düğmeler aynı kapta (testler satırı adın kabından bulur).
                    <div
                        key={d.ad}
                        className="border-t border-kenar px-[18px] py-4 first:border-t-0"
                    >
                        <span className="flex flex-wrap items-center justify-between gap-3">
                            <span className="grid gap-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    <strong className="text-[15px] font-semibold">
                                        {d.ad}
                                    </strong>
                                    <Rozet tur={DURUMLAR[d.durum][0]}>
                                        {DURUMLAR[d.durum][1]}
                                    </Rozet>
                                </span>
                                <span className="text-[13px] text-soluk">
                                    {d.tur === "yonlendirme"
                                        ? "Yönlendirme"
                                        : "İçerik"}{" "}
                                    · {d.huni} ·{" "}
                                    {d.varyantlar
                                        .map(
                                            (v) =>
                                                `${v.ad} %${v.pay}${v.huni ? ` → ${v.huni}` : ""}`,
                                        )
                                        .join(" · ")}
                                </span>
                            </span>
                            <span className="inline-flex gap-2">
                                {d.durum === "taslak" ? (
                                    <Dugme
                                        tur="ana"
                                        className="min-h-8 px-3"
                                        onClick={() =>
                                            void durum(d.ad, "aktif")
                                        }
                                    >
                                        Başlat
                                    </Dugme>
                                ) : null}
                                {d.durum === "aktif" ? (
                                    <Dugme
                                        className="min-h-8 px-3 font-normal"
                                        onClick={() =>
                                            void durum(d.ad, "durdu")
                                        }
                                    >
                                        Durdur
                                    </Dugme>
                                ) : null}
                                {d.durum === "durdu" ? (
                                    <Dugme
                                        className="min-h-8 px-3 font-normal"
                                        onClick={() =>
                                            void durum(d.ad, "aktif")
                                        }
                                    >
                                        Sürdür
                                    </Dugme>
                                ) : null}
                                {d.durum === "aktif" || d.durum === "durdu" ? (
                                    <Dugme
                                        tur="tehlike"
                                        className="min-h-8 px-3 font-normal"
                                        onClick={() =>
                                            void durum(d.ad, "bitti")
                                        }
                                    >
                                        Bitir
                                    </Dugme>
                                ) : null}
                            </span>
                        </span>
                        {d.durum !== "taslak" ? (
                            <Sonuclar app={app} ad={d.ad} />
                        ) : null}
                    </div>
                ))}
                {!sorgu.isLoading && !sorgu.data?.deneyler.length ? (
                    <p className="px-[18px] py-4 text-soluk">
                        Henüz deney yok.
                    </p>
                ) : null}
                {hata ? (
                    <p role="alert" className="px-[18px] pb-4 text-tehlike">
                        {hata}
                    </p>
                ) : null}
            </Kart>
            <Kart className="mt-5" aria-label="Yeni deney">
                <KartBasi baslik="Yeni deney" />
                <form aria-label="Yeni deney" onSubmit={(e) => void kaydet(e)}>
                    <Satir
                        etiket="Deney adı"
                        yardim="Koşullarda deney.ad olarak geçer; sonradan değişmez."
                    >
                        {(id) => (
                            <input
                                id={id}
                                required
                                className={alanSinifi}
                                value={yeni.ad}
                                onChange={(e) =>
                                    setYeni({ ...yeni, ad: e.target.value })
                                }
                            />
                        )}
                    </Satir>
                    <Satir
                        etiket="Tür"
                        yardim="İçerik: aynı hunide metin ya da blok farkı. Yönlendirme: trafiği iki huniye böler."
                    >
                        {(id) => (
                            <select
                                id={id}
                                className={alanSinifi}
                                value={yeni.tur}
                                onChange={(e) =>
                                    setYeni({
                                        ...yeni,
                                        tur: e.target.value as Deney["tur"],
                                    })
                                }
                            >
                                <option value="icerik">İçerik</option>
                                <option value="yonlendirme">Yönlendirme</option>
                            </select>
                        )}
                    </Satir>
                    <Satir
                        etiket={
                            yeni.tur === "yonlendirme"
                                ? "Kontrol hunisi"
                                : "Huni"
                        }
                    >
                        {(id) => (
                            <input
                                id={id}
                                required
                                className={alanSinifi}
                                value={yeni.huni}
                                onChange={(e) =>
                                    setYeni({ ...yeni, huni: e.target.value })
                                }
                            />
                        )}
                    </Satir>
                    <Satir etiket="Kontrol varyantı">
                        {(id) => (
                            <input
                                id={id}
                                required
                                className={alanSinifi}
                                value={yeni.a}
                                onChange={(e) =>
                                    setYeni({ ...yeni, a: e.target.value })
                                }
                            />
                        )}
                    </Satir>
                    <Satir
                        etiket="Kontrol payı (%)"
                        yardim="Kalan pay varyanta gider."
                    >
                        {(id) => (
                            <input
                                id={id}
                                required
                                type="number"
                                min="1"
                                max="99"
                                className={alanSinifi}
                                value={yeni.oran}
                                onChange={(e) =>
                                    setYeni({ ...yeni, oran: e.target.value })
                                }
                            />
                        )}
                    </Satir>
                    <Satir etiket="Varyant">
                        {(id) => (
                            <input
                                id={id}
                                required
                                className={alanSinifi}
                                value={yeni.b}
                                onChange={(e) =>
                                    setYeni({ ...yeni, b: e.target.value })
                                }
                            />
                        )}
                    </Satir>
                    {yeni.tur === "yonlendirme" ? (
                        <Satir etiket="Varyant hunisi">
                            {(id) => (
                                <input
                                    id={id}
                                    required
                                    className={alanSinifi}
                                    value={yeni.hedef}
                                    onChange={(e) =>
                                        setYeni({
                                            ...yeni,
                                            hedef: e.target.value,
                                        })
                                    }
                                />
                            )}
                        </Satir>
                    ) : null}
                    <div className="flex justify-end border-t border-kenar px-[18px] py-3.5">
                        <Dugme tur="ana" type="submit">
                            Taslağı kaydet
                        </Dugme>
                    </div>
                </form>
            </Kart>
        </>
    );
}

/**
 * M4b (A17): dönüşüm = abone oldu (kartlı deneme dahil); kazanma olasılığı
 * bu oranın en yüksek olma olasılığı. "Ödedi" yalnız tutar çekilen (deneme 0).
 */
function Sonuclar({ app, ad }: { app: string; ad: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["deney-sonuclari", app, ad],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/deneyler/{ad}/sonuclar",
                { params: { path: { app, ad } } },
            );
            if (!data) throw new Error("Sonuçlar yüklenemedi.");
            return data;
        },
    });
    if (sorgu.isError) return <p role="alert">Sonuçlar yüklenemedi.</p>;
    if (!sorgu.data) return null;
    return (
        <Tablo aria-label={`${ad} sonuçları`}>
            <thead>
                <tr>
                    {[
                        "Varyant",
                        "Ziyaret",
                        "Ödemeye geçti",
                        "Abone oldu",
                        "Dönüşüm",
                        "Ödedi",
                        "Kazanma olasılığı",
                    ].map((b) => (
                        <Th key={b}>{b}</Th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {sorgu.data.varyantlar.map((v) => (
                    <tr key={v.ad}>
                        <Td>{v.ad}</Td>
                        <Td>{v.ziyaret}</Td>
                        <Td>{v.odemeyeGecti}</Td>
                        <Td>{v.abone}</Td>
                        <Td>{yuzde(v.abone, v.ziyaret)}</Td>
                        <Td>{v.odedi}</Td>
                        <Td>{yuzde(v.kazanmaOlasiligi, 1)}</Td>
                    </tr>
                ))}
            </tbody>
        </Tablo>
    );
}
