// Uygulama ayarları (M9a, A23): görünen ad, URL şeması, mağaza adresi, destek
// e-postası. Veri /panel/api/uygulamalar/{app}/ayarlar (sözleşme
// UygulamaAyarlari); yazma kabuğun CSRF jetonuyla (api.ts). P7c: taslaktaki
// gibi üç bölüm — Canlıya geçiş (adımlar sunucuda ölçülür), Uygulama
// bilgileri (solda etiket), Yeni hunilerin görünümü.
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { Link } from "react-router";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { uzunTarih } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { alanSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { cn } from "../ui/cn";
import { Kart } from "../ui/Kart";

type UygulamaAyarlari = components["schemas"]["UygulamaAyarlari"];
type CanliyaGecis = components["schemas"]["CanliyaGecis"];

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
        aciklama: "E-postaların gönderen adı.",
        ornek: "KeepChat",
    },
    {
        ad: "destekEpostasi",
        etiket: "Destek e-postası",
        azami: 254,
        tur: "email",
        aciklama: "Müşteri bir e-postaya yanıt verince buraya gelir.",
        ornek: "destek@ornek.com",
    },
    {
        ad: "magazaAdresi",
        etiket: "App Store adresi",
        azami: 300,
        tur: "url",
        aciklama: '"Uygulamayı indir" bağlantısı. Boşsa e-postada çıkmaz.',
        ornek: "https://apps.apple.com/app/id…",
    },
    {
        ad: "sema",
        etiket: "Uygulamayı açan bağlantı",
        azami: 32,
        tur: "text",
        aciklama:
            "iOS uygulamasının Info.plist'indekiyle aynı olmalı. Değiştirirseniz daha önce gönderilen bağlantılar açılmaz.",
        ornek: "keepchat",
    },
];

/** Paywall belgelerinin adları (Taslaklar::BASLIKLAR, tr). */
const BELGE_ADLARI: Record<string, string> = {
    sartlar: "Kullanım şartları",
    gizlilik: "Gizlilik politikası",
};

/** Adım: başlık, açıklama (durumdan), nasıl yapılır (yalnız eksikken). */
const ADIMLAR: {
    ad: keyof CanliyaGecis;
    baslik: string;
    aciklama: (d: CanliyaGecis) => string;
    nasil?: string;
    ac?: string;
}[] = [
    {
        ad: "odeme",
        baslik: "Ödeme: Stripe",
        aciklama: ({ odeme }) =>
            odeme.kip === "canli"
                ? "Canlı modda; gerçek ödeme alınıyor."
                : odeme.kip === "test"
                  ? "Test modunda. Canlı anahtarlar sunucuya girilince gerçek ödeme alınır."
                  : "Anahtar yok; ödeme sayfası açılmaz.",
        nasil: "Sunucunun ortam dosyasına STRIPE_SECRET_KEY ve STRIPE_PUBLISHABLE_KEY yazılır (canlı için sk_live_ / pk_live_ ile başlayanlar).",
    },
    {
        ad: "stripeBildirimleri",
        baslik: "Stripe bildirimleri",
        aciklama: ({ stripeBildirimleri: b }) =>
            b.son
                ? `Son bildirim ${uzunTarih(b.son)}. Fatura ve iadeler rapora bunlarla gelir.`
                : "Fatura ve iadeler rapora bunlarla gelir.",
        nasil: "Stripe panelinde Geliştiriciler → Webhook'lar'a https://<alan adı>/webhooks/stripe adresini ekleyin; verdiği imza gizlisini STRIPE_WEBHOOK_SECRET'a yazın. İlk olay gelince tamamlanır.",
    },
    {
        ad: "eposta",
        baslik: "E-posta gönderimi (Postmark)",
        aciklama: () =>
            "Giriş kodu ve bağlantıları bu olmadan müşteriye ulaşmaz.",
        nasil: "MAILER_DSN=postmark+api://<sunucu anahtarı>@default yazılır; MAIL_GONDEREN adresi Postmark'ta doğrulanmış olmalı.",
    },
    {
        ad: "yasal",
        baslik: "Yasal metinleri yayınla",
        aciklama: ({ yasal }) =>
            yasal.eksikler.length
                ? `Yayında değil: ${yasal.eksikler.map((b) => BELGE_ADLARI[b] ?? b).join(", ")}. Yayında değilse paywall'da bağlantı çıkmaz.`
                : "Şartlar ve gizlilik paywall'da bağlantılı.",
        ac: "/panel/yasal",
    },
    {
        ad: "metaReklam",
        baslik: "Meta reklam hesabı",
        aciklama: () => "Kampanya harcaması ve getirisi (ROAS) için.",
        nasil: "META_AD_ACCOUNT_ID (act_… biçiminde) ve ads_read izinli META_INSIGHTS_ACCESS_TOKEN yazılır.",
    },
    {
        ad: "gorevler",
        baslik: "Otomatik görevler",
        aciklama: ({ gorevler }) =>
            gorevler.eksikler.length
                ? `Çalışmayan: ${gorevler.eksikler.map((k) => GOREV_ADLARI[k] ?? k).join(", ")}.`
                : "Hatırlatma e-postaları, webhook'lar ve harcama çekme düzenli çalışıyor.",
        nasil: "Sunucuda her komut için bir cron satırı gerekir (deploy/sunucu-hazirla.sh). Komut başarıyla bitince burada görünür.",
    },
];

