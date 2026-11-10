// Rapor (A6/2, B5): reklamdan mı geldi, ödedi mi; izin oranı; anket.
// Veri /panel/api/rapor (sözleşme PanelRaporu); hesaplama yalnız gösterim
// (toplam, yüzde, şerit) — sayımların kendisi çekirdekte.
import { useQuery } from "@tanstack/react-query";
import { lazy, Suspense, type ReactNode } from "react";
import { Link, useSearchParams } from "react-router";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { tutarlar, yuzde, zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";

// P4: grafik kitaplığı (Recharts) ayrı parça — Huniler/düzenleyici indirmez.
const GunlukGrafikler = lazy(async () => ({
    default: (await import("./GunlukGrafikler")).GunlukGrafikler,
}));
import { cn } from "../ui/cn";
import { Kart, KartBasi } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import { Tablo, Td, Th } from "../ui/Tablo";

type PanelRaporu = components["schemas"]["PanelRaporu"];
type Sayim = components["schemas"]["PanelSayimi"];
type Gun = PanelRaporu["gun"];

const GUNLER: Gun[] = [7, 30, 90];

function gunOku(deger: string | null): Gun {
    const n = Number(deger);
    return GUNLER.find((g) => g === n) ?? 30;
}

export function Rapor(): ReactNode {
    const [sorgu] = useSearchParams();
    const gun = gunOku(sorgu.get("gun"));
    const { data, isError } = useQuery({
        queryKey: ["rapor", gun],
        queryFn: async () => {
            const { data } = await api.GET("/panel/api/rapor", {
                params: { query: { gun } },
            });
            if (!data) throw new Error("rapor alınamadı");
            return data;
        },
    });

    return (
        <>
            <SayfaBasi
                baslik="Rapor"
                aciklama={`Ziyaretçilerin kaçı abone oldu ve ne kadar kazandırdı — son ${gun} gün.`}
            >
                <nav
                    aria-label="Zaman aralığı"
                    className="inline-flex gap-0.5 rounded-[9px] bg-yuzey-2 p-[2px]"
                >
                    {GUNLER.map((g) => (
                        <Link
                            key={g}
                            to={`/panel?gun=${g}`}
                            aria-current={g === gun ? "page" : undefined}
                            className="rounded-[7px] px-3 py-1 text-[13px] whitespace-nowrap text-metin hover:bg-yuzey/60 aria-[current=page]:bg-yuzey aria-[current=page]:font-semibold aria-[current=page]:shadow-sm"
                        >
                            Son {g} gün
                        </Link>
                    ))}
                </nav>
            </SayfaBasi>
            {isError ? (
                <p role="alert" className="text-tehlike">
                    Rapor yüklenemedi. Sayfayı yenileyin.
                </p>
            ) : !data ? (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            ) : (
                <RaporIcerik r={data} />
            )}
        </>
    );
}

export function RaporIcerik({ r }: { r: PanelRaporu }): ReactNode {
    const { reklam, organik } = r.sayimlar;
    // Üstteki özet = reklam + organik (aynı sayımlar, yeni sorgu yok).
    const toplam: Sayim = {
        ziyaret: reklam.ziyaret + organik.ziyaret,
        odemeye_gecti: reklam.odemeye_gecti + organik.odemeye_gecti,
        deneme: reklam.deneme + organik.deneme,
        odedi: reklam.odedi + organik.odedi,
    };
    const izinToplam = r.izinler.kabul + r.izinler.red + r.izinler.secmeyen;
    return (
        <>
            <section
                aria-label="Özet"
                className="mb-5 grid grid-cols-[repeat(auto-fit,minmax(min(160px,100%),1fr))] gap-3"
            >
                <Ozet ad="Sayfayı gördü" deger={toplam.ziyaret} />
                <Ozet ad="Ödemeye geçti" deger={toplam.odemeye_gecti} />
                <Ozet ad="Deneme başladı" deger={toplam.deneme} />
                <Ozet ad="Ödedi" deger={toplam.odedi} />
                <Ozet ad="Dönüşüm" deger={donusum(toplam)} />
            </section>

            <Suspense
                fallback={
                    <p role="status" className="mb-5 text-soluk">
                        Grafikler yükleniyor…
                    </p>
                }
            >
                <GunlukGrafikler gunluk={r.gunluk} />
            </Suspense>

            <div className="mb-5 grid grid-cols-[repeat(auto-fit,minmax(min(260px,100%),1fr))] gap-3">
                <SayimKarti baslik="Reklamdan gelen" s={reklam} />
                <SayimKarti baslik="Organik / doğrudan" s={organik} />
                {/* Ç56 (3): yalnız sayı — kim kabul etti bilgisi yok. */}
                <Kart className="p-4.5" aria-label="Reklam ölçümü izni">
                    <h2 className="mb-3 text-[13px] font-semibold text-soluk">
                        Reklam ölçümü izni
                    </h2>
                    <Liste
                        satirlar={(
                            [
                                ["Kabul etti", r.izinler.kabul],
                                ["Reddetti", r.izinler.red],
                                ["Seçmedi", r.izinler.secmeyen],
                            ] as const
                        ).map(([ad, n]) => [
                            ad,
                            `${n} · ${yuzde(n, izinToplam)}`,
                        ])}
                    />
                </Kart>
            </div>

            {r.adimlar.length ? <Anket r={r} /> : null}

            <Kart aria-label="Ziyaretler">
                <KartBasi
                    baslik="Ziyaretler"
                    aciklama={
                        r.satirlar.length === r.azami
                            ? `En yeni ${r.azami} ziyaret gösteriliyor; sayımlar tüm aralığı kapsar.`
                            : undefined
                    }
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Zaman (UTC)",
                                "Uygulama",
                                "Huni",
                                "Kaynak",
                                "Kampanya",
                                "Aşama",
                                "Abonelik",
                                "E-posta",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {r.satirlar.map((s, i) => (
                            <tr key={i} className="hover:bg-yuzey-2/50">
                                <Td>{zaman(s.zaman)}</Td>
                                <Td>{s.app}</Td>
                                <Td>{s.huni ?? "—"}</Td>
                                <Td>
                                    {s.reklam ? (
                                        <>
                                            <Rozet tur="uyari">reklam</Rozet>{" "}
                                            ·{" "}
                                        </>
                                    ) : null}
                                    {s.kaynak}
                                </Td>
                                <Td>{s.kampanya ?? "—"}</Td>
                                <Td>
                                    <Rozet
                                        tur={
                                            s.asama === "ödedi" ? "iyi" : "notr"
                                        }
                                    >
                                        {s.asama}
                                    </Rozet>
                                </Td>
                                <Td>{s.durum ?? "—"}</Td>
                                <Td>{s.email ?? "—"}</Td>
                            </tr>
                        ))}
                        {r.satirlar.length ? null : (
                            <tr>
                                <Td colSpan={8} className="text-soluk">
                                    Bu aralıkta ziyaret yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
        </>
    );
}

// Ç118: dönüşüm = abone olan ziyaret (deneme dahil) — deneylerle aynı tanım (A17).
function donusum(s: Pick<Sayim, "deneme" | "odedi" | "ziyaret">): string {
    return yuzde(s.deneme + s.odedi, s.ziyaret);
}

function Ozet({
    ad,
    deger,
}: {
    ad: string;
    deger: number | string;
}): ReactNode {
    return (
        <div className="rounded-xl border border-kenar bg-yuzey px-4.5 py-4 shadow-xs">
            <span className="block text-[13px] font-medium text-soluk">
                {ad}
            </span>
            <strong className="mt-1.5 block text-[28px] font-bold tracking-tight tabular-nums">
                {deger}
            </strong>
        </div>
    );
}

function SayimKarti({ baslik, s }: { baslik: string; s: Sayim }): ReactNode {
    return (
        <Kart className="p-4.5" aria-label={baslik}>
            <h2 className="mb-3 text-[13px] font-semibold text-soluk">
                {baslik}
            </h2>
            <Liste
                satirlar={[
                    ["Sayfayı gördü", String(s.ziyaret)],
                    ["Ödemeye geçti", String(s.odemeye_gecti)],
                    ["Deneme başladı", String(s.deneme)],
                    ["Ödedi", String(s.odedi)],
                    ["Dönüşüm", donusum(s)],
                ]}
            />
        </Kart>
    );
}

function Liste({
    satirlar,
}: {
    satirlar: (readonly [string, string])[];
}): ReactNode {
    return (
        <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-2">
            {satirlar.map(([ad, deger]) => (
                <div key={ad} className="contents">
                    <dt className="text-soluk">{ad}</dt>
                    <dd className="text-right font-semibold tabular-nums">
                        {deger}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Anket({ r }: { r: PanelRaporu }): ReactNode {
    // Her huninin ilk adımına ulaşan: oranın paydası.
    const ilk = new Map<string, number>();
    for (const a of r.adimlar) {
        const anahtar = `${a.app}/${a.huni}`;
        if (!ilk.has(anahtar)) ilk.set(anahtar, a.ulasan);
    }
    return (
        <div className="mb-5 grid grid-cols-[repeat(auto-fit,minmax(min(340px,100%),1fr))] gap-3">
            <Kart aria-label="Anket adımları">
                <KartBasi
                    baslik="Anket adımları"
                    aciklama="Kaç ziyaret ulaştı"
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Huni",
                                "#",
                                "Adım",
                                "Ulaşan",
                                "İlk adıma göre",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {r.adimlar.map((a) => {
                            const payda = ilk.get(`${a.app}/${a.huni}`) ?? 0;
                            const oran =
                                payda > 0
                                    ? Math.min((a.ulasan / payda) * 100, 100)
                                    : 0;
                            return (
                                <tr key={`${a.app}/${a.huni}/${a.sira}`}>
                                    <Td>
                                        {a.huni}
                                        {a.app !== "keepchat" ? (
                                            <span className="text-soluk">
                                                {" "}
                                                ({a.app})
                                            </span>
                                        ) : null}
                                    </Td>
                                    <Td>{a.sira + 1}</Td>
                                    <Td>{a.adim}</Td>
                                    <Td>{a.ulasan}</Td>
                                    <Td>
                                        {yuzde(a.ulasan, payda, 0)}
                                        {/* Huni şeridi: genişlik = ilk adıma göre oran. */}
                                        <span
                                            aria-hidden="true"
                                            data-serit=""
                                            className="ml-2.5 inline-block h-2 w-30 overflow-hidden rounded-full bg-yuzey-2 align-middle"
                                        >
                                            <span
                                                className="block h-full rounded-full bg-linear-90 from-vurgu to-[#22c55e]"
                                                style={{
                                                    width: `${oran.toFixed(1)}%`,
                                                }}
                                            />
                                        </span>
                                    </Td>
                                </tr>
                            );
                        })}
                    </tbody>
                </Tablo>
            </Kart>
            <Kart aria-label="Cevaplar">
                <KartBasi baslik="Cevaplar" />
                <Tablo>
                    <thead>
                        <tr>
                            <Th>Soru</Th>
                            <Th>Cevap</Th>
                            <Th>Sayı</Th>
                            <Th>Abone</Th>
                            <Th>Dönüşüm</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {r.cevaplar.map((c) => (
                            <tr key={`${c.app}/${c.soru}/${c.secenek}`}>
                                <Td>{c.soru}</Td>
                                <Td>{c.secenek}</Td>
                                <Td>{c.sayi}</Td>
                                <Td>{c.abone}</Td>
                                {/* M5c: seçen ziyaretten abone olan (deneme dahil, A17). */}
                                <Td>{yuzde(c.abone, c.sayi)}</Td>
                            </tr>
                        ))}
                        {r.cevaplar.length ? null : (
                            <tr>
                                <Td colSpan={5} className="text-soluk">
                                    Henüz cevap yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <Kart aria-label="Kaynak kırılımı">
                <KartBasi
                    baslik="Kaynak kırılımı"
                    aciklama="Gelir: bu ziyaretlerin bugüne kadar tahsil edilen faturaları"
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Kaynak",
                                "Ortam",
                                "Kampanya",
                                "Ziyaret",
                                "Ödemeye geçti",
                                "Deneme",
                                "Ödedi",
                                "Dönüşüm",
                                "Gelir",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {r.utm.map((u) => (
                            <tr
                                key={`${u.kaynak}/${u.ortam ?? ""}/${u.kampanya ?? ""}/${u.reklam}`}
                            >
                                <Td>
                                    {u.reklam ? (
                                        <>
                                            <Rozet tur="uyari">reklam</Rozet>{" "}
                                            ·{" "}
                                        </>
                                    ) : null}
                                    {u.kaynak}
                                </Td>
                                <Td>{u.ortam ?? "—"}</Td>
                                <Td>{u.kampanya ?? "—"}</Td>
                                <Td>{u.ziyaret}</Td>
                                <Td>{u.odemeye_gecti}</Td>
                                <Td>{u.deneme}</Td>
                                <Td>{u.odedi}</Td>
                                <Td>{donusum(u)}</Td>
                                <Td>{tutarlar(u.gelir)}</Td>
                            </tr>
                        ))}
                        {r.utm.length ? null : (
                            <tr>
                                <Td colSpan={9} className="text-soluk">
                                    Bu aralıkta ziyaret yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <Kart aria-label="Meta kampanyaları">
                <KartBasi
                    baslik="Meta kampanyaları"
                    aciklama="Harcama Meta'dan (günlük çekilir); gelir aynı adlı utm_campaign ile Meta'dan gelen ziyaretlerin faturaları · ROAS hesabın para biriminde"
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Kampanya",
                                "Harcama",
                                "Ziyaret",
                                "Ödedi",
                                "Gelir",
                                "ROAS",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {r.kampanyalar.map((k) => (
                            <tr key={`${k.kampanya}/${k.harcama.paraBirimi}`}>
                                <Td>{k.kampanya}</Td>
                                <Td>{tutarlar([k.harcama])}</Td>
                                <Td>{k.ziyaret}</Td>
                                <Td>{k.odedi}</Td>
                                <Td>{tutarlar(k.gelir)}</Td>
                                <Td>
                                    {k.roas === null
                                        ? "—"
                                        : `${k.roas.toFixed(2).replace(".", ",")}×`}
                                </Td>
                            </tr>
                        ))}
                        {r.kampanyalar.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Bu aralıkta Meta harcaması yok
                                    (META_AD_ACCOUNT_ID ayarlı değilse
                                    çekilmez).
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <Kart aria-label="Kohort geliri">
                <KartBasi
                    baslik="Kohort geliri"
                    aciklama="Ziyaret haftası (UTC, pazartesi) · ziyaret başına birikimli gelir · — = pencere henüz dolmadı"
                />
                <Tablo>
                    <thead>
                        <tr>
                            {[
                                "Hafta",
                                "Ziyaret",
                                "İlk 24 saat",
                                "7 gün",
                                "30 gün",
                                "60 gün",
                            ].map((b) => (
                                <Th key={b}>{b}</Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {r.kohort.map((k) => (
                            <tr key={k.hafta}>
                                <Td>{k.hafta}</Td>
                                <Td>{k.ziyaret}</Td>
                                {k.pencereler.map((p) => (
                                    <Td key={p.gun}>
                                        {p.olgun
                                            ? p.kisiBasi.length
                                                ? tutarlar(p.kisiBasi)
                                                : "0"
                                            : "—"}
                                    </Td>
                                ))}
                            </tr>
                        ))}
                        {r.kohort.length ? null : (
                            <tr>
                                <Td colSpan={6} className="text-soluk">
                                    Bu aralıkta ziyaret yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
            <Kart aria-label="Serbest yanıtlar">
                <KartBasi
                    baslik="Serbest yanıtlar"
                    aciklama={`90 gün içinde, en yeni ${r.azami}`}
                />
                <Tablo>
                    <thead>
                        <tr>
                            {["Zaman (UTC)", "Uygulama", "Soru", "Yanıt"].map(
                                (b) => (
                                    <Th key={b}>{b}</Th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {r.metinCevaplari.map((c, i) => (
                            <tr key={i}>
                                <Td>{zaman(c.zaman)}</Td>
                                <Td>{c.app}</Td>
                                <Td>{c.soru}</Td>
                                <Td
                                    className={cn("min-w-60 whitespace-normal")}
                                >
                                    {c.cevap}
                                </Td>
                            </tr>
                        ))}
                        {r.metinCevaplari.length ? null : (
                            <tr>
                                <Td colSpan={4} className="text-soluk">
                                    Henüz serbest yanıt yok.
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </Tablo>
            </Kart>
        </div>
    );
}
