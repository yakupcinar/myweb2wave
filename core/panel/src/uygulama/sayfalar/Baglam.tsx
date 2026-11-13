// Ürün bağlamı (A4): uygulama başına ürün tanımı; AI asistanı huni metinlerini
// yazarken sistem isteminde okur. Veri /panel/api/uygulamalar/{app}/baglam
// (sözleşme UrunBaglami); yazma kabuğun CSRF jetonuyla (api.ts). P7d (A25):
// Entegrasyonlar'ın bölümü, satır düzeninde (solda etiket).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { alanSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";

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

export function BaglamBolumu({ app }: { app: string }): ReactNode {
    return (
        <Bolum
            id="baglam"
            baslik="Ürün bağlamı (AI)"
            aciklama="AI, huni metinlerini yazarken uygulamanı bu bilgilerle tanır. Boş alan gönderilmez."
        >
            <BaglamFormu app={app} />
        </Bolum>
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
        <form
            className="flex flex-col gap-2.5"
            onSubmit={(e) => {
                e.preventDefault();
                kayit.mutate(form);
            }}
        >
            <Kart>
                {ALANLAR.map((a) => (
                    <Satir
                        key={a.ad}
                        etiket={a.etiket}
                        yardim={
                            <span className="flex justify-between gap-3">
                                <span>{a.aciklama}</span>
                                <span className="tabular-nums">
                                    {form[a.ad].length}/{a.azami}
                                </span>
                            </span>
                        }
                    >
                        {(id) => {
                            const ortak = {
                                id,
                                value: form[a.ad],
                                maxLength: a.azami,
                                placeholder: a.ornek,
                                className: alanSinifi,
                                onChange: (e: {
                                    currentTarget: { value: string };
                                }) => {
                                    const deger = e.currentTarget.value;
                                    setForm((f) => ({ ...f, [a.ad]: deger }));
                                },
                            };
                            return a.uzun ? (
                                <textarea rows={3} {...ortak} />
                            ) : (
                                <input {...ortak} />
                            );
                        }}
                    </Satir>
                ))}
            </Kart>
            <div className="flex flex-wrap items-center justify-end gap-3">
                {kayit.isSuccess && !degisti ? (
                    <span role="status" className="text-iyi">
                        Kaydedildi. Sonraki AI isteğinden itibaren kullanılır.
                    </span>
                ) : null}
                {kayit.isError ? (
                    <span role="alert" className="text-tehlike">
                        {kayit.error.message}
                    </span>
                ) : null}
                <Dugme
                    tur="ana"
                    type="submit"
                    className="min-h-11 px-5.5 text-[15px]"
                    disabled={!degisti || kayit.isPending}
                >
                    {kayit.isPending ? "Kaydediliyor…" : "Kaydet"}
                </Dugme>
            </div>
        </form>
    );
}
