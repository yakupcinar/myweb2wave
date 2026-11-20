<?php

declare(strict_types=1);

namespace App\Tests\RevenueCat;

use App\RevenueCat\MagazaAboneligi;
use App\Tests\Support\SahteRevenueCat;
use App\Tests\Support\SahteRevenueCatV2;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * G2 (Ç159): web'e geçişte web aboneliği mağaza aboneliğinin bittiği an
 * başlar — bitiş yanlış okunursa ya çift ödeme ya da erişim boşluğu olur.
 * Yalnız erişim veren mağaza aboneliği sayılır; web faturası (Stripe…) değil.
 */
final class MagazaAboneligiTest extends TestCase
{
    private const K = 'b2f6c1d0-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        SahteRevenueCatV2::sifirla();
    }

    private function servis(): MagazaAboneligi
    {
        return new MagazaAboneligi(new MockHttpClient(new SahteRevenueCat()), new NullLogger(),
            ['keepchat' => ['proje' => 'projtest', 'anahtar' => 'sk_test_v2'], 'bos' => ['proje' => 'p', 'anahtar' => '']],
            'https://revenuecat.test');
    }

    /** @return array<string, mixed> */
    private static function abonelik(string $magaza, int $bitisSn, bool $erisim = true, string $yenileme = 'will_renew', ?int $endsSn = null): array
    {
        return ['object' => 'subscription', 'store' => $magaza, 'gives_access' => $erisim, 'auto_renewal_status' => $yenileme,
            'current_period_ends_at' => $bitisSn * 1000, 'ends_at' => null === $endsSn ? $bitisSn * 1000 : $endsSn * 1000];
    }

    public function testAppStoreBitisiVeYenilenme(): void
    {
        SahteRevenueCatV2::$abonelikler[self::K] = [self::abonelik('app_store', 1_800_000_000)];
        $s = $this->servis()->oku('keepchat', self::K);
        self::assertIsArray($s);
        self::assertSame(['2027-01-15T08:00:00+00:00', true], [$s['bitis']->format(\DATE_ATOM), $s['yenilenecek']]);
        self::assertSame([['url' => 'https://revenuecat.test/v2/projects/projtest/customers/'.self::K.'/subscriptions?limit=100', 'yetki' => 'Bearer sk_test_v2']], SahteRevenueCatV2::$istekler);
    }

    public function testWebFaturasiVeErisimsizSayilmaz(): void
    {
        SahteRevenueCatV2::$abonelikler[self::K] = [self::abonelik('stripe', 1_900_000_000), self::abonelik('app_store', 1_850_000_000, false)];
        self::assertNull($this->servis()->oku('keepchat', self::K));
    }

    /** Birden çok mağaza aboneliğinde en geç biten; zaten yenilenmişte ends_at (belge: yalnız orada ileri). */
    public function testEnGecBitenVeYenilenmisDonem(): void
    {
        SahteRevenueCatV2::$abonelikler[self::K] = [
            self::abonelik('play_store', 1_800_000_000, true, 'will_not_renew'),
            self::abonelik('app_store', 1_700_000_000, true, 'has_already_renewed', 1_810_000_000),
        ];
        $s = $this->servis()->oku('keepchat', self::K);
        self::assertIsArray($s);
        self::assertSame([1_810_000_000, true], [$s['bitis']->getTimestamp(), $s['yenilenecek']]);
    }

    public function testYenilenmeyecek(): void
    {
        SahteRevenueCatV2::$abonelikler[self::K] = [self::abonelik('app_store', 1_800_000_000, true, 'will_not_renew')];
        $s = $this->servis()->oku('keepchat', self::K);
        self::assertIsArray($s);
        self::assertFalse($s['yenilenecek']);
    }

    /** Bilinmeyen müşteri 404 = mağaza aboneliği yok; sunucu hatası "okunamadı" (geçiş başlamaz); ayarsız uygulama kapalı, istek yok. */
    public function testYokHataVeKapali(): void
    {
        self::assertNull($this->servis()->oku('keepchat', self::K));
        SahteRevenueCatV2::$durum = 500;
        self::assertSame('ulasilamadi', $this->servis()->oku('keepchat', self::K));
        SahteRevenueCatV2::$istekler = [];
        self::assertSame(['kapali', 'kapali'], [$this->servis()->oku('bos', self::K), $this->servis()->oku('baska', self::K)]);
        self::assertSame([], SahteRevenueCatV2::$istekler);
    }
}
