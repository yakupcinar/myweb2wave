// Meta bağlantısı (A30, MB2): Entegrasyonlar'ın bölümü. Dönüşüm (Pixel +
// Conversions API anahtarı, isteğe bağlı test olay kodu) ve harcama (reklam
// hesabı + Insights anahtarı) uygulama başına. Anahtarlar sunucuda şifreli;
// burada yalnız son 4 hanesi görünür — boş bırakılan anahtar alanı kayıtlıyı
// değiştirmez, "Kaldır" siler. "Bağlantıyı dene" kayıtlı değerlerle Meta'ya
// gerçek istek atar. Veri /panel/api/uygulamalar/{app}/meta (sözleşme PanelMeta).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { alanSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";

type PanelMeta = components["schemas"]["PanelMeta"];
type Deneme = components["schemas"]["MetaDenemeSonucu"];
type AnahtarAdi = "capiAnahtari" | "insightsAnahtari";

/** Düz alanlar ve yeni anahtar metni; "sil" anahtarı kaldırır. */
type Form = {
    pixelId: string;
    testKodu: string;
    reklamHesabi: string;
    capiAnahtari: string;
    insightsAnahtari: string;
    sil: Record<AnahtarAdi, boolean>;
};

function hata(error: { hatalar?: string[] } | undefined): Error {
    return new Error((error?.hatalar ?? ["Kaydedilmedi."]).join(" "));
}

export function MetaBolumu({ app }: { app: string }): ReactNode {
    return (
        <Bolum
            id="meta"
            baslik="Meta (Facebook ve Instagram reklamları)"
            aciklama="Satın almaları Meta'ya bildirir ve kampanya harcamasını ROAS için çeker. Anahtarlar şifreli saklanır; burada yalnız son 4 hanesi görünür."
        >
            <MetaFormu app={app} />
        </Bolum>
    );
}

function MetaFormu({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["meta", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/meta",
                {
                    params: { path: { app } },
                },
            );
            if (!data) throw new Error("Meta bağlantısı alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Meta bağlantısı yüklenemedi. Sayfayı yenileyin.
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
        <MetaGovdesi
            key={sorgu.data.guncellendi ?? "yok"}
            app={app}
            kayitli={sorgu.data}
        />
    );
}

function MetaGovdesi({
    app,
    kayitli,
}: {
    app: string;
    kayitli: PanelMeta;
}): ReactNode {
    const istemci = useQueryClient();
    const bos: Form = {
        pixelId: kayitli.pixelId,
        testKodu: kayitli.testKodu,
        reklamHesabi: kayitli.reklamHesabi,
        capiAnahtari: "",
        insightsAnahtari: "",
        sil: { capiAnahtari: false, insightsAnahtari: false },
    };
    const [form, setForm] = useState<Form>(bos);
    const [kaldirOnay, setKaldirOnay] = useState(false);
    const yol = {
        params: { path: { app }, header: { "X-CSRF-Token": "" } },
    };
    const kaydet = (data: PanelMeta) =>
        istemci.setQueryData(["meta", app], data);
    const anahtarGovdesi = (ad: AnahtarAdi): string | null =>
        form.sil[ad] ? "" : form[ad].trim() || null;
    const kayit = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/meta",
                {
                    ...yol,
                    body: {
                        pixelId: form.pixelId,
                        testKodu: form.testKodu,
                        reklamHesabi: form.reklamHesabi,
                        capiAnahtari: anahtarGovdesi("capiAnahtari"),
                        insightsAnahtari: anahtarGovdesi("insightsAnahtari"),
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
                "/panel/api/uygulamalar/{app}/meta",
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
                "/panel/api/uygulamalar/{app}/meta/dene",
                yol,
            );
            if (!data) throw hata(error);
            return data;
        },
    });
    const degisti = JSON.stringify(form) !== JSON.stringify(bos);
    const bagli =
        kayitli.pixelId !== "" ||
        kayitli.reklamHesabi !== "" ||
        kayitli.capiAnahtari.durum !== "yok" ||
        kayitli.insightsAnahtari.durum !== "yok";
    const mesgul = kayit.isPending || kaldir.isPending;
    const hataMesaji = kayit.error?.message ?? kaldir.error?.message;
    const duz = (ad: "pixelId" | "testKodu" | "reklamHesabi") =>
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
    const anahtar = (ad: AnahtarAdi, ornek: string) =>
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
                                : ornek
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
            {kayitli.surum === null ? (
                <p
                    role="note"
                    className="rounded-[14px] bg-uyari-zemin px-[18px] py-3.5 text-[14px]"
                >
                    Sunucuda Graph API sürümü yok (META_GRAPH_API_VERSION);
                    Meta'ya istek gitmez.
                </p>
            ) : null}
            <Kart aria-label="Dönüşüm">
                <h3 className="px-[18px] pt-3.5 text-[15px] font-semibold">
                    Dönüşüm (Pixel + Conversions API)
                </h3>
                <Satir
                    etiket="Pixel ID"
                    yardim="Events Manager › Veri kaynakları › Pixel'in kimliği (yalnız rakam)."
                >
                    {duz("pixelId")}
                </Satir>
                <Satir
                    etiket="Conversions API anahtarı"
                    yardim="Events Manager › Pixel › Ayarlar › Conversions API › Erişim anahtarı oluştur."
                >
                    {anahtar("capiAnahtari", "EAAG…")}
                </Satir>
                <Satir
                    etiket="Test olay kodu (isteğe bağlı)"
                    yardim="Events Manager › Olayları test et'teki kod. Doluyken olaylar yalnız test görünümüne düşer; canlıya geçmeden boşalt."
                >
                    {duz("testKodu")}
                </Satir>
            </Kart>
            <Kart aria-label="Harcama">
                <h3 className="px-[18px] pt-3.5 text-[15px] font-semibold">
                    Harcama (ROAS için)
                </h3>
                <Satir
                    etiket="Reklam hesabı"
                    yardim="Reklam Yöneticisi'nin adresindeki act= değeri, act_ ile."
                >
                    {duz("reklamHesabi")}
                </Satir>
                <Satir
                    etiket="Insights anahtarı"
                    yardim="İşletme Ayarları › Sistem kullanıcıları › ads_read izinli anahtar."
                >
                    {anahtar("insightsAnahtari", "EAAG…")}
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
    pixelId: "1234567890123456",
    testKodu: "TEST12345",
    reklamHesabi: "act_1234567890",
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
                      : "Ayarlı değil"}
            </Rozet>
            <span className="min-w-0 break-words text-soluk">{s.mesaj}</span>
        </li>
    );
}
