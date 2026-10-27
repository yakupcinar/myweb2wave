// Ürün bağlamı (A4): uygulama başına ürün tanımı; AI asistanı huni metinlerini
// yazarken sistem isteminde okur. Veri /panel/api/uygulamalar/{app}/baglam
// (sözleşme UrunBaglami); yazma kabuğun CSRF jetonuyla (api.ts).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";

type UrunBaglami = components["schemas"]["UrunBaglami"];

/** Alan, sınır (sözleşmedeki maxLength ile aynı), açıklama, örnek. */
const ALANLAR: {
    ad: keyof UrunBaglami;
    etiket: string;
    azami: number;
    aciklama: string;
    ornek: string;
    uzun?: true;
}[] = [
    {
        ad: "urun",
        etiket: "Ürün ne yapıyor",
        azami: 1000,
        aciklama: "Kısa, somut: ne işe yarıyor, kimin derdini çözüyor.",
        ornek: "Reklamsız, sade birebir mesajlaşma; iPhone ve tarayıcıdan.",
        uzun: true,
    },
    {
        ad: "kategori",
        etiket: "Kategori",
        azami: 100,
        aciklama: "Sektör ya da uygulama türü.",
        ornek: "Mesajlaşma",
    },
    {
        ad: "kitle",
        etiket: "Hedef kitle",
        azami: 500,
        aciklama: "Reklamdan kim geliyor; yaş, durum, beklenti.",
        ornek: "Kalabalık gruplardan yorulmuş, ailesiyle sade yazışmak isteyen 30–55 yaş.",
        uzun: true,
    },
    {
        ad: "ton",
        etiket: "Ton",
        azami: 200,
        aciklama: "Metinler nasıl konuşsun.",
        ornek: "Sıcak, sade, abartısız; 'sen' diye",
    },
    {
        ad: "yasaklar",
        etiket: "Asla yazma / kaçın",
        azami: 1000,
        aciklama:
            "AI'ın kesinlikle yazmaması gerekenler: vaatler, rakip adları, yasal riskler.",
        ornek: "Rakip uygulama adı verme. 'Tamamen güvenli' gibi kesin güvenlik vaadi yazma.",
        uzun: true,
    },
];

export function Baglam(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const [app, setApp] = useState<string | null>(null);
    const secili = app ?? uygulamalar.data?.[0] ?? null;

    return (
        <>
            <SayfaBasi
                baslik="Ürün bağlamı"
                aciklama="AI asistanı huni metinlerini yazarken bunu okur. Boş alan gönderilmez."
            >
                {uygulamalar.data && uygulamalar.data.length > 1 ? (
                    <select
                        aria-label="Uygulama"
                        value={secili ?? ""}
                        onChange={(e) => setApp(e.currentTarget.value)}
                        className="min-h-9 rounded-lg border border-kenar bg-yuzey px-2.5"
                    >
                        {uygulamalar.data.map((a) => (
                            <option key={a}>{a}</option>
                        ))}
                    </select>
                ) : null}
            </SayfaBasi>
            {secili ? (
                <BaglamFormu key={secili} app={secili} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </>
    );
}

function BaglamFormu({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["baglam", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/baglam",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("bağlam alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Bağlam yüklenemedi. Sayfayı yenileyin.
            </p>
        );
    // Form veri gelince çizilir: önce boş çizilip sonra doldurulsaydı, bu
    // arada yazılan metni gelen veri ezerdi (test yakaladı).
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    return <BaglamGovdesi app={app} kayitli={sorgu.data} />;
}

function BaglamGovdesi({
    app,
    kayitli,
}: {
    app: string;
    kayitli: UrunBaglami;
}): ReactNode {
    const istemci = useQueryClient();
    const [form, setForm] = useState<UrunBaglami>(kayitli);
    const kayit = useMutation({
        mutationFn: async (govde: UrunBaglami) => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/baglam",
                {
                    // Sözleşme başlığı zorunlu tutuyor; asıl jetonu api.ts yazar.
                    params: { path: { app }, header: { "X-CSRF-Token": "" } },
                    body: govde,
                },
            );
            if (!data)
                throw new Error(
                    (error?.hatalar ?? ["Kaydedilmedi."]).join(" "),
                );
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["baglam", app], data),
    });
    const degisti = JSON.stringify(form) !== JSON.stringify(kayitli);

    return (
        <Kart>
            <KartBasi
                baslik={app}
                aciklama="Her istekte Claude'a bu uygulamanın tanımı olarak gider."
            />
            <form
                className="grid gap-5 px-4.5 py-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    kayit.mutate(form);
                }}
            >
                {ALANLAR.map((a) => {
                    const ortak = {
                        id: `baglam-${a.ad}`,
                        value: form[a.ad],
                        maxLength: a.azami,
                        placeholder: a.ornek,
                        "aria-describedby": `baglam-${a.ad}-aciklama`,
                        className:
                            "w-full rounded-lg border border-kenar bg-zemin px-3 py-2 text-metin",
                        onChange: (e: { currentTarget: { value: string } }) => {
                            const deger = e.currentTarget.value;
                            setForm((f) => ({ ...f, [a.ad]: deger }));
                        },
                    };
                    return (
                        <div key={a.ad} className="grid gap-1.5">
                            <div className="flex items-baseline justify-between gap-3">
                                <label
                                    htmlFor={ortak.id}
                                    className="font-semibold"
                                >
                                    {a.etiket}
                                </label>
                                <span className="text-xs text-soluk tabular-nums">
                                    {form[a.ad].length}/{a.azami}
                                </span>
                            </div>
                            {a.uzun ? (
                                <textarea rows={3} {...ortak} />
                            ) : (
                                <input {...ortak} />
                            )}
                            <p
                                id={`baglam-${a.ad}-aciklama`}
                                className="text-[13px] text-soluk"
                            >
                                {a.aciklama}
                            </p>
                        </div>
                    );
                })}
                <div className="flex flex-wrap items-center gap-3">
                    <Dugme
                        tur="ana"
                        type="submit"
                        disabled={!degisti || kayit.isPending}
                    >
                        {kayit.isPending ? "Kaydediliyor…" : "Kaydet"}
                    </Dugme>
                    {kayit.isSuccess && !degisti ? (
                        <span role="status" className="text-iyi">
                            Kaydedildi. Sonraki AI isteğinden itibaren
                            kullanılır.
                        </span>
                    ) : null}
                    {kayit.isError ? (
                        <span role="alert" className="text-tehlike">
                            {kayit.error.message}
                        </span>
                    ) : null}
                </div>
            </form>
        </Kart>
    );
}
