// Kişisel API anahtarları (A5a, B6): MCP istemcisi (Claude Code, Claude
// Desktop…) bununla bağlanır. Anahtar YALNIZ oluşturulunca bir kez görünür;
// listede önek. İptal geri alınamaz — tarayıcı onay penceresi yerine iki
// adımlı düğme (diyalog akışı kilitler). P7d (A25): Entegrasyonlar'ın
// bölümü; iptal edilenler katlanır bölümde, oradan kalıcı silinir.
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { zaman } from "../bicim";
import { alanSinifi, kutuSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type ApiAnahtari = components["schemas"]["ApiAnahtari"];

const JETON = { "X-CSRF-Token": "" };

export function AnahtarBolumu({ app }: { app: string }): ReactNode {
    return (
        <Bolum
            id="anahtarlar"
            baslik="API anahtarları"
            aciklama="Claude (Code ya da Desktop) panele bu anahtarla bağlanır. Huniye yalnız taslak olarak yazar."
        >
            <AnahtarListesi app={app} />
        </Bolum>
    );
}

function AnahtarListesi({ app }: { app: string }): ReactNode {
    const istemci = useQueryClient();
    const sorgu = useQuery({
        queryKey: ["anahtarlar", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/anahtarlar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("anahtarlar alınamadı");
            return data;
        },
    });
    const [ad, setAd] = useState("");
    // M7c2 (A21): yazma araçları yalnız bilerek — varsayılan kapalı.
    const [yonetim, setYonetim] = useState(false);
    const [yeni, setYeni] = useState<{ ad: string; anahtar: string } | null>(
        null,
    );
    const [iptalSor, setIptalSor] = useState<number | null>(null);
    const [iptallerAcik, setIptallerAcik] = useState(false);
    const yenile = (): Promise<void> =>
        istemci.invalidateQueries({ queryKey: ["anahtarlar", app] });

    const olustur = useMutation({
        mutationFn: async ({ adi, izin }: { adi: string; izin: boolean }) => {
            const { data, error } = await api.POST(
                "/panel/api/uygulamalar/{app}/anahtarlar",
                {
                    // Sözleşme başlığı zorunlu tutuyor; asıl jetonu api.ts yazar.
                    params: { path: { app }, header: JETON },
                    body: { ad: adi, yonetim: izin },
                },
            );
            if (!data)
                throw new Error(
                    (error?.hatalar ?? ["Oluşturulmadı."]).join(" "),
                );
            return data;
        },
        onSuccess: async (d) => {
            setYeni({ ad: d.ad, anahtar: d.anahtar });
            setAd("");
            setYonetim(false);
            await yenile();
        },
    });
    const iptal = useMutation({
        mutationFn: async (id: number) => {
            const { error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/anahtarlar/{id}",
                { params: { path: { app, id }, header: JETON } },
            );
            if (error) throw new Error(error.hatalar.join(" "));
        },
        onSettled: async () => {
            setIptalSor(null);
            await yenile();
        },
    });

    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Anahtarlar yüklenemedi.
            </p>
        );
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    const mcp = sorgu.data.mcpAdresi;
    const etkinler = sorgu.data.anahtarlar.filter((a) => !a.iptal);
    const iptaller = sorgu.data.anahtarlar.filter((a) => a.iptal);

    return (
        <>
            {yeni ? (
                <YeniAnahtar {...yeni} mcp={mcp} kapat={() => setYeni(null)} />
            ) : null}
            <Kart aria-label="Anahtarlar">
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Ad",
                                "Önek",
                                "Oluşturma (UTC)",
                                "Son kullanım (UTC)",
                                "İzin",
                                "",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {etkinler.map((a: ApiAnahtari) => (
                            <tr key={a.id}>
                                <Td>{a.ad}</Td>
                                <Td className="font-mono text-[12.5px]">
                                    {a.onek}…
                                </Td>
                                <Td>{zaman(a.olusturma)}</Td>
                                <Td>
                                    {a.sonKullanim ? zaman(a.sonKullanim) : "—"}
                                </Td>
                                <Td>
                                    {a.yonetim ? (
                                        <Rozet tur="uyari">yönetim</Rozet>
                                    ) : (
                                        <span className="text-soluk">
                                            okuma
                                        </span>
                                    )}
                                </Td>
                                <Td className="text-right">
                                    {iptalSor === a.id ? (
                                        <span className="inline-flex gap-2">
                                            <Dugme
                                                tur="yikici"
                                                onClick={() =>
                                                    iptal.mutate(a.id)
                                                }
                                            >
                                                Evet, iptal et
                                            </Dugme>
                                            <Dugme
                                                tur="sade"
                                                onClick={() =>
                                                    setIptalSor(null)
                                                }
                                            >
                                                Vazgeç
                                            </Dugme>
                                        </span>
                                    ) : (
                                        <Dugme
                                            tur="tehlike"
                                            onClick={() => setIptalSor(a.id)}
                                        >
                                            İptal et
                                        </Dugme>
                                    )}
                                </Td>
                            </tr>
                        ))}
                        {etkinler.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Etkin anahtar yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
                {iptaller.length ? (
                    <div className="border-t border-kenar">
                        <button
                            type="button"
                            aria-expanded={iptallerAcik}
                            className="w-full px-[18px] py-3 text-left text-sm text-vurgu"
                            onClick={() => setIptallerAcik((x) => !x)}
                        >
                            İptal edilenler ({iptaller.length})
                        </button>
                        {iptallerAcik ? (
                            <IptalEdilenler
                                app={app}
                                anahtarlar={iptaller}
                                yenile={yenile}
                            />
                        ) : null}
                    </div>
                ) : null}
            </Kart>
            <Kart>
                <KartBasi
                    baslik="Yeni anahtar"
                    aciklama={`MCP adresi: ${mcp}`}
                />
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        olustur.mutate({ adi: ad, izin: yonetim });
                    }}
                >
                    <Satir etiket="Ad">
                        {(id) => (
                            <input
                                id={id}
                                value={ad}
                                maxLength={100}
                                placeholder="Claude Code — dizüstü"
                                onChange={(e) => setAd(e.currentTarget.value)}
                                className={alanSinifi}
                            />
                        )}
                    </Satir>
                    <div className="grid items-start gap-x-4 gap-y-1.5 border-t border-kenar px-[18px] py-3.5 md:grid-cols-[220px_minmax(0,1fr)]">
                        <span className="text-[15px]">İzin</span>
                        <label className="flex items-start gap-2">
                            <input
                                type="checkbox"
                                checked={yonetim}
                                onChange={(e) =>
                                    setYonetim(e.currentTarget.checked)
                                }
                                className={`${kutuSinifi} mt-0.5`}
                            />
                            <span>
                                <span className="text-[15px]">
                                    Abonelik ve deney yönetimi
                                </span>
                                <span className="block text-[13px] text-soluk">
                                    MCP'de iptal planlama, iade ve deney başlat
                                    / durdur araçları açılır. Yalnız
                                    güvendiğiniz istemciye verin.
                                </span>
                            </span>
                        </label>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-kenar px-[18px] py-3.5">
                        {olustur.isError ? (
                            <span role="alert" className="text-tehlike">
                                {olustur.error.message}
                            </span>
                        ) : null}
                        <Dugme
                            tur="ana"
                            type="submit"
                            disabled={!ad.trim() || olustur.isPending}
                        >
                            Oluştur
                        </Dugme>
                    </div>
                </form>
            </Kart>
        </>
    );
}

