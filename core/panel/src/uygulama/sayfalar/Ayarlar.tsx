// Uygulama ayarları (M9a, A23): görünen ad, URL şeması, mağaza adresi, destek
// e-postası. Veri /panel/api/uygulamalar/{app}/ayarlar (sözleşme
// UygulamaAyarlari); yazma kabuğun CSRF jetonuyla (api.ts).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";

type UygulamaAyarlari = components["schemas"]["UygulamaAyarlari"];

/** Alan, sınır (sözleşmedeki maxLength ile aynı), girdi türü, açıklama, örnek. */
const ALANLAR: {
    ad: keyof UygulamaAyarlari;
    etiket: string;
    azami: number;
    tur: "text" | "url" | "email";
    aciklama: string;
    ornek: string;
}[] = [
    {
        ad: "ad",
        etiket: "Görünen ad",
        azami: 60,
        tur: "text",
        aciklama: "E-postalarda gönderen adı olarak görünür.",
        ornek: "KeepChat",
    },
    {
        ad: "sema",
        etiket: "URL şeması",
        azami: 32,
        tur: "text",
        aciklama:
            "Giriş bağlantısı uygulamayı bununla açar (şema://giris?jeton=…). iOS uygulamasının Info.plist'indekiyle aynı olmalı; değiştirirseniz daha önce gönderilen bağlantılar açılmaz.",
        ornek: "keepchat",
    },
    {
        ad: "magazaAdresi",
        etiket: "App Store adresi",
        azami: 300,
        tur: "url",
        aciklama:
            'Aktivasyon e-postasında "uygulamayı kurun" bağlantısı. Boşsa yazılmaz.',
        ornek: "https://apps.apple.com/app/id…",
    },
    {
        ad: "destekEpostasi",
        etiket: "Destek e-postası",
        azami: 254,
        tur: "email",
        aciklama:
            "E-postalara verilen yanıtlar buraya gider. Boşsa yanıt adresi yok.",
        ornek: "destek@ornek.com",
    },
];

export function Ayarlar(): ReactNode {
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
                baslik="Ayarlar"
                aciklama="Uygulamanın kimliği: e-postalarda ve giriş bağlantısında kullanılır."
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
                <AyarFormu key={secili} app={secili} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </>
    );
}

function AyarFormu({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["ayarlar", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/ayarlar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("ayarlar alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Ayarlar yüklenemedi. Sayfayı yenileyin.
            </p>
        );
    // Form veri gelince çizilir (Baglam ile aynı sebep: yazılanı gelen veri ezmesin).
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    return <AyarGovdesi app={app} kayitli={sorgu.data} />;
}

function AyarGovdesi({
    app,
    kayitli,
}: {
    app: string;
    kayitli: UygulamaAyarlari;
}): ReactNode {
    const istemci = useQueryClient();
    const [form, setForm] = useState<UygulamaAyarlari>(kayitli);
    const kayit = useMutation({
        mutationFn: async (govde: UygulamaAyarlari) => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/ayarlar",
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
        onSuccess: (data) => istemci.setQueryData(["ayarlar", app], data),
    });
    const degisti = JSON.stringify(form) !== JSON.stringify(kayitli);

    return (
        <Kart>
            <KartBasi baslik={app} />
            <form
                className="grid gap-5 px-4.5 py-4"
                // Doğrulama sunucuda (422 mesajı satırda); tarayıcının kendi
                // url/email denetimi ayrı, farklı bir kural koymasın.
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    kayit.mutate(form);
                }}
            >
                {ALANLAR.map((a) => (
                    <div key={a.ad} className="grid gap-1.5">
                        <label
                            htmlFor={`ayar-${a.ad}`}
                            className="font-semibold"
                        >
                            {a.etiket}
                        </label>
                        <input
                            id={`ayar-${a.ad}`}
                            type={a.tur}
                            value={form[a.ad]}
                            maxLength={a.azami}
                            placeholder={a.ornek}
                            aria-describedby={`ayar-${a.ad}-aciklama`}
                            className="w-full rounded-lg border border-kenar bg-zemin px-3 py-2 text-metin"
                            onChange={(e) => {
                                const deger = e.currentTarget.value;
                                setForm((f) => ({ ...f, [a.ad]: deger }));
                            }}
                        />
                        <p
                            id={`ayar-${a.ad}-aciklama`}
                            className="text-[13px] text-soluk"
                        >
                            {a.aciklama}
                        </p>
                    </div>
                ))}
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
                            Kaydedildi.
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
