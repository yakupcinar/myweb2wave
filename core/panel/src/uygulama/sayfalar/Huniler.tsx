// Huniler (P2; A7, A8): uygulama başına huniler ve reklam adresinin hangi
// huniyi açacağını seçen kurallar. Düzenleyici (Puck) henüz ayrı sayfa (P3)
// → "Düzenle" ve yeni huni tam sayfa yüklemesiyle açılır. Kaldırma geri
// alınamaz: tarayıcı onay penceresi yerine iki adımlı düğme.
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Dugme, dugmeSinifi } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type Liste = components["schemas"]["PanelHunileri"];
type Huni = Liste["huniler"][number];

/** Tam sayfa geçişi (düzenleyici React uygulamasının dışında); testte izlenir. */
export const gezgin = {
    git: (adres: string): void => window.location.assign(adres),
};

// Sözleşme başlığı zorunlu tutuyor; asıl jetonu api.ts yazar.
const JETON = { "X-CSRF-Token": "" };

const girdi = "min-h-9 rounded-lg border border-kenar bg-zemin px-3 text-metin";

async function hatasiz(
    istek: Promise<{ error?: { hatalar: string[] }; response: Response }>,
): Promise<void> {
    const { error, response } = await istek;
    if (!response.ok)
        throw new Error((error?.hatalar ?? ["İşlem yapılamadı."]).join(" "));
}

export function Huniler(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    return (
        <>
            <SayfaBasi
                baslik="Huniler"
                aciklama="Huni sürümleri ve reklam adresinin hangi huniyi açacağı"
            />
            {uygulamalar.data ? (
                <div className="grid gap-8">
                    {uygulamalar.data.map((app) => (
                        <Uygulama key={app} app={app} />
                    ))}
                </div>
            ) : (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            )}
        </>
    );
}

function Uygulama({ app }: { app: string }): ReactNode {
    const istemci = useQueryClient();
    const sorgu = useQuery({
        queryKey: ["huniler", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/huniler",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("huniler alınamadı");
            return data;
        },
    });
    const [bildirim, setBildirim] = useState<{
        tur: "iyi" | "hata";
        metin: string;
    } | null>(null);
    const islem = useMutation({
        mutationFn: async (is: { yap: () => Promise<void>; tamam: string }) => {
            await is.yap();
            return is.tamam;
        },
        onSuccess: (metin) => setBildirim({ tur: "iyi", metin }),
        onError: (e) => setBildirim({ tur: "hata", metin: e.message }),
        onSettled: () =>
            istemci.invalidateQueries({ queryKey: ["huniler", app] }),
    });
    const calistir = (yap: () => Promise<void>, tamam: string): void =>
        islem.mutate({ yap, tamam });

    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                {app}: huniler yüklenemedi.
            </p>
        );
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    const veri = sorgu.data;

    return (
        <section aria-label={app} className="grid gap-5">
            {bildirim ? (
                <p
                    role={bildirim.tur === "hata" ? "alert" : "status"}
                    className={
                        bildirim.tur === "hata" ? "text-tehlike" : "text-iyi"
                    }
                >
                    {bildirim.metin}
                </p>
            ) : null}
            <HuniTablosu
                app={app}
                veri={veri}
                calistir={calistir}
                hataGoster={(metin) => setBildirim({ tur: "hata", metin })}
            />
            <Kurallar app={app} veri={veri} calistir={calistir} />
        </section>
    );
}

type Calistir = (yap: () => Promise<void>, tamam: string) => void;