/** P7d (A25): iptal edilmiş anahtarlar — kalıcı silme iki adımlı. */
function IptalEdilenler({
    app,
    anahtarlar,
    yenile,
}: {
    app: string;
    anahtarlar: ApiAnahtari[];
    yenile: () => Promise<void>;
}): ReactNode {
    const [silSor, setSilSor] = useState<number | null>(null);
    const sil = useMutation({
        mutationFn: async (id: number) => {
            const { error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/anahtarlar/iptal-edilenler/{id}",
                { params: { path: { app, id }, header: JETON } },
            );
            if (error) throw new Error(error.hatalar.join(" "));
        },
        onSuccess: async () => {
            setSilSor(null);
            await yenile();
        },
    });
    return (
        <Tablo aria-label="İptal edilen anahtarlar">
            <tbody>
                {anahtarlar.map((a) => (
                    <tr key={a.id} className="text-soluk">
                        <Td>{a.ad}</Td>
                        <Td className="font-mono text-[12.5px]">{a.onek}…</Td>
                        <Td>{zaman(a.olusturma)}</Td>
                        <Td>{a.sonKullanim ? zaman(a.sonKullanim) : "—"}</Td>
                        <Td className="text-right">
                            {silSor === a.id ? (
                                <span className="inline-flex flex-wrap items-center justify-end gap-2">
                                    <span className="text-metin">
                                        Kalıcı silinsin mi? Geri alınamaz.
                                    </span>
                                    <Dugme
                                        tur="yikici"
                                        disabled={sil.isPending}
                                        onClick={() => sil.mutate(a.id)}
                                    >
                                        Evet, sil
                                    </Dugme>
                                    <Dugme
                                        tur="sade"
                                        onClick={() => setSilSor(null)}
                                    >
                                        Vazgeç
                                    </Dugme>
                                </span>
                            ) : (
                                <Dugme
                                    tur="tehlike"
                                    onClick={() => setSilSor(a.id)}
                                >
                                    Sil
                                </Dugme>
                            )}
                            {sil.isError && silSor === a.id ? (
                                <span
                                    role="alert"
                                    className="block text-tehlike"
                                >
                                    {sil.error.message}
                                </span>
                            ) : null}
                        </Td>
                    </tr>
                ))}
            </tbody>
        </Tablo>
    );
}

