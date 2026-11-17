<?php

declare(strict_types=1);

namespace App\Panel;

use Doctrine\DBAL\Connection;

/**
 * Ç151: panelden abonelik işlemlerinin denetim izi (panel_islemleri, yalnız
 * ekleme). Başarılı ve reddedilen her deneme yazılır; ayrıntı yalnız
 * reddedilende (sunucunun hata metni). E-posta yazılmaz.
 */
final readonly class PanelIslemleri
{
    public const ISLEMLER = ['iptal', 'iptal_geri_al', 'duraklat', 'surdur', 'iade', 'eposta_duzelt'];

    public function __construct(private Connection $db)
    {
    }

    public function yaz(string $kullanici, string $abonelik, string $islem, ?string $hata): void
    {
        \assert(\in_array($islem, self::ISLEMLER, true), "bilinmeyen işlem: {$islem}");
        $this->db->insert('panel_islemleri', [
            'kullanici' => $kullanici,
            'abonelik' => $abonelik,
            'islem' => $islem,
            'basarili' => null === $hata,
            'ayrinti' => $hata,
        ], ['basarili' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
    }

    /**
     * Son 50, yeniden eskiye.
     *
     * @return list<array{zaman: string, kullanici: string, islem: string, basarili: bool, ayrinti: ?string}>
     */
    public function abonelik(string $abonelik): array
    {
        /** @var list<array{zaman: string, kullanici: string, islem: string, basarili: bool, ayrinti: ?string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT to_char(zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS zaman, kullanici, islem, basarili, ayrinti
             FROM panel_islemleri WHERE abonelik = :a ORDER BY id DESC LIMIT 50",
            ['a' => $abonelik],
        );

        return $satirlar;
    }
}