/** P7f: zamanlı komutların okunur adı (CanliyaGecis::GOREVLER); bilinmeyen komut adıyla. */
const GOREV_ADLARI: Record<string, string> = {
    "app:webhook:gonder": "webhook gönderimi",
    "app:posta:dizi": "hatırlatma e-postaları",
    "app:reklam-donusumleri:gonder": "reklam dönüşümleri",
    "app:revenuecat:gonder": "RevenueCat aktarımı",
    "app:reklam-harcamalari:cek": "Meta harcaması",
    "app:huni-metin-cevaplari-sil": "eski yanıtların silinmesi",
};

/** "1'i", "2'si", "6'sı" — belirtme hâli eki (0–9). */
function belirtme(n: number): string {
    const ek = ["ı", "i", "si", "ü", "ü", "i", "sı", "si", "i", "u"][n % 10];
    return `${n}'${ek}`;
}

export function Ayarlar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const [app, setApp] = useState<string | null>(null);
    const secili = app ?? uygulamalar.data?.[0] ?? null;

    return (
        <div className="max-w-[880px]">
            <SayfaBasi
                baslik="Uygulama"
                aciklama="Uygulamanın adı, iletişim bilgileri ve canlıya çıkmadan önce yapılacaklar."
            >
                {uygulamalar.data && uygulamalar.data.length > 1 ? (
                    <select
                        aria-label="Uygulama"
                        value={secili ?? ""}
                        onChange={(e) => setApp(e.currentTarget.value)}
                        className="min-h-9 rounded-lg border border-kenar-2 bg-yuzey px-2.5"
                    >
                        {uygulamalar.data.map((a) => (
                            <option key={a}>{a}</option>
                        ))}
                    </select>
                ) : null}
            </SayfaBasi>
            {secili ? (
                <div className="flex flex-col gap-7">
                    <CanliyaGecisBolumu key={`c-${secili}`} app={secili} />
                    <AyarFormu key={secili} app={secili} />
                    <VarsayilanKarti key={`v-${secili}`} app={secili} />
                </div>
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </div>
    );
}

function CanliyaGecisBolumu({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["canliya-gecis", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/canliya-gecis",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("durum alınamadı");
            return data;
        },
    });
    const d = sorgu.data;
    const tamam = d ? ADIMLAR.filter((a) => d[a.ad].tamam).length : 0;

    return (
        <Bolum
            baslik="Canlıya geçiş"
            sag={
                d ? (
                    <span className="text-[13px] text-soluk">
                        {ADIMLAR.length} adımdan {belirtme(tamam)} tamam
                    </span>
                ) : null
            }
        >
            {sorgu.isError ? (
                <p role="alert" className="text-tehlike">
                    Durum yüklenemedi. Sayfayı yenileyin.
                </p>
            ) : !d ? (
                <p role="status" className="text-soluk">
                    Yükleniyor…
                </p>
            ) : (
                <>
                    <div
                        role="progressbar"
                        aria-label="Canlıya geçiş"
                        aria-valuemin={0}
                        aria-valuemax={ADIMLAR.length}
                        aria-valuenow={tamam}
                        className="h-1.5 rounded-full bg-dolgu"
                    >
                        <div
                            className="h-1.5 rounded-full bg-iyi"
                            style={{
                                width: `${(tamam / ADIMLAR.length) * 100}%`,
                            }}
                        />
                    </div>
                    <Kart>
                        <ul>
                            {ADIMLAR.map((a) => (
                                <Adim
                                    key={a.ad}
                                    adim={a}
                                    durum={d}
                                    tamam={d[a.ad].tamam}
                                />
                            ))}
                        </ul>
                    </Kart>
                </>
            )}
        </Bolum>
    );
}