function YeniAnahtar({
    ad,
    anahtar,
    mcp,
    kapat,
}: {
    ad: string;
    anahtar: string;
    mcp: string;
    kapat: () => void;
}): ReactNode {
    const komut = `claude mcp add --transport http myweb2wave ${mcp} --header "Authorization: Bearer ${anahtar}"`;
    const [kopya, setKopya] = useState("");
    const kopyala = (metin: string, ne: string): void => {
        void navigator.clipboard?.writeText(metin).then(() => setKopya(ne));
    };
    return (
        <Kart aria-label="Yeni anahtar" className="border-iyi">
            <KartBasi
                baslik={`“${ad}” anahtarı oluşturuldu`}
                aciklama="Şimdi kopyala — bu anahtar bir daha GÖSTERİLMEZ."
            />
            <div className="grid gap-3 px-4.5 py-4">
                <code
                    data-anahtar=""
                    className="block break-all rounded-lg bg-yuzey-2 p-3 font-mono text-[13px]"
                >
                    {anahtar}
                </code>
                <p className="text-[13px] text-soluk">
                    Claude Code'a eklemek için terminalde:
                </p>
                <code className="block break-all rounded-lg bg-yuzey-2 p-3 font-mono text-[12.5px]">
                    {komut}
                </code>
                <div className="flex flex-wrap items-center gap-2">
                    <Dugme
                        onClick={() => kopyala(anahtar, "Anahtar kopyalandı.")}
                    >
                        Anahtarı kopyala
                    </Dugme>
                    <Dugme onClick={() => kopyala(komut, "Komut kopyalandı.")}>
                        Komutu kopyala
                    </Dugme>
                    <Dugme tur="ana" onClick={kapat}>
                        Kaydettim, kapat
                    </Dugme>
                    {kopya ? (
                        <span role="status" className="text-iyi">
                            {kopya}
                        </span>
                    ) : null}
                </div>
            </div>
        </Kart>
    );
}
