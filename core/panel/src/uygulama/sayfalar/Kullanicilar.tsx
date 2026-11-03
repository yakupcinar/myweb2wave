// Kullanıcılar (P5, CRM): abonelik başına satır — durum, maskeli e-posta,
// ödemeyi başlatan ziyaretin kaynağı/kampanyası, kişi ve uygulama bağlantısı.
// Arama ve durum adreste (?ara=&durum=), sunucuda süzülür; sıralama istemcide
// (en çok 200 satır — tablo kitaplığı gerekmedi).
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useMemo, useState, type ReactNode } from "react";
import { useSearchParams } from "react-router";
import { api } from "../api";
import { zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Satir = Awaited<ReturnType<typeof listeAl>>["satirlar"][number];
type Sutun = "durum" | "email" | "kaynak" | "kampanya" | "odeme" | "guncelleme";

async function listeAl(ara: string, durum: string) {
    const { data } = await api.GET("/panel/api/kullanicilar", {
        params: { query: { ara, durum } },
    });
    if (!data) throw new Error("kullanıcılar alınamadı");
    return data;
}

const SUTUNLAR: [Sutun, string][] = [
    ["durum", "Durum"],
    ["email", "E-posta"],
    ["kaynak", "Kaynak"],
    ["kampanya", "Kampanya"],
    ["odeme", "Ödeme (UTC)"],
    ["guncelleme", "Güncelleme (UTC)"],
];

const durumRozeti = (d: string): ReactNode =>
    d === "active" || d === "trialing" ? (
        <Rozet tur="iyi">{d}</Rozet>
    ) : d === "past_due" || d === "unpaid" ? (
        <Rozet tur="uyari">{d}</Rozet>
    ) : (
        <Rozet>{d || "—"}</Rozet>
    );

export function Kullanicilar(): ReactNode {
    const [adres, setAdres] = useSearchParams();
    const ara = adres.get("ara") ?? "";
    const durum = adres.get("durum") ?? "";
    const [taslak, setTaslak] = useState(ara);
    const sorgu = useQuery({
        queryKey: ["kullanicilar", ara, durum],
        queryFn: () => listeAl(ara, durum),
        // Yeni arama yüklenirken önceki sonuç kalır — durum seçenekleri boşalmasın.
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
                baslik="Kullanıcılar"
                aciklama="Abonelikler ve geldikleri kaynak — e-posta maskeli (tam adres Stripe'ta)"
            />
            <form
                role="search"
                className="mb-4 flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    sec(taslak, durum);
                }}
            >
                <label className="grid gap-1.5">
                    <span className="font-semibold">Ara</span>
                    <input
                        type="search"
                        value={taslak}
                        maxLength={100}
                        placeholder="e-posta, kaynak ya da kampanya"
                        onChange={(e) => setTaslak(e.currentTarget.value)}
                        className="min-h-9 w-72 max-w-full rounded-lg border border-kenar bg-zemin px-3"
                    />
                </label>
                <label className="grid gap-1.5">
                    <span className="font-semibold">Durum</span>
                    <select
                        value={durum}
                        onChange={(e) => sec(ara, e.currentTarget.value)}
                        className="min-h-9 rounded-lg border border-kenar bg-zemin px-3"
                    >
                        <option value="">hepsi</option>
                        {(sorgu.data?.durumlar ?? (durum ? [durum] : [])).map(
                            (d) => (
                                <option key={d} value={d}>
                                    {d}
                                </option>
                            ),
                        )}
                    </select>
                </label>
                <Dugme tur="ana" type="submit">
                    Ara
                </Dugme>
            </form>
            {sorgu.isError ? (
                <p role="alert" className="text-tehlike">
                    Kullanıcılar yüklenemedi.
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

function Liste({
    satirlar,
    azami,
}: {
    satirlar: Satir[];
    azami: number;
}): ReactNode {
    // Varsayılan: sunucunun sırası (en yeni güncellenen önce).
    const [sira, setSira] = useState<{ sutun: Sutun; artan: boolean } | null>(
        null,
    );
    const sirali = useMemo(() => {
        if (!sira) return satirlar;
        const deger = (s: Satir): string =>
            (sira.sutun === "kaynak" ? s.kaynak : s[sira.sutun]) ?? "";
        return [...satirlar].sort(
            (a, b) =>
                (deger(a) < deger(b) ? -1 : deger(a) > deger(b) ? 1 : 0) *
                (sira.artan ? 1 : -1),
        );
    }, [satirlar, sira]);

    return (
        <Kart aria-label="Abonelikler">
            <KartBasi
                baslik={`${satirlar.length} abonelik`}
                aciklama={
                    satirlar.length >= azami
                        ? `İlk ${azami} gösteriliyor — aramayı daralt.`
                        : "Başlığa tıklayarak sırala."
                }
            />
            <Tablo>
                <thead>
                    <tr>
                        {SUTUNLAR.map(([sutun, ad]) => (
                            <Th
                                key={sutun}
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
                                    className="uppercase"
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
                        <Th>Huni</Th>
                        <Th>Kişi</Th>
                    </tr>
                </thead>
                <tbody>
                    {sirali.map((s) => (
                        <tr key={s.abonelik} data-abonelik={s.abonelik}>
                            <Td>{durumRozeti(s.durum)}</Td>
                            <Td className="font-mono text-[12.5px]">
                                {s.email ?? "—"}
                            </Td>
                            <Td>
                                {s.reklam ? (
                                    <Rozet tur="uyari">reklam</Rozet>
                                ) : null}{" "}
                                {s.kaynak
                                    ? `${s.kaynak}${s.ortam ? ` / ${s.ortam}` : ""}`
                                    : "—"}
                            </Td>
                            <Td>{s.kampanya ?? "—"}</Td>
                            <Td>{s.odeme ? zaman(s.odeme) : "—"}</Td>
                            <Td>{zaman(s.guncelleme)}</Td>
                            <Td>{s.huni ?? "—"}</Td>
                            <Td>
                                {s.bagli
                                    ? `bağlı (${s.app ?? "?"})`
                                    : s.kisi
                                      ? "kayıtlı"
                                      : "—"}
                            </Td>
                        </tr>
                    ))}
                    {satirlar.length ? null : (
                        <tr>
                            <Td colSpan={8} className="text-soluk">
                                Eşleşen abonelik yok.
                            </Td>
                        </tr>
                    )}
                </tbody>
            </Tablo>
        </Kart>
    );
}
