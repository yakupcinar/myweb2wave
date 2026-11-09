// M7b (A20): giden webhook hedefleri — adres + olaylar, sır yalnız eklemede
// bir kez görünür; etkin / pasif, sil (iki adımlı onay), teslim günlüğü ve
// yeniden gönder. Gövdede kişisel veri yok; imza Standard Webhooks.
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type FormEvent, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Hedef = components["schemas"]["WebhookHedefi"];

export function Webhooklar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const app = uygulamalar.data?.[0];
    if (!app) return <p className="text-soluk">Yükleniyor…</p>;
    return <HedefListesi app={app} />;
}

const JETON = { "X-CSRF-Token": "" };

function HedefListesi({ app }: { app: string }): ReactNode {
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
        <>
            <SayfaBasi
                baslik="Webhook'lar"
                aciklama="Olaylar seçtiğiniz adrese imzalı POST edilir (Standard Webhooks); kişisel veri gitmez, teslim en az bir kez."
            />
            {sir ? (
                <div
                    role="alert"
                    className="mb-4 rounded-lg border border-kenar bg-yuzey p-4"
                >
                    <strong>
                        Sırrı şimdi kopyalayın — bir daha gösterilmez:
                    </strong>{" "}
                    <code className="font-mono break-all">{sir}</code>
                </div>
            ) : null}
            <Kart aria-label="Yeni hedef" className="mb-4 p-4.5">
                <form onSubmit={(e) => void ekle(e)} className="grid gap-3">
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Adres (https)</span>
                        <input
                            type="url"
                            required
                            value={adres}
                            placeholder="https://ornek.com/kanca"
                            onChange={(e) => setAdres(e.currentTarget.value)}
                            className="min-h-9 rounded-lg border border-kenar bg-zemin px-3"
                        />
                    </label>
                    <fieldset className="grid gap-1.5">
                        <legend className="font-semibold">Olaylar</legend>
                        <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                            {(sorgu.data?.olaylar ?? []).map((o) => (
                                <label
                                    key={o}
                                    className="flex items-center gap-1.5"
                                >
                                    <input
                                        type="checkbox"
                                        checked={secili.includes(o)}
                                        onChange={(e) => {
                                            // Değer şimdi okunur: güncelleyici sonra (çizimde) koşar,
                                            // currentTarget o an null (test yakaladı).
                                            const isaretli =
                                                e.currentTarget.checked;
                                            setSecili((s) =>
                                                isaretli
                                                    ? [...s, o]
                                                    : s.filter((x) => x !== o),
                                            );
                                        }}
                                    />
                                    <span className="font-mono text-[12.5px]">
                                        {o}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    {hata ? (
                        <p role="alert" className="text-tehlike">
                            {hata}
                        </p>
                    ) : null}
                    <div>
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
            <Kart aria-label="Hedefler">
                <KartBasi baslik="Hedefler" />
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
        </>
    );
}

function HedefSatiri({
    app,
    h,
    yenile,
}: {
    app: string;
    h: Hedef;
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
                <Td className="font-mono text-[12.5px] whitespace-normal">
                    {h.olaylar.join(", ")}
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
                                    tur="ana"
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
                                <Dugme onClick={() => setSilOnay(false)}>
                                    Vazgeç
                                </Dugme>
                            </>
                        ) : (
                            <Dugme onClick={() => setSilOnay(true)}>Sil</Dugme>
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
                        <td className="py-1 pr-3 font-mono">{t.olay}</td>
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
