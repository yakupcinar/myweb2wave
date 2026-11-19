// Ç152: panel yazma denetimi — panelden yapılan son 200 değişiklik (kim, ne,
// nerede, sonuç), yeniden eskiye. Yalnız okuma; kayıt değiştirilemez. Gövde
// tutulmadığı için ne yazıldığı değil, neyin nereye yapıldığı görünür.
import { useQuery } from "@tanstack/react-query";
import type { ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { zaman } from "../bicim";
import { SayfaBasi } from "../Duzen";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";
import {
    KartEtiketi,
    kartHucre,
    kartSatir,
    kartTablo,
    kartTam,
    Tablo,
    Td,
    Th,
} from "../ui/Tablo";

type Kayit = components["schemas"]["PanelDenetimKaydi"];

/** Rota adı → okunur işlem; listede olmayan (yeni uç) rota adıyla görünür. */
export const ISLEM_ADLARI: Record<string, string> = {
    panel_giris: "Panele giriş",
    panel_api_abonelik_iptal: "Abonelik iptali / geri alma",
    panel_api_abonelik_duraklat: "Abonelik duraklatma / sürdürme",
    panel_api_abonelik_eposta: "Abonelik e-postası düzeltme",
    panel_api_abonelik_iade: "Son fatura iadesi",
    panel_api_ai_sohbet: "AI asistanına mesaj",
    panel_api_anahtar_olustur: "API anahtarı oluşturma",
    panel_api_anahtar_iptal: "API anahtarı iptali",
    panel_api_anahtar_sil: "İptal edilmiş API anahtarını silme",
    panel_api_ayarlar_kaydet: "Uygulama ayarları",
    panel_api_baglam_kaydet: "Ürün bağlamı (AI)",
    panel_api_deney_kaydet: "Deney kaydetme",
    panel_api_deney_durum: "Deney başlatma / durdurma",
    panel_api_ek_urun_olustur: "Ek ürün oluşturma",
    panel_api_eposta_kaydet: "E-posta şablonu kaydetme",
    panel_api_eposta_sil: "E-posta şablonunu varsayılana döndürme",
    panel_api_huni_yeni: "Yeni huni",
    panel_api_huni_kaldir: "Huni kaldırma",
    panel_api_huni_ornek: "Örnek huni",
    panel_api_huni_varsayilan: "Varsayılan huni",
    panel_api_kural_ekle: "Yönlendirme kuralı ekleme",
    panel_api_kural_sil: "Yönlendirme kuralı silme",
    panel_api_kural_tasi: "Yönlendirme kuralı sırası",
    panel_api_meta_kaydet: "Meta bağlantısı kaydetme",
    panel_api_meta_sil: "Meta bağlantısı kaldırma",
    panel_api_meta_dene: "Meta bağlantısını deneme",
    panel_api_plan_olustur: "Plan oluşturma",
    panel_api_plan_fiyat: "Plan fiyatı değiştirme",
    panel_api_plan_para_birimleri: "Plan para birimleri",
    panel_api_plan_teklif: "Plan teklifi",
    panel_api_tiktok_kaydet: "TikTok bağlantısı kaydetme",
    panel_api_tiktok_sil: "TikTok bağlantısı kaldırma",
    panel_api_tiktok_dene: "TikTok bağlantısını deneme",
    panel_api_varsayilanlar_kaldir: "Varsayılanları kaldırma",
    panel_api_webhook_ekle: "Webhook hedefi ekleme",
    panel_api_webhook_durum: "Webhook etkin / pasif",
    panel_api_webhook_sil: "Webhook hedefi silme",
    panel_api_webhook_yeniden: "Webhook teslimini yeniden gönderme",
    panel_api_yasal_veriler: "Yasal metin verileri",
    panel_api_yasal_metin: "Yasal metin kaydetme",
    panel_huni_durum_geri_yukle: "Huni sürümünü taslağa geri yükleme",
    panel_huni_durum_yayinla: "Huni yayınlama",
    panel_huni_taslak: "Huni taslağı kaydetme",
    panel_medya_yukle: "Görsel yükleme",
    panel_medya_stok_al: "Stok fotoğraf alma",
};

export function IslemGecmisi(): ReactNode {
    const sorgu = useQuery({
        queryKey: ["denetim"],
        queryFn: async () => {
            const { data } = await api.GET("/panel/api/denetim");
            if (!data) throw new Error("İşlem geçmişi yüklenemedi.");
            return data.kayitlar;
        },
    });
    const kayitlar = sorgu.data ?? [];

    return (
        <div className="max-w-[1100px]">
            <SayfaBasi
                baslik="İşlem geçmişi"
                aciklama="Panelden yapılan son 200 değişiklik, yeniden eskiye — kim, ne, nerede, sonuç. Reddedilen denemeler de görünür; kayıt değiştirilemez. Yazılan içerik (anahtar, metin) tutulmaz."
            />
            <Kart aria-label="İşlemler">
                {sorgu.isError ? (
                    <p role="alert" className="p-4 text-tehlike">
                        İşlem geçmişi yüklenemedi.
                    </p>
                ) : sorgu.isPending ? (
                    <p className="p-4 text-soluk">Yükleniyor…</p>
                ) : kayitlar.length === 0 ? (
                    <p className="p-4 text-soluk">Henüz kayıt yok.</p>
                ) : (
                    // Ç137: telefonda satırlar karta döner.
                    <Tablo className={kartTablo}>
                        <thead className="max-md:hidden">
                            <tr>
                                {[
                                    "Zaman (UTC)",
                                    "Kullanıcı",
                                    "İşlem",
                                    "Kaynak",
                                    "Sonuç",
                                ].map((b) => (
                                    <Th key={b}>{b}</Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="max-md:block">
                            {kayitlar.map((k, i) => (
                                <KayitSatiri key={i} k={k} />
                            ))}
                        </tbody>
                    </Tablo>
                )}
            </Kart>
        </div>
    );
}

function KayitSatiri({ k }: { k: Kayit }): ReactNode {
    const yapildi = k.durum >= 200 && k.durum < 300;
    const kaynak = Object.entries(k.kaynak)
        .map(([ad, deger]) => `${ad}: ${deger}`)
        .join(" · ");
    return (
        <tr className={kartSatir}>
            <Td className={`${kartHucre} font-mono text-[13px]`}>
                {zaman(k.zaman)}
            </Td>
            <Td className={kartHucre}>
                <KartEtiketi>Kullanıcı</KartEtiketi>
                {k.kullanici}
            </Td>
            <Td className={`${kartHucre} ${kartTam}`}>
                {ISLEM_ADLARI[k.rota] ?? k.rota}
            </Td>
            <Td
                className={`${kartHucre} ${kartTam} font-mono text-[13px] break-all whitespace-normal text-soluk`}
            >
                {kaynak || "—"}
            </Td>
            <Td className={kartHucre}>
                {yapildi ? (
                    <Rozet tur="iyi">Yapıldı</Rozet>
                ) : (
                    <Rozet tur="tehlike">Reddedildi · {k.durum}</Rozet>
                )}
            </Td>
        </tr>
    );
}
