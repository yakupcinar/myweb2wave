<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T1c (Ç86, kullanıcı kararı): tanıtım, soru ve yorum adımları ekrana
 * taşınır — taslaklar VE yayın sürümleri (geliştirme aşaması; sürümlerin
 * değişmezliği bu kez bilerek bozulur). Yükleme ve ödeme yerinde kalır.
 * Dönüştürücü göçün içinde: sonradan Tanim değişse de göç aynı kalır.
 */
final class Version20261102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T1c: tanıtım/soru/yorum adımları ekran adımına taşınır';
    }

    public function up(Schema $schema): void
    {
        foreach ([['huniler', 'taslak'], ['huni_surumleri', 'tanim']] as [$tablo, $sutun]) {
            $satirlar = $this->connection->fetchAllAssociative("SELECT ctid::text AS yer, {$sutun} AS tanim FROM {$tablo}");
            foreach ($satirlar as $s) {
                $tanim = json_decode((string) $s['tanim'], true, 512, \JSON_THROW_ON_ERROR);
                if (!\is_array($tanim)) {
                    continue;
                }
                $yeni = self::ekrana($tanim);
                if ($yeni !== $tanim) {
                    $this->addSql(
                        "UPDATE {$tablo} SET {$sutun} = :tanim::jsonb WHERE ctid = :yer::tid",
                        ['tanim' => json_encode($yeni, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), 'yer' => $s['yer']],
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Eski adım türleri geri üretilmez (Ç86).');
    }

    /**
     * @param array<mixed> $tanim
     *
     * @return array<mixed>
     */
    public static function ekrana(array $tanim): array
    {
        if (\is_array($tanim['adimlar'] ?? null)) {
            foreach ($tanim['adimlar'] as $i => $adim) {
                if (\is_array($adim)) {
                    $tanim['adimlar'][$i] = self::adim($adim);
                }
            }
        }

        return $tanim;
    }

    /**
     * @param array<mixed> $a
     *
     * @return array<mixed>
     */
    private static function adim(array $a): array
    {
        $al = static fn (array $alanlar): array => array_intersect_key($a, array_flip($alanlar));
        $metin = static fn (mixed $m): array => \is_string($m) && '' !== trim($m) ? [['tur' => 'metin', 'metin' => $m]] : [];

        return match ($a['tur'] ?? null) {
            // Kapak alanları aynı ad; boş metin bloksuz ekran olur.
            'tanitim' => ['id' => $a['id'] ?? null, 'tur' => 'ekran', 'baslik' => $a['baslik'] ?? null]
                + $al(['gorsel', 'gorselMetni', 'duzen'])
                + ['bloklar' => $metin($a['metin'] ?? null), 'dugme' => $a['dugme'] ?? null]
                + $al(['hedef', 'ilerlemeGoster']),
            // Açıklama paragraf olur; tek seçimde düğme yok (seçenek geçirir).
            'soru' => ['id' => $a['id'] ?? null, 'tur' => 'ekran', 'baslik' => $a['soru'] ?? null, 'bloklar' => [
                ...$metin($a['aciklama'] ?? null),
                ['tur' => 'soru'] + $al(['secimTuru', 'duzen', 'yanitZorunlu', 'secenekler']),
            ]] + ('tekli' === ($a['secimTuru'] ?? 'tekli') ? [] : ['dugme' => $a['devamDugmesi'] ?? 'Devam'])
                + $al(['ilerlemeGoster']),
            'yorum' => ['id' => $a['id'] ?? null, 'tur' => 'ekran', 'baslik' => $a['baslik'] ?? null, 'bloklar' => \is_string($a['metin'] ?? null) && '' !== trim($a['metin'])
                ? [['tur' => 'yorum', 'metin' => $a['metin'], 'yazar' => $a['yazar'] ?? null] + (\is_string($a['unvan'] ?? null) && '' !== $a['unvan'] ? ['unvan' => $a['unvan']] : [])]
                : [], 'dugme' => $a['dugme'] ?? null] + $al(['ilerlemeGoster']),
            default => $a,
        };
    }
}
