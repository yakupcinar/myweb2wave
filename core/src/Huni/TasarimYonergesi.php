<?php

declare(strict_types=1);

namespace App\Huni;

/**
 * V1: AI'ın huni TASARIMI yönergesi (panel asistanı + MCP). Rakip
 * araştırması (rakip-gorsellik-arastirmasi.md): iyi hunilerin aşama düzeni
 * ve görsel denetim listesi; FunnelFox eklentisi tescilli — metin kendi
 * cümlemizle. Yapı kuralları Tanim::EKRAN_YONERGESI'nde, tema Tema'da.
 */
final class TasarimYonergesi
{
    public const METIN = <<<'METIN'
        Tasarım (iyi hunilerin ortak düzeni):
        - Akış: kolay ilk dokunuş (yaş, hedef gibi tek seçim; kayıt yok) → hedef sorusu ilk üç ekranda → ürünün gerçekten kullandığı profil soruları → soru gruplarının arasında kanıt/değer ekranı (bilgi kutusu, istatistik, yorum) → cevaplardan kurulan kişisel özet (önünde yükleme ekranı) → e-posta (ne alacağıyla çerçevele: "planını nereye gönderelim?") → paywall. Her soru bir sonraki ekranda ya da özette karşılık bulmalı; kullanılmayacak soru sorma.
        - Kişiselleştir: özet ve paywall öncesi ekranlar ziyaretçinin seçtiğini geri söylesin (koşullu değerlerle başlık/metin).
        - Ekran başına tek odak: kısa büyük başlık, bir-iki satır destek, tek birincil düğme. Başlık en çok ~6 kelime; uzun açıklama metin bloğuna.
        - Ekranı doldur: düğme telefonda alta yapışır; içerik üstte toplanıp ortası boş kalmasın — bilgi ekranına istatistik, kutu, madde ya da görsel ekle. Soru ekranında seçenek görsel/simgeliyse ızgara, uzun metinliyse liste.
        - Satır sonu: başlık ya da paragrafın son satırında tek kısa kelime kalmasın; kelimeyi değiştir ya da cümleyi kısalt.
        - Simgeler: aynı soruda ya hepsinde simge ya hiçbirinde; tek emoji, anlamı metinde.
        - Dürüst içerik: verilmeyen sayı, oran, kullanıcı sayısı, rozet ya da yorum ekleme (paywall'ın guvenceler kataloğu hariç); sayaç ya da "sınırlı teklif" metni yazma — teklifin gerçek bitişi varsa paywall sayacı kendisi gösterir.
        - Görsel: stok_gorsel_ara/stok_gorsel_al varsa ilk ekrana kapak, sonuç/özet ekranına atmosfer görseli koy (görselsiz huni şablon gibi durur); her görsele gorselMetni yaz. Stok fotoğraftaki kişiyi müşteri, yorum sahibi ya da önce/sonra örneği gibi gösterme (lisans: onay ima edilemez).

        Görsel döngü (ekran_goruntusu varsa ZORUNLU):
        - Kaydettikten sonra değiştirdiğin her ekranın görüntüsünü al (yeni huni kurduysan en az: ilk ekran, bir soru ekranı, özet, paywall). Temayı değiştirdiysen bütün ekranlara yeniden bak: yazı tipi ve boşluk satır kırılmasını ve yüksekliği değiştirir (aynı içerik bir temada uyarısız, ötekinde uyarılı çıkar).
        - Dönen "uyarilar" listesini düzelt (boşluk, yetim satır, kaydırmada kalan düğme, taşan metin), yeniden kaydet ve yeniden bak; uyarısız olunca bitir. Düzeltemediğin uyarıyı (ör. görsel yok) yöneticiye söyle.
        - Görüntüye bakmadığın ekranın nasıl göründüğünü anlatma.
        METIN;
}
