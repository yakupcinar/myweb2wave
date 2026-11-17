<?php

declare(strict_types=1);

namespace App\Huni\Ekran;

/**
 * V1: ekran görüntüsü + aynı anda sayfadan ölçülen yerleşim. Model görüntüde
 * "altı boş" ya da "tek kelime kalmış" gibi kusuru her zaman fark etmiyor;
 * ölçüm ona sayı olarak gider (rakip araştırması: görsel denetim listesi).
 */
final readonly class EkranCekimi
{
    /**
     * @param string                                                                                                                        $gorsel JPEG baytları
     * @param array{bosAlt: float, kaydirma: bool, dugmeGorunur: bool|null, yetimSatirlar: list<string>, kucukHedefler: int, tasanlar: int} $olcum
     */
    public function __construct(
        public string $gorsel,
        public array $olcum,
    ) {
    }

    /**
     * Ölçümden modelin okuyacağı Türkçe uyarılar; eşikler ekran-olcumu
     * kaydında gerekçeli (V1).
     *
     * @return list<string>
     */
    public function uyarilar(): array
    {
        $o = $this->olcum;
        $u = [];
        if (!$o['kaydirma'] && $o['bosAlt'] > self::BOS_ALT_SINIRI) {
            $u[] = \sprintf('içerikle düğme (ya da ekranın altı) arasında ekranın %%%d kadar boşluk: içerik üstte toplanmış — blok ekle (görsel, istatistik, kutu, yorum) ya da var olanı zenginleştir', (int) round($o['bosAlt'] * 100));
        }
        if (false === $o['dugmeGorunur']) {
            $u[] = 'birincil düğme ilk ekranda görünmüyor (kaydırmak gerekiyor): blokları azalt ya da metni kısalt';
        }
        foreach ($o['yetimSatirlar'] as $metin) {
            $u[] = "son satırda tek kısa kelime kalmış: \"{$metin}\" — metni yeniden yaz";
        }
        if ($o['kucukHedefler'] > 0) {
            $u[] = "{$o['kucukHedefler']} dokunma hedefi 44 pikselden alçak";
        }
        if ($o['tasanlar'] > 0) {
            $u[] = "{$o['tasanlar']} öğenin metni yatayda taşıyor (bölünmeyen uzun kelime?)";
        }

        return $u;
    }

    public const BOS_ALT_SINIRI = 0.35;
}
