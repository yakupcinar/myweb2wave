// M7b (A20): giden webhook hedefleri — adres + olaylar, sır yalnız eklemede
// bir kez görünür; etkin / pasif, sil (iki adımlı onay), teslim günlüğü ve
// yeniden gönder. Gövdede kişisel veri yok; imza Standard Webhooks.
// P7d (A25): Entegrasyonlar sayfasının bölümü; olaylar Türkçe ve gruplu,
// grup başına "Tümü".
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
    useEffect,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { zaman } from "../bicim";
import { gruplar, olayAdi, olayOzeti } from "../olaylar";
import { alanSinifi, kutuSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Hedef = components["schemas"]["WebhookHedefi"];

const JETON = { "X-CSRF-Token": "" };

export function WebhookBolumu({ app }: { app: string }): ReactNode {
    const anahtar = ["webhooklar", app];
    const istemci = useQueryClient();
    const sorgu = useQuery({
        queryKey: anahtar,
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/webhooklar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("Webhook'lar yüklenemedi.");
            return data;
        },
    });
    const [adres, setAdres] = useState("");
    const [secili, setSecili] = useState<string[]>([]);
    const [hata, setHata] = useState("");
    const [sir, setSir] = useState("");
    const sozluk = sorgu.data?.olaylar ?? [];
    const yenile = () => istemci.invalidateQueries({ queryKey: anahtar });
    const ekle = async (e: FormEvent) => {
        e.preventDefault();
        setHata("");
        setSir("");
        const { data, error } = await api.POST(
            "/panel/api/uygulamalar/{app}/webhooklar",
            {
                params: { path: { app }, header: JETON },
                body: { adres: adres.trim(), olaylar: secili },
            },
        );
        if (!data) return setHata((error?.hatalar ?? ["Eklenmedi."]).join(" "));
        setSir(data.sir);
        setAdres("");
        setSecili([]);
        await yenile();
    };
    return (
        <Bolum
            id="webhooklar"
            baslik="Webhook'lar"
            aciklama="Olaylar kendi sunucuna imzalı olarak gönderilir. Kişisel veri gönderilmez; aynı olay iki kez gelebilir."
        >
            {sir ? (
                <div
                    role="alert"
                    className="rounded-[14px] border border-iyi bg-yuzey p-4"
                >
                    <strong>
                        Sırrı şimdi kopyalayın — bir daha gösterilmez:
                    </strong>{" "}
                    <code className="font-mono break-all">{sir}</code>
                </div>
            ) : null}
            <Kart aria-label="Hedefler">
                {sorgu.isError ? (
                    <p role="alert" className="p-4 text-tehlike">
                        Webhook'lar yüklenemedi.
                    </p>
                ) : (
                    <Tablo>
                        <thead>
                            <tr>
                                {[
                                    "Adres",
                                    "Olaylar",
                                    "Durum",
                                    "Teslim",
                                    "İşlem",
                                ].map((b) => (
                                    <Th key={b}>{b}</Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {(sorgu.data?.hedefler ?? []).map((h) => (
                                <HedefSatiri
                                    key={h.id}
                                    app={app}
                                    h={h}
                                    sozluk={sozluk}
                                    yenile={yenile}
                                />
                            ))}
                            {sorgu.data && !sorgu.data.hedefler.length ? (
                                <tr>
                                    <Td colSpan={5} className="text-soluk">
                                        Henüz hedef yok.
                                    </Td>
                                </tr>
                            ) : null}
                        </tbody>
                    </Tablo>
                )}
            </Kart>
            <Kart aria-label="Yeni hedef">
                <KartBasi baslik="Yeni hedef" />
                <form onSubmit={(e) => void ekle(e)}>
                    <Satir etiket="Adres (https)">
                        {(id) => (
                            <input
                                id={id}
                                type="url"
                                required
                                value={adres}
                                placeholder="https://ornek.com/kanca"
                                onChange={(e) =>
                                    setAdres(e.currentTarget.value)
                                }
                                className={alanSinifi}
                            />
                        )}
                    </Satir>
                    <div className="grid items-start gap-x-4 gap-y-2 border-t border-kenar px-[18px] py-3.5 md:grid-cols-[220px_minmax(0,1fr)]">
                        <span id="webhook-olaylar" className="text-[15px]">
                            Olaylar
                        </span>
                        <div
                            role="group"
                            aria-labelledby="webhook-olaylar"
                            className="grid gap-3"
                        >
                            {gruplar(sozluk).map((g) => (
                                <OlayGrubu
                                    key={g.ad}
                                    ad={g.ad}
                                    olaylar={g.olaylar}
                                    secili={secili}
                                    setSecili={setSecili}
                                />
                            ))}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-kenar px-[18px] py-3.5">
                        {hata ? (
                            <p role="alert" className="text-tehlike">
                                {hata}
                            </p>
                        ) : null}
                        <Dugme
                            tur="ana"
                            type="submit"
                            disabled={!secili.length}
                        >
                            Ekle
                        </Dugme>
                    </div>
                </form>
            </Kart>
        </Bolum>
    );
}

function OlayGrubu({
    ad,
    olaylar,
    secili,
    setSecili,
}: {
    ad: string;
    olaylar: [string, string][];
    secili: string[];
    setSecili: (f: (s: string[]) => string[]) => void;
}): ReactNode {
    const tumu = useRef<HTMLInputElement>(null);
    const adlar = olaylar.map(([o]) => o);
    const sayi = adlar.filter((o) => secili.includes(o)).length;
    // Yarım seçim HTML özniteliği değil, yalnız DOM özelliği.
    useEffect(() => {
        if (tumu.current)
            tumu.current.indeterminate = sayi > 0 && sayi < adlar.length;
    }, [sayi, adlar.length]);
    return (
        <div role="group" aria-label={ad} className="grid gap-1.5">
            <div className="flex items-center gap-3">
                <span className="text-[13px] font-semibold text-soluk uppercase">
                    {ad}
                </span>
                {adlar.length > 1 ? (
                    <label className="flex items-center gap-1.5 text-[13px]">
                        <input
                            ref={tumu}
                            type="checkbox"
                            checked={sayi === adlar.length}
                            className={kutuSinifi}
                            onChange={(e) => {
                                const isaretli = e.currentTarget.checked;
                                setSecili((s) =>
                                    isaretli
                                        ? [
                                              ...s,
                                              ...adlar.filter(
                                                  (o) => !s.includes(o),
                                              ),
                                          ]
                                        : s.filter((x) => !adlar.includes(x)),
                                );
                            }}
                        />
                        Tümü
                    </label>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                {olaylar.map(([o, etiket]) => (
                    <label
                        key={o}
                        title={o}
                        className="flex items-center gap-1.5 text-[15px]"
                    >
                        <input
                            type="checkbox"
                            checked={secili.includes(o)}
                            className={kutuSinifi}
                            onChange={(e) => {
                                // Değer şimdi okunur: güncelleyici sonra (çizimde) koşar,
                                // currentTarget o an null (test yakaladı).
                                const isaretli = e.currentTarget.checked;
                                setSecili((s) =>
                                    isaretli
                                        ? [...s, o]
                                        : s.filter((x) => x !== o),
                                );
                            }}
                        />
                        {etiket}
                    </label>
                ))}
            </div>
        </div>
    );
}

function HedefSatiri({
    app,
    h,
    sozluk,
    yenile,
}: {
    app: string;
    h: Hedef;
    sozluk: string[];
    yenile: () => Promise<void>;
}): ReactNode {
    const [silOnay, setSilOnay] = useState(false);
    const [gunluk, setGunluk] = useState(false);
    const [hata, setHata] = useState("");
    const yol = { params: { path: { app, id: h.id }, header: JETON } };
    const sonuc = async (
        p: Promise<{ response: Response; error?: { hatalar: string[] } }>,
    ) => {
        setHata("");
        const { response, error } = await p;
        if (response.status !== 204)
            return setHata((error?.hatalar ?? ["İşlem yapılamadı."]).join(" "));
        await yenile();
    };
    return (
        <>
            <tr data-hedef={h.id}>
                <Td className="font-mono text-[12.5px]">{h.adres}</Td>
                <Td className="whitespace-normal" title={h.olaylar.join(", ")}>
                    {olayOzeti(h.olaylar, sozluk)}
                </Td>
                <Td>
                    {h.aktif ? (
                        <Rozet tur="iyi">etkin</Rozet>
                    ) : (
                        <Rozet>pasif</Rozet>
                    )}
                </Td>
                <Td>
                    {h.teslim} teslim · {h.bekleyen} bekliyor
                    {h.vazgecildi ? ` · ${h.vazgecildi} vazgeçildi` : ""}
                </Td>
                <Td>
                    <div className="flex flex-wrap gap-2">
                        <Dugme
                            onClick={() =>
                                void sonuc(
                                    api.PUT(
                                        "/panel/api/uygulamalar/{app}/webhooklar/{id}",
                                        {
                                            ...yol,
                                            body: { aktif: !h.aktif },
                                        },
                                    ),
                                )
                            }
                        >
                            {h.aktif ? "Pasifleştir" : "Etkinleştir"}
                        </Dugme>
                        <Dugme onClick={() => setGunluk((g) => !g)}>
                            {gunluk ? "Günlüğü kapat" : "Günlük"}
                        </Dugme>
                        {silOnay ? (
                            <>
                                <span>Silinsin mi?</span>
                                <Dugme
                                    tur="yikici"
                                    onClick={() =>
                                        void sonuc(
                                            api.DELETE(
                                                "/panel/api/uygulamalar/{app}/webhooklar/{id}",
                                                yol,
                                            ),
                                        )
                                    }
                                >
                                    Evet
                                </Dugme>
                                <Dugme
                                    tur="sade"
                                    onClick={() => setSilOnay(false)}
                                >
                                    Vazgeç
                                </Dugme>
                            </>
                        ) : (
                            <Dugme
                                tur="tehlike"
                                onClick={() => setSilOnay(true)}
                            >
                                Sil
                            </Dugme>
                        )}
                    </div>
                    {hata ? (
                        <span role="alert" className="text-tehlike">
                            {hata}
                        </span>
                    ) : null}
                </Td>
            </tr>
            {gunluk ? (
                <tr>
                    <Td colSpan={5}>
                        <Gunluk app={app} id={h.id} yenile={yenile} />
                    </Td>
                </tr>
            ) : null}
        </>
    );
}

function Gunluk({
    app,
    id,
    yenile,
}: {
    app: string;
    id: string;
    yenile: () => Promise<void>;
}): ReactNode {
    const istemci = useQueryClient();
    const anahtar = ["webhook-teslimleri", app, id];
    const sorgu = useQuery({
        queryKey: anahtar,
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/webhooklar/{id}/teslimler",
                { params: { path: { app, id } } },
            );
            if (!data) throw new Error("Günlük yüklenemedi.");
            return data.teslimler;
        },
    });
    const yeniden = async (teslim: number) => {
        await api.POST(
            "/panel/api/uygulamalar/{app}/webhooklar/{id}/teslimler/{teslim}/yeniden",
            { params: { path: { app, id, teslim }, header: JETON } },
        );
        await istemci.invalidateQueries({ queryKey: anahtar });
        await yenile();
    };
    if (!sorgu.data) return <p className="text-soluk">Yükleniyor…</p>;
    if (!sorgu.data.length)
        return <p className="text-soluk">Henüz teslim yok.</p>;
    return (
        <table aria-label="Teslim günlüğü" className="w-full text-[13px]">
            <tbody>
                {sorgu.data.map((t) => (
                    <tr key={t.id} data-teslim={t.id}>
                        <td className="py-1 pr-3">{zaman(t.zaman)}</td>
                        <td className="py-1 pr-3" title={t.olay}>
                            {olayAdi(t.olay)}
                        </td>
                        <td className="py-1 pr-3">
                            {t.durum} ({t.deneme})
                            {t.sonHata ? ` — ${t.sonHata}` : ""}
                        </td>
                        <td className="py-1">
                            {t.durum !== "bekliyor" ? (
                                <Dugme onClick={() => void yeniden(t.id)}>
                                    Yeniden gönder
                                </Dugme>
                            ) : null}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
