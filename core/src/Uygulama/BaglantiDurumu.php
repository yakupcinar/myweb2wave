<?php

declare(strict_types=1);

namespace App\Uygulama;

use App\RevenueCat\Aktarim;
use App\RevenueCat\MagazaAboneligi;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * BR1: panelin "Uygulama bağlantısı" listesi — bir uygulamanın çekirdeğe
 * gerçekten bağlanıp bağlanmadığı çalışan sistemden ölçülür (CanliyaGecis
 * gibi; elle işaretlenmez). KeepChat'te bağlama kodu yazılmış ama canlıda
 * kapalıydı (Ç158) — bu liste onu ilk bakışta gösterir. Anahtar yanıta girmez.
 */
final readonly class BaglantiDurumu
{
    public function __construct(
        private Connection $db,
        /** @var array<string, string> */
        #[Autowire('%uygulama_anahtarlari%')]
        private array $anahtarlar,
        private Aktarim $aktarim,
        private MagazaAboneligi $magaza,
    ) {
    }

    /**
     * @return array{
     *     anahtar: array{tamam: bool},
     *     istek: array{tamam: bool, son: string|null},
     *     baglama: array{tamam: bool, sayi: int, son: string|null},
     *     girisBaglantisi: array{tamam: bool, son: string|null},
     *     revenuecat: array{tamam: bool, acik: bool, gonderilen: int, bekleyen: int},
     *     magaza: array{tamam: bool}
     * }
     */
    public function durum(string $app): array
    {
        $zaman = static fn (mixed $z): ?string => \is_string($z) ? (new \DateTimeImmutable($z))->format(\DATE_ATOM) : null;
        $sayi = static fn (mixed $n): int => is_numeric($n) ? (int) $n : 0;
        $istek = $zaman($this->db->fetchOne('SELECT son_istek_at FROM uygulama_istekleri WHERE app = ?', [$app]));
        $b = $this->db->fetchAssociative('SELECT count(*) AS sayi, max(created_at) AS son FROM baglantilar WHERE app = ?', [$app]) ?: [];
        $giris = $zaman($this->db->fetchOne('SELECT max(kullanildi_at) FROM giris_jetonlari WHERE app = ?', [$app]));
        $r = $this->db->fetchAssociative(
            'SELECT count(gonderildi_at) AS gonderilen, count(*) FILTER (WHERE gonderildi_at IS NULL) AS bekleyen FROM revenuecat_gidenler WHERE app = ?',
            [$app],
        ) ?: [];
        $rcAcik = $this->aktarim->acik($app);
        $gonderilen = $sayi($r['gonderilen'] ?? 0);

        return [
            'anahtar' => ['tamam' => '' !== ($this->anahtarlar[$app] ?? '')],
            'istek' => ['tamam' => null !== $istek, 'son' => $istek],
            'baglama' => ['tamam' => $sayi($b['sayi'] ?? 0) > 0, 'sayi' => $sayi($b['sayi'] ?? 0), 'son' => $zaman($b['son'] ?? null)],
            'girisBaglantisi' => ['tamam' => null !== $giris, 'son' => $giris],
            'revenuecat' => ['tamam' => $rcAcik && $gonderilen > 0, 'acik' => $rcAcik, 'gonderilen' => $gonderilen, 'bekleyen' => $sayi($r['bekleyen'] ?? 0)],
            'magaza' => ['tamam' => $this->magaza->acik($app)],
        ];
    }
}
