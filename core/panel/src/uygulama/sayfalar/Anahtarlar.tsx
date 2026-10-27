// Kişisel API anahtarları (A5a, B6): MCP istemcisi (Claude Code, Claude
// Desktop…) bununla bağlanır. Anahtar YALNIZ oluşturulunca bir kez görünür;
// listede önek. İptal geri alınamaz — tarayıcı onay penceresi yerine iki
// adımlı düğme (diyalog akışı kilitler).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type ApiAnahtari = components["schemas"]["ApiAnahtari"];

export function Anahtarlar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const app = uygulamalar.data?.[0];
    return (
        <>
            <SayfaBasi
                baslik="API anahtarları"
                aciklama="Claude Code ya da Claude Desktop MCP ile bu anahtarla bağlanır; huniyi yalnız taslağa yazar."
            />
            {app ? (
                <AnahtarListesi app={app} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </>
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
    const [yeni, setYeni] = useState<{ ad: string; anahtar: string } | null>(
        null,
    );
    const [iptalSor, setIptalSor] = useState<number | null>(null);
    const yenile = (): Promise<void> =>
        istemci.invalidateQueries({ queryKey: ["anahtarlar", app] });

    const olustur = useMutation({
        mutationFn: async (adi: string) => {
            const { data, error } = await api.POST(
                "/panel/api/uygulamalar/{app}/anahtarlar",
                {
                    // Sözleşme başlığı zorunlu tutuyor; asıl jetonu api.ts yazar.
                    params: { path: { app }, header: { "X-CSRF-Token": "" } },
                    body: { ad: adi },
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
            await yenile();
        },
    });
    const iptal = useMutation({
        mutationFn: async (id: number) => {
            const { error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/anahtarlar/{id}",
                {
                    params: {
                        path: { app, id },
                        header: { "X-CSRF-Token": "" },
                    },
                },
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

    return (
        <div className="grid gap-5">
            {yeni ? (
                <YeniAnahtar {...yeni} mcp={mcp} kapat={() => setYeni(null)} />
            ) : null}
            <Kart>
                <KartBasi
                    baslik="Yeni anahtar"
                    aciklama={`MCP adresi: ${mcp}`}
                />
                <form
                    className="flex flex-wrap items-end gap-3 px-4.5 py-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        olustur.mutate(ad);
                    }}
                >
                    <label className="grid gap-1.5">
                        <span className="font-semibold">Ad</span>
                        <input
                            value={ad}
                            maxLength={100}
                            placeholder="Claude Code — dizüstü"
                            onChange={(e) => setAd(e.currentTarget.value)}
                            className="min-h-9 w-72 max-w-full rounded-lg border border-kenar bg-zemin px-3"
                        />
                    </label>
                    <Dugme
                        tur="ana"
                        type="submit"
                        disabled={!ad.trim() || olustur.isPending}
                    >
                        Oluştur
                    </Dugme>
                    {olustur.isError ? (
                        <span role="alert" className="text-tehlike">
                            {olustur.error.message}
                        </span>
                    ) : null}
                </form>
            </Kart>
            <Kart aria-label="Anahtarlar">
                <KartBasi
                    baslik="Anahtarlar"
                    aciklama="Anahtarın kendisi saklanmaz; tanımak için ilk karakterleri görünür."
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Ad",
                                "Önek",
                                "Oluşturma (UTC)",
                                "Son kullanım (UTC)",
                                "Durum",
                                "",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sorgu.data.anahtarlar.map((a: ApiAnahtari) => (
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
                                    {a.iptal ? (
                                        <Rozet>iptal</Rozet>
                                    ) : (
                                        <Rozet tur="iyi">etkin</Rozet>
                                    )}
                                </Td>
                                <Td className="text-right">
                                    {a.iptal ? null : iptalSor === a.id ? (
                                        <span className="inline-flex gap-2">
                                            <Dugme
                                                tur="tehlike"
                                                onClick={() =>
                                                    iptal.mutate(a.id)
                                                }
                                            >
                                                Evet, iptal et
                                            </Dugme>
                                            <Dugme
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
                        {sorgu.data.anahtarlar.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Henüz anahtar yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
        </div>
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
