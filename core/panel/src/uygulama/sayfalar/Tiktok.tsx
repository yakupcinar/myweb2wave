// TikTok bağlantısı (A31, TB2): Entegrasyonlar'ın bölümü — Meta'nın (A30)
// karşılığı. Dönüşüm (Pixel kodu + Events API anahtarı, isteğe bağlı test olay
// kodu) ve harcama (reklamveren kimliği + Marketing API anahtarı) uygulama
// başına. Anahtarlar sunucuda şifreli; burada yalnız son 4 hanesi görünür —
// boş bırakılan anahtar alanı kayıtlıyı değiştirmez, "Kaldır" siler.
// "Bağlantıyı dene" kayıtlı değerlerle TikTok'a gerçek istek atar; dönüşüm
// yalnız test olay koduyla denenir. Veri /panel/api/uygulamalar/{app}/tiktok
// (sözleşme PanelTiktok).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { alanSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";

type PanelTiktok = components["schemas"]["PanelTiktok"];
type Deneme = components["schemas"]["MetaDenemeSonucu"];
type AnahtarAdi = "eventsAnahtari" | "raporAnahtari";

/** Düz alanlar ve yeni anahtar metni; "sil" anahtarı kaldırır. */
type Form = {
    pixelKodu: string;
    testKodu: string;
    reklamveren: string;
    eventsAnahtari: string;
    raporAnahtari: string;
    sil: Record<AnahtarAdi, boolean>;
};

function hata(error: { hatalar?: string[] } | undefined): Error {
    return new Error((error?.hatalar ?? ["Kaydedilmedi."]).join(" "));
}

export function TiktokBolumu({ app }: { app: string }): ReactNode {
    return (
        <Bolum
            id="tiktok"
            baslik="TikTok reklamları"
            aciklama="Satın almaları TikTok'a bildirir ve kampanya harcamasını ROAS için çeker. Anahtarlar şifreli saklanır; burada yalnız son 4 hanesi görünür."
        >
            <TiktokFormu app={app} />
        </Bolum>
    );
}