function Adim({
    adim,
    durum,
    tamam,
}: {
    adim: (typeof ADIMLAR)[number];
    durum: CanliyaGecis;
    tamam: boolean;
}): ReactNode {
    const [acik, setAcik] = useState(false);
    return (
        <li className="flex items-center gap-3.5 border-t border-kenar px-[18px] py-3.5 first:border-t-0">
            <svg
                role="img"
                aria-label={tamam ? "Tamam" : "Yapılmadı"}
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                className={cn(
                    "shrink-0 self-start",
                    tamam ? "text-iyi" : "text-soluk opacity-60",
                )}
            >
                <circle cx="12" cy="12" r="10" />
                {tamam ? <path d="M7.5 12.5l3 3 6-6.5" /> : null}
            </svg>
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-[15px]">{adim.baslik}</span>
                <span className="text-[13px] text-soluk">
                    {adim.aciklama(durum)}
                </span>
                {acik ? (
                    <p className="mt-1.5 text-[13px]">{adim.nasil}</p>
                ) : null}
            </div>
            {tamam ? null : adim.ac ? (
                <Link to={adim.ac} className="shrink-0 text-sm text-vurgu">
                    Aç
                </Link>
            ) : (
                <button
                    type="button"
                    aria-expanded={acik}
                    className="shrink-0 text-sm text-vurgu"
                    onClick={() => setAcik((x) => !x)}
                >
                    Nasıl yapılır
                </button>
            )}
        </li>
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
        <Bolum baslik="Uygulama bilgileri">
            <form
                className="flex flex-col gap-2.5"
                // Doğrulama sunucuda (422 mesajı satırda); tarayıcının kendi
                // url/email denetimi ayrı, farklı bir kural koymasın.
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    kayit.mutate(form);
                }}
            >
                <Kart>
                    {ALANLAR.map((a) => (
                        <Satir key={a.ad} etiket={a.etiket} yardim={a.aciklama}>
                            {(id) => {
                                const girdi = (
                                    <input
                                        id={id}
                                        type={a.tur}
                                        value={form[a.ad]}
                                        maxLength={a.azami}
                                        placeholder={a.ornek}
                                        // P7f: şema yazıldığı kadar geniş (field-sizing; desteklemeyen tarayıcıda size); ek (://giris) hemen
                                        // arkasında (sabit 140 px'te arada boşluk kalıyordu).
                                        size={
                                            a.ad === "sema"
                                                ? Math.max(
                                                      (form.sema || a.ornek)
                                                          .length,
                                                      4,
                                                  )
                                                : undefined
                                        }
                                        className={
                                            a.ad === "sema"
                                                ? "min-w-0 bg-transparent py-2 pl-2.5 text-[15px] text-metin outline-none [field-sizing:content]"
                                                : alanSinifi
                                        }
                                        onChange={(e) => {
                                            const deger = e.currentTarget.value;
                                            setForm((f) => ({
                                                ...f,
                                                [a.ad]: deger,
                                            }));
                                        }}
                                    />
                                );
                                // Şema giriş bağlantısının başı: şema://giris?jeton=…
                                return a.ad === "sema" ? (
                                    <div className="flex items-center overflow-hidden rounded-lg border border-kenar-2 bg-yuzey focus-within:outline-2 focus-within:-outline-offset-1 focus-within:outline-vurgu">
                                        {girdi}
                                        <span className="pr-2.5 text-[15px] text-soluk">
                                            ://giris
                                        </span>
                                    </div>
                                ) : (
                                    girdi
                                );
                            }}
                        </Satir>
                    ))}
                </Kart>
                <div className="flex flex-wrap items-center justify-end gap-3">
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
        </Bolum>
    );
}

/** M9c (A23): yeni hunilere kopyalanan görünüm — kaynak, tarih, içerik; kaldırılabilir. */
function VarsayilanKarti({ app }: { app: string }): ReactNode {
    const istemci = useQueryClient();
    const sorgu = useQuery({
        queryKey: ["varsayilanlar", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/varsayilanlar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("varsayılan alınamadı");
            return data;
        },
    });
    const kaldir = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/varsayilanlar",
                { params: { path: { app }, header: { "X-CSRF-Token": "" } } },
            );
            if (!data)
                throw new Error(
                    (error?.hatalar ?? ["Kaldırılmadı."]).join(" "),
                );
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["varsayilanlar", app], data),
    });
    const v = sorgu.data;
    const icerik = v
        ? [v.tema && "tema", v.logo && "logo", v.paywall && "paywall"].filter(
              (x): x is string => Boolean(x),
          )
        : [];
    const liste =
        icerik.length > 1
            ? `${icerik.slice(0, -1).join(", ")} ve ${icerik.at(-1)}`
            : (icerik[0] ?? "");

    return (
        <Bolum baslik="Yeni hunilerin görünümü">
            <Kart className="flex flex-wrap items-center justify-between gap-3 px-[18px] py-4">
                {!v ? (
                    <p role="status" className="text-soluk">
                        Yükleniyor…
                    </p>
                ) : v.kaynak ? (
                    <>
                        <div className="flex flex-col gap-0.5">
                            <span className="text-[15px]">
                                "{v.kaynak}" hunisinden
                                {v.alindi ? ` · ${uzunTarih(v.alindi)}` : ""}
                            </span>
                            <span className="text-[13px] text-soluk">
                                {liste.charAt(0).toLocaleUpperCase("tr")}
                                {liste.slice(1)} yeni açılan hunilere
                                kopyalanır. Var olan huniler değişmez.
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            {kaldir.isError ? (
                                <span role="alert" className="text-tehlike">
                                    {kaldir.error.message}
                                </span>
                            ) : null}
                            <Dugme
                                tur="tehlike"
                                disabled={kaldir.isPending}
                                onClick={() => kaldir.mutate()}
                            >
                                Kaldır
                            </Dugme>
                        </div>
                    </>
                ) : (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[15px]">
                            Yok — yeni huniler boş başlar.
                        </span>
                        <span className="text-[13px] text-soluk">
                            Huniler sayfasında bir huninin yanındaki "Yeni
                            hunilere örnek yap" ile seçin.
                        </span>
                    </div>
                )}
            </Kart>
        </Bolum>
    );
}
