// Kullanıcılar (P5, CRM): abonelik başına satır — durum, maskeli e-posta,
// ödemeyi başlatan ziyaretin kaynağı/kampanyası, kişi ve uygulama bağlantısı.
// Arama ve durum adreste (?ara=&durum=), sunucuda süzülür; sıralama istemcide
// (en çok 200 satır — tablo kitaplığı gerekmedi).
// M6a (A19): satırda dönem sonunda iptal / geri al ve son faturanın iadesi —
// iki adımlı onay sayfada (tarayıcı iletişim kutusu yok).
import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from "@tanstack/react-query";
import { useMemo, useState, type ReactNode } from "react";
import { useSearchParams } from "react-router";
import { api } from "../api";
import { tutarlar, zaman } from "../bicim";
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
                        <Th>İşlem</Th>
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
                            <Td>
                                <Islemler s={s} />
                            </Td>
                        </tr>
                    ))}
                    {satirlar.length ? null : (
                        <tr>
                            <Td colSpan={9} className="text-soluk">
                                Eşleşen abonelik yok.
                            </Td>
                        </tr>
                    )}
                </tbody>
            </Tablo>
        </Kart>
    );
}

type Is = "iptal" | "geriAl" | "iade";

const SURER = ["active", "trialing", "past_due"];

function Islemler({ s }: { s: Satir }): ReactNode {
    const istemci = useQueryClient();
    const [onay, setOnay] = useState<Is | null>(null);
    const [hata, setHata] = useState("");
    const [calisiyor, setCalisiyor] = useState(false);
    const kalan = s.sonFatura
        ? Math.round((s.sonFatura.tutar - s.sonFatura.iade) * 100) / 100
        : 0;
    const islemler: [Is, string][] = [
        ...(s.iptalDonemSonu
            ? ([["geriAl", "İptali geri al"]] as [Is, string][])
            : SURER.includes(s.durum)
              ? ([["iptal", "Dönem sonunda iptal et"]] as [Is, string][])
              : []),
        ...(s.sonFatura && kalan > 0
            ? ([
                  [
                      "iade",
                      `Son faturayı iade et (${tutarlar([{ paraBirimi: s.sonFatura.paraBirimi, tutar: kalan }])})`,
                  ],
              ] as [Is, string][])
            : []),
    ];
    const yap = async (is: Is): Promise<void> => {
        setHata("");
        setCalisiyor(true);
        const yol = {
            params: {
                path: { abonelik: s.abonelik },
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
            return setHata((error?.hatalar ?? ["İşlem yapılamadı."]).join(" "));
        await istemci.invalidateQueries({ queryKey: ["kullanicilar"] });
    };
    return (
        <div className="grid gap-1.5">
            {s.iptalDonemSonu ? (
                <span className="text-[12.5px] text-soluk">
                    İptal planlı
                    {s.donemSonu ? ` — ${zaman(s.donemSonu)}` : ""}
                </span>
            ) : null}
            {onay ? (
                <span className="flex flex-wrap items-center gap-2">
                    <span>
                        {islemler.find(([i]) => i === onay)?.[1]} — emin
                        misiniz?
                    </span>
                    <Dugme
                        tur="ana"
                        disabled={calisiyor}
                        onClick={() => void yap(onay)}
                    >
                        Evet
                    </Dugme>
                    <Dugme disabled={calisiyor} onClick={() => setOnay(null)}>
                        Vazgeç
                    </Dugme>
                </span>
            ) : (
                islemler.map(([is, ad]) => (
                    <Dugme key={is} onClick={() => setOnay(is)}>
                        {ad}
                    </Dugme>
                ))
            )}
            {hata ? (
                <span role="alert" className="text-tehlike">
                    {hata}
                </span>
            ) : null}
        </div>
    );
}