function TiktokFormu({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["tiktok", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/tiktok",
                {
                    params: { path: { app } },
                },
            );
            if (!data) throw new Error("TikTok bağlantısı alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                TikTok bağlantısı yüklenemedi. Sayfayı yenileyin.
            </p>
        );
    // Form veri gelince çizilir (Baglam ile aynı sebep).
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    return (
        <TiktokGovdesi
            key={sorgu.data.guncellendi ?? "yok"}
            app={app}
            kayitli={sorgu.data}
        />
    );
}

function TiktokGovdesi({
    app,
    kayitli,
}: {
    app: string;
    kayitli: PanelTiktok;
}): ReactNode {
    const istemci = useQueryClient();
    const bos: Form = {
        pixelKodu: kayitli.pixelKodu,
        testKodu: kayitli.testKodu,
        reklamveren: kayitli.reklamveren,
        eventsAnahtari: "",
        raporAnahtari: "",
        sil: { eventsAnahtari: false, raporAnahtari: false },
    };
    const [form, setForm] = useState<Form>(bos);
    const [kaldirOnay, setKaldirOnay] = useState(false);
    const yol = {
        params: { path: { app }, header: { "X-CSRF-Token": "" } },
    };
    const kaydet = (data: PanelTiktok) =>
        istemci.setQueryData(["tiktok", app], data);
    const anahtarGovdesi = (ad: AnahtarAdi): string | null =>
        form.sil[ad] ? "" : form[ad].trim() || null;
    const kayit = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/tiktok",
                {
                    ...yol,
                    body: {
                        pixelKodu: form.pixelKodu,
                        testKodu: form.testKodu,
                        reklamveren: form.reklamveren,
                        eventsAnahtari: anahtarGovdesi("eventsAnahtari"),
                        raporAnahtari: anahtarGovdesi("raporAnahtari"),
                    },
                },
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: kaydet,
    });
    const kaldir = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/tiktok",
                yol,
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: kaydet,
    });
    const dene = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.POST(
                "/panel/api/uygulamalar/{app}/tiktok/dene",
                yol,
            );
            if (!data) throw hata(error);
            return data;
        },
    });
    const degisti = JSON.stringify(form) !== JSON.stringify(bos);
    const bagli =
        kayitli.pixelKodu !== "" ||
        kayitli.reklamveren !== "" ||
        kayitli.eventsAnahtari.durum !== "yok" ||
        kayitli.raporAnahtari.durum !== "yok";
    const mesgul = kayit.isPending || kaldir.isPending;
    const hataMesaji = kayit.error?.message ?? kaldir.error?.message;
    const duz = (ad: "pixelKodu" | "testKodu" | "reklamveren") =>
        function DuzAlan(id: string): ReactNode {
            return (
                <input
                    id={id}
                    value={form[ad]}
                    placeholder={YER[ad]}
                    autoComplete="off"
                    className={alanSinifi}
                    onChange={(e) => {
                        const deger = e.currentTarget.value;
                        setForm((f) => ({ ...f, [ad]: deger }));
                    }}
                />
            );
        };
    const anahtar = (ad: AnahtarAdi) =>
        function AnahtarAlani(id: string): ReactNode {
            const k = kayitli[ad];
            return (
                <span className="flex flex-wrap items-center gap-2">
                    <input
                        id={id}
                        type="password"
                        value={form[ad]}
                        disabled={form.sil[ad]}
                        placeholder={
                            k.durum === "kayitli"
                                ? `Kayıtlı (…${k.son4 ?? ""}) — değiştirmek için yenisini yapıştır`
                                : "Erişim anahtarı"
                        }
                        autoComplete="new-password"
                        className={`${alanSinifi} min-w-0 flex-1`}
                        onChange={(e) => {
                            const deger = e.currentTarget.value;
                            setForm((f) => ({ ...f, [ad]: deger }));
                        }}
                    />
                    {k.durum === "okunamadi" ? (
                        <Rozet tur="tehlike">Okunamadı — yeniden girin</Rozet>
                    ) : null}
                    {k.durum !== "yok" ? (
                        <Dugme
                            tur={form.sil[ad] ? "sade" : "tehlike"}
                            aria-pressed={form.sil[ad]}
                            onClick={() =>
                                setForm((f) => ({
                                    ...f,
                                    [ad]: "",
                                    sil: { ...f.sil, [ad]: !f.sil[ad] },
                                }))
                            }
                        >
                            {form.sil[ad] ? "Silinecek — geri al" : "Kaldır"}
                        </Dugme>
                    ) : null}
                </span>
            );
        };

    return (
        <form
            className="flex flex-col gap-2.5"
            onSubmit={(e) => {
                e.preventDefault();
                kayit.mutate();
            }}
        >
            {!kayitli.sifrelemeHazir ? (
                <p
                    role="note"
                    className="rounded-[14px] bg-uyari-zemin px-[18px] py-3.5 text-[14px]"
                >
                    Sunucuda şifreleme anahtarı yok (AYAR_SIFRELEME_ANAHTARI);
                    erişim anahtarları kaydedilemez.
                </p>
            ) : null}
            <Kart aria-label="Dönüşüm">
                <h3 className="px-[18px] pt-3.5 text-[15px] font-semibold">
                    Dönüşüm (Pixel + Events API)
                </h3>
                <Satir
                    etiket="Pixel kodu"
                    yardim="Ads Manager › Araçlar › Events › Web olayları › Pixel'in kodu (büyük harf ve rakam)."
                >
                    {duz("pixelKodu")}
                </Satir>
                <Satir
                    etiket="Events API anahtarı"
                    yardim="Events › Pixel › Ayarlar › Events API › Erişim anahtarı oluştur. Anahtar o Pixel'e özeldir."
                >
                    {anahtar("eventsAnahtari")}
                </Satir>
                <Satir
                    etiket="Test olay kodu (isteğe bağlı)"
                    yardim="Events › Pixel › Test events'teki kod. Doluyken olaylar yalnız test görünümüne düşer ve 'Bağlantıyı dene' bununla bir deneme olayı yollar; canlıya geçmeden boşalt."
                >
                    {duz("testKodu")}
                </Satir>
            </Kart>
            <Kart aria-label="Harcama">
                <h3 className="px-[18px] pt-3.5 text-[15px] font-semibold">
                    Harcama (ROAS için)
                </h3>
                <Satir
                    etiket="Reklamveren kimliği"
                    yardim="Ads Manager'da hesap adının altındaki ID (yalnız rakam)."
                >
                    {duz("reklamveren")}
                </Satir>
                <Satir
                    etiket="Marketing API anahtarı"
                    yardim="TikTok for Business geliştirici uygulamasının (rapor okuma izni) uzun ömürlü erişim anahtarı."
                >
                    {anahtar("raporAnahtari")}
                </Satir>
            </Kart>
            {dene.data ? (
                <Kart aria-label="Deneme sonucu" className="px-[18px] py-3.5">
                    <ul className="flex flex-col gap-1.5 text-[14px]">
                        <DenemeSatiri ad="Dönüşüm" s={dene.data.donusum} />
                        <DenemeSatiri ad="Harcama" s={dene.data.harcama} />
                    </ul>
                </Kart>
            ) : null}
            <div className="flex flex-wrap items-center justify-end gap-3">
                {(hataMesaji ?? dene.error?.message) ? (
                    <span role="alert" className="text-tehlike">
                        {hataMesaji ?? dene.error?.message}
                    </span>
                ) : null}
                {bagli && !kaldirOnay ? (
                    <Dugme
                        tur="tehlike"
                        disabled={mesgul}
                        onClick={() => setKaldirOnay(true)}
                    >
                        Bağlantıyı kaldır
                    </Dugme>
                ) : null}
                {kaldirOnay ? (
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span className="text-[14px]">
                            Dönüşüm ve harcama durur. Emin misin?
                        </span>
                        <Dugme tur="sade" onClick={() => setKaldirOnay(false)}>
                            Vazgeç
                        </Dugme>
                        <Dugme
                            tur="yikici"
                            disabled={mesgul}
                            onClick={() => kaldir.mutate()}
                        >
                            Kaldır
                        </Dugme>
                    </span>
                ) : null}
                <Dugme
                    disabled={!bagli || degisti || dene.isPending}
                    title={degisti ? "Önce kaydet" : undefined}
                    onClick={() => dene.mutate()}
                >
                    {dene.isPending ? "Deneniyor…" : "Bağlantıyı dene"}
                </Dugme>
                <Dugme
                    tur="ana"
                    type="submit"
                    className="min-h-11 px-5.5 text-[15px]"
                    disabled={!degisti || mesgul}
                >
                    {kayit.isPending ? "Kaydediliyor…" : "Bağlantıyı kaydet"}
                </Dugme>
            </div>
        </form>
    );
}

const YER = {
    pixelKodu: "CABCDEFGHIJ1234567890",
    testKodu: "TEST12345",
    reklamveren: "7123456789012345678",
} as const;

function DenemeSatiri({ ad, s }: { ad: string; s: Deneme }): ReactNode {
    return (
        <li className="flex flex-wrap items-center gap-2">
            <span className="w-24 font-medium">{ad}</span>
            <Rozet
                tur={
                    s.durum === "tamam"
                        ? "iyi"
                        : s.durum === "hata"
                          ? "tehlike"
                          : "notr"
                }
            >
                {s.durum === "tamam"
                    ? "Bağlı"
                    : s.durum === "hata"
                      ? "Hata"
                      : s.durum === "kayitli"
                        ? "Kayıtlı"
                        : "Ayarlı değil"}
            </Rozet>
            <span className="min-w-0 break-words text-soluk">{s.mesaj}</span>
        </li>
    );
}