function HuniTablosu({
    app,
    veri,
    calistir,
    hataGoster,
}: {
    app: string;
    veri: Liste;
    calistir: Calistir;
    hataGoster: (metin: string) => void;
}): ReactNode {
    const [kaldirSor, setKaldirSor] = useState<string | null>(null);
    const [ad, setAd] = useState("");
    const [kaynak, setKaynak] = useState("");
    const yeni = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.POST(
                "/panel/api/uygulamalar/{app}/huniler",
                {
                    params: { path: { app }, header: JETON },
                    body: { ad: ad.trim(), ...(kaynak ? { kaynak } : {}) },
                },
            );
            if (!data)
                throw new Error((error?.hatalar ?? ["Açılmadı."]).join(" "));
            return data;
        },
        onSuccess: (d) => gezgin.git(d.duzenle),
        onError: (e) => hataGoster(e.message),
    });
    const kaldir = (h: Huni): void => {
        setKaldirSor(null);
        calistir(
            async () => {
                const { data, error } = await api.DELETE(
                    "/panel/api/uygulamalar/{app}/huniler/{ad}",
                    { params: { path: { app, ad: h.ad }, header: JETON } },
                );
                if (!data)
                    throw new Error(
                        (error?.hatalar ?? ["Kaldırılamadı."]).join(" "),
                    );
            },
            h.ziyaretli
                ? `'${h.ad}' arşivlendi — ziyaret verisi raporda kalır, kuralları kaldırıldı.`
                : `'${h.ad}' silindi.`,
        );
    };

    return (
        <Kart aria-label={`${app} hunileri`}>
            <KartBasi
                baslik={app}
                aciklama={`Reklam adresi tek: ${veri.adres} — hangi huninin açılacağını kurallar seçer; hiçbiri tutmazsa varsayılan.`}
            />
            <Tablo>
                <thead>
                    <tr>
                        {["Huni", "Yayında", "Taslak", ""].map((b) => (
                            <Th key={b}>{b}</Th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {veri.huniler.map((h) => (
                        <tr key={h.ad} data-huni={h.ad}>
                            <Td>
                                <strong>{h.ad}</strong>{" "}
                                {h.varsayilan ? (
                                    <Rozet tur="iyi">varsayılan</Rozet>
                                ) : null}
                            </Td>
                            <Td>{h.yayinda ? `v${h.yayinda}` : "—"}</Td>
                            <Td>
                                {h.farkli ? (
                                    <Rozet tur="uyari">
                                        yayınlanmamış değişiklik
                                    </Rozet>
                                ) : (
                                    <Rozet>yayındakiyle aynı</Rozet>
                                )}
                            </Td>
                            <Td className="text-right">
                                <span className="inline-flex flex-wrap justify-end gap-2">
                                    <a
                                        href={h.duzenle}
                                        className={dugmeSinifi({ tur: "ana" })}
                                    >
                                        Düzenle
                                    </a>
                                    <a
                                        href={h.ac}
                                        target="_blank"
                                        rel="noopener"
                                        title={`${h.yayinda ? "Yayındaki sürüm" : "Taslak"} — 1 saat geçerli, ziyaret sayılmaz, ödeme kapalı`}
                                        className={dugmeSinifi()}
                                    >
                                        Aç
                                    </a>
                                    {h.varsayilan ? null : (
                                        <>
                                            <Dugme
                                                onClick={() =>
                                                    calistir(
                                                        () =>
                                                            hatasiz(
                                                                api.POST(
                                                                    "/panel/api/uygulamalar/{app}/huniler/{ad}/varsayilan",
                                                                    {
                                                                        params: {
                                                                            path: {
                                                                                app,
                                                                                ad: h.ad,
                                                                            },
                                                                            header: JETON,
                                                                        },
                                                                    },
                                                                ),
                                                            ),
                                                        `Varsayılan huni artık '${h.ad}'.`,
                                                    )
                                                }
                                            >
                                                Varsayılan yap
                                            </Dugme>
                                            {kaldirSor === h.ad ? (
                                                <>
                                                    <Dugme
                                                        tur="tehlike"
                                                        onClick={() =>
                                                            kaldir(h)
                                                        }
                                                    >
                                                        {h.ziyaretli
                                                            ? "Evet, arşivle"
                                                            : "Evet, sil"}
                                                    </Dugme>
                                                    <Dugme
                                                        onClick={() =>
                                                            setKaldirSor(null)
                                                        }
                                                    >
                                                        Vazgeç
                                                    </Dugme>
                                                </>
                                            ) : (
                                                // Ç41: ziyaret verisi silinmez — o zaman arşiv.
                                                <Dugme
                                                    tur="tehlike"
                                                    onClick={() =>
                                                        setKaldirSor(h.ad)
                                                    }
                                                >
                                                    {h.ziyaretli
                                                        ? "Arşivle"
                                                        : "Sil"}
                                                </Dugme>
                                            )}
                                        </>
                                    )}
                                </span>
                            </Td>
                        </tr>
                    ))}
                    {veri.huniler.length ? null : (
                        <tr>
                            <Td colSpan={4} className="text-soluk">
                                Henüz huni yok.
                            </Td>
                        </tr>
                    )}
                </tbody>
            </Tablo>
            <form
                aria-label="Yeni huni"
                className="flex flex-wrap items-end gap-3 px-4.5 py-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    yeni.mutate();
                }}
            >
                <label className="grid gap-1.5">
                    <span className="font-semibold">Yeni huni adı</span>
                    <input
                        value={ad}
                        required
                        pattern="[a-z0-9_\-]{1,64}"
                        placeholder="fitness"
                        onChange={(e) => setAd(e.currentTarget.value)}
                        className={girdi}
                    />
                </label>
                <label className="grid gap-1.5">
                    <span className="font-semibold">Kopyala</span>
                    <select
                        value={kaynak}
                        onChange={(e) => setKaynak(e.currentTarget.value)}
                        className={girdi}
                    >
                        <option value="">boş başla</option>
                        {veri.huniler.map((h) => (
                            <option key={h.ad} value={h.ad}>
                                {h.ad}
                            </option>
                        ))}
                    </select>
                </label>
                <Dugme
                    tur="ana"
                    type="submit"
                    disabled={!ad.trim() || yeni.isPending}
                >
                    Huni aç
                </Dugme>
            </form>
        </Kart>
    );
}

