// Rapor grafikleri (P4): günlük ziyaret ve günlük ödeme. Ölçekleri farklı →
// iki ayrı grafik (çift eksen yok). Renk rolden (--seri-1/2, doğrulayıcıyla
// açık ve koyu yüzeyde geçti); yazılar metin renginde, seri renginde değil.
// Her grafiğin üstünde ipucu, altında tablo görünümü (renk tek kimlik değil).
import type { ReactNode } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import type { components } from "../../sozlesme";
import { Kart, KartBasi } from "../ui/Kart";
import { Tablo, Td, Th } from "../ui/Tablo";

type Gunluk = components["schemas"]["PanelRaporu"]["gunluk"];
type Seri = {
    anahtar: "ziyaret" | "odemeBaslatildi" | "odemeTamamlandi";
    ad: string;
    renk: string;
};

const ZIYARET: Seri[] = [
    { anahtar: "ziyaret", ad: "Ziyaret", renk: "var(--seri-1)" },
];
const ODEME: Seri[] = [
    // Olay sayısı (ödeme oturumu), ziyaret değil: özet kartlarının "Ödemeye
    // geçti / Ödedi"si ziyaret sayar — aynı ad iki farklı sayı gösteriyordu.
    {
        anahtar: "odemeBaslatildi",
        ad: "Başlatılan ödeme",
        renk: "var(--seri-1)",
    },
    {
        anahtar: "odemeTamamlandi",
        // Ç118: olay Stripe'ın "paid" anı — kartlı deneme de (tutar 0) burada.
        ad: "Tamamlanan ödeme (deneme dahil)",
        renk: "var(--seri-2)",
    },
];

// Gün UTC (uç da UTC günü sayar) — yerel saat günü kaydırmasın.
const kisa = new Intl.DateTimeFormat("tr", {
    day: "numeric",
    month: "short",
    timeZone: "UTC",
});
const uzun = new Intl.DateTimeFormat("tr", {
    day: "numeric",
    month: "long",
    weekday: "short",
    timeZone: "UTC",
});
const tarih = (gun: string, b: Intl.DateTimeFormat): string =>
    b.format(new Date(`${gun}T00:00:00Z`));

export function GunlukGrafikler({ gunluk }: { gunluk: Gunluk }): ReactNode {
    return (
        <div className="mb-5 grid grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))] gap-3">
            <Grafik baslik="Günlük ziyaret" seriler={ZIYARET} gunluk={gunluk} />
            <Grafik baslik="Günlük ödeme" seriler={ODEME} gunluk={gunluk} />
        </div>
    );
}

function Grafik({
    baslik,
    seriler,
    gunluk,
}: {
    baslik: string;
    seriler: Seri[];
    gunluk: Gunluk;
}): ReactNode {
    const toplam = (s: Seri): number =>
        gunluk.reduce((t, g) => t + g[s.anahtar], 0);
    const bos = seriler.every((s) => toplam(s) === 0);
    return (
        <Kart aria-label={baslik}>
            <KartBasi
                baslik={baslik}
                aciklama={`${gunluk.length} gün (UTC) · toplam ${seriler.map((s) => `${s.ad.toLocaleLowerCase("tr")} ${toplam(s)}`).join(", ")}`}
            />
            {/* İki seri: gösterge her zaman (tek seride başlık adlandırır). */}
            {seriler.length > 1 ? (
                <ul
                    aria-label="Gösterge"
                    className="flex flex-wrap gap-4 px-4.5 pt-3 text-[13px] text-soluk"
                >
                    {seriler.map((s) => (
                        <li
                            key={s.anahtar}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                aria-hidden="true"
                                className="size-2.5 rounded-[3px]"
                                style={{ background: s.renk }}
                            />
                            {s.ad}
                        </li>
                    ))}
                </ul>
            ) : null}
            {bos ? (
                <p className="px-4.5 py-8 text-center text-soluk">
                    Bu aralıkta kayıt yok.
                </p>
            ) : (
                <div className="h-56 px-2 pt-3" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={gunluk}
                            barGap={2}
                            barCategoryGap="20%"
                            margin={{ top: 4, right: 8, bottom: 0, left: -16 }}
                        >
                            <CartesianGrid
                                vertical={false}
                                stroke="var(--kenar)"
                            />
                            <XAxis
                                dataKey="gun"
                                tickFormatter={(g: string) => tarih(g, kisa)}
                                tick={{ fill: "var(--soluk)", fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                                minTickGap={24}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={{ fill: "var(--soluk)", fontSize: 12 }}
                                axisLine={false}
                                tickLine={false}
                                width={40}
                            />
                            <Tooltip
                                cursor={{ fill: "var(--yuzey-2)" }}
                                content={(p) => (
                                    <Ipucu
                                        active={p.active}
                                        payload={p.payload}
                                        label={p.label}
                                        seriler={seriler}
                                    />
                                )}
                            />
                            {seriler.map((s) => (
                                <Bar
                                    key={s.anahtar}
                                    dataKey={s.anahtar}
                                    name={s.ad}
                                    fill={s.renk}
                                    radius={[4, 4, 0, 0]}
                                    maxBarSize={18}
                                    isAnimationActive={false}
                                />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
            <details className="border-t border-kenar px-4.5 py-3">
                <summary className="cursor-pointer text-[13px] font-semibold text-soluk">
                    Tablo olarak göster
                </summary>
                <Tablo>
                    <thead>
                        <tr>
                            <Th>Gün</Th>
                            {seriler.map((s) => (
                                <Th key={s.anahtar}>{s.ad}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {gunluk.map((g) => (
                            <tr key={g.gun} data-gun={g.gun}>
                                <Td>{tarih(g.gun, uzun)}</Td>
                                {seriler.map((s) => (
                                    <Td key={s.anahtar}>{g[s.anahtar]}</Td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </Tablo>
            </details>
        </Kart>
    );
}

/** Recharts ipucu: yalnız kullandığımız alanlar (tür genelleri burada gerekmez). */
function Ipucu({
    active,
    payload,
    label,
    seriler,
}: {
    active?: boolean | undefined;
    payload?: readonly { payload?: unknown }[] | undefined;
    label?: unknown;
    seriler: Seri[];
}): ReactNode {
    if (!active || !payload?.length || typeof label !== "string") return null;
    const satir = payload[0]?.payload as Gunluk[number] | undefined;
    if (!satir) return null;
    return (
        <div className="rounded-lg border border-kenar bg-yuzey px-3 py-2 text-[13px] text-metin shadow-lg">
            <p className="mb-1 font-semibold">{tarih(label, uzun)}</p>
            {seriler.map((s) => (
                <p key={s.anahtar} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="size-2.5 rounded-[3px]"
                        style={{ background: s.renk }}
                    />
                    {s.ad}: <strong>{satir[s.anahtar]}</strong>
                </p>
            ))}
        </div>
    );
}