function Kurallar({
    app,
    veri,
    calistir,
}: {
    app: string;
    veri: Liste;
    calistir: Calistir;
}): ReactNode {
    const [parametre, setParametre] = useState(veri.parametreler[0] ?? "");
    const [deger, setDeger] = useState("");
    const [huni, setHuni] = useState(veri.huniler[0]?.ad ?? "");
    const tasi = (id: number, yon: "yukari" | "asagi"): void =>
        calistir(
            () =>
                hatasiz(
                    api.POST(
                        "/panel/api/uygulamalar/{app}/kurallar/{id}/tasi",
                        {
                            params: { path: { app, id }, header: JETON },
                            body: { yon },
                        },
                    ),
                ),
            "Kural taşındı.",
        );

    return (
        <Kart aria-label={`${app} kuralları`}>
            <KartBasi
                baslik="Yönlendirme kuralları"
                aciklama="Üstten alta, ilk tutan kazanır."
            />
            <Tablo>
                <thead>
                    <tr>
                        {["#", "Adreste", "→ Huni", ""].map((b) => (
                            <Th key={b}>{b}</Th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {veri.kurallar.map((k, i) => (
                        <tr key={k.id} data-kural={k.id}>
                            <Td>{i + 1}</Td>
                            <Td className="font-mono text-[12.5px]">
                                {k.parametre}={k.deger}
                            </Td>
                            <Td>{k.huni}</Td>
                            <Td className="text-right">
                                <span className="inline-flex gap-2">
                                    <Dugme
                                        aria-label="Yukarı taşı"
                                        disabled={i === 0}
                                        onClick={() => tasi(k.id, "yukari")}
                                    >
                                        ↑
                                    </Dugme>
                                    <Dugme
                                        aria-label="Aşağı taşı"
                                        disabled={
                                            i === veri.kurallar.length - 1
                                        }
                                        onClick={() => tasi(k.id, "asagi")}
                                    >
                                        ↓
                                    </Dugme>
                                    <Dugme
                                        tur="tehlike"
                                        onClick={() =>
                                            calistir(
                                                () =>
                                                    hatasiz(
                                                        api.DELETE(
                                                            "/panel/api/uygulamalar/{app}/kurallar/{id}",
                                                            {
                                                                params: {
                                                                    path: {
                                                                        app,
                                                                        id: k.id,
                                                                    },
                                                                    header: JETON,
                                                                },
                                                            },
                                                        ),
                                                    ),
                                                "Kural silindi.",
                                            )
                                        }
                                    >
                                        Sil
                                    </Dugme>
                                </span>
                            </Td>
                        </tr>
                    ))}
                    {veri.kurallar.length ? null : (
                        <tr>
                            <Td colSpan={4} className="text-soluk">
                                Kural yok — herkes varsayılan huniyi görür.
                            </Td>
                        </tr>
                    )}
                </tbody>
            </Tablo>
            {veri.huniler.length ? (
                <>
                    <form
                        aria-label="Yeni kural"
                        className="flex flex-wrap items-end gap-3 px-4.5 py-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            calistir(
                                () =>
                                    hatasiz(
                                        api.POST(
                                            "/panel/api/uygulamalar/{app}/kurallar",
                                            {
                                                params: {
                                                    path: { app },
                                                    header: JETON,
                                                },
                                                body: {
                                                    parametre,
                                                    deger,
                                                    huni,
                                                },
                                            },
                                        ),
                                    ).then(() => setDeger("")),
                                "Kural eklendi.",
                            );
                        }}
                    >
                        <label className="grid gap-1.5">
                            <span className="font-semibold">Adreste</span>
                            <select
                                value={parametre}
                                onChange={(e) =>
                                    setParametre(e.currentTarget.value)
                                }
                                className={girdi}
                            >
                                {veri.parametreler.map((p) => (
                                    <option key={p} value={p}>
                                        {p}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5">
                            <span className="font-semibold">=</span>
                            <input
                                value={deger}
                                required
                                maxLength={200}
                                placeholder="fitness"
                                onChange={(e) =>
                                    setDeger(e.currentTarget.value)
                                }
                                className={girdi}
                            />
                        </label>
                        <label className="grid gap-1.5">
                            <span className="font-semibold">→ Huni</span>
                            <select
                                value={huni}
                                onChange={(e) => setHuni(e.currentTarget.value)}
                                className={girdi}
                            >
                                {veri.huniler.map((h) => (
                                    <option key={h.ad} value={h.ad}>
                                        {h.ad}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <Dugme tur="ana" type="submit" disabled={!deger.trim()}>
                            Kural ekle
                        </Dugme>
                    </form>
                    <p className="px-4.5 pb-4 text-[13px] text-soluk">
                        Örnek reklam adresi: <code>{veri.adres}?k=fitness</code>{" "}
                        ya da Meta'da{" "}
                        <code>{"utm_campaign={{campaign.name}}"}</code>.
                        Büyük/küçük harf fark etmez. Yayınlanmamış huniyi
                        gösteren kural atlanır.
                    </p>
                </>
            ) : null}
        </Kart>
    );
}
