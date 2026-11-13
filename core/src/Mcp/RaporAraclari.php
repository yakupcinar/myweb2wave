<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Huni\Deneyler;
use App\Panel\Rapor;
use App\Panel\RaporSuzgeci;

/**
 * M7c1 (A21): MCP'de yalnız okuyan rapor araçları — anahtarın uygulamasıyla
 * sınırlı (Rapor'un app süzgeci). Panelle aynı hesap; satır listesi ve
 * serbest yanıt yok (kişisel veri MCP'ye gitmez). Meta harcaması reklam
 * hesabı düzeyinde: uygulamaya bölünmez, gelir ve ziyaret bölünür.
 */
final readonly class RaporAraclari
{
    private const GUN = ['type' => 'integer', 'enum' => Rapor::GUNLER, 'default' => 30, 'description' => 'Aralık (gün): ziyaretin geldiği son N gün'];

    public function __construct(
        private Rapor $rapor,
        private Deneyler $deneyler,
    ) {
    }

    /** @return list<array{name: string, description: string, input_schema: array<string, mixed>}> */
    public function tanimlar(): array
    {
        $gunlu = static fn (string $ad, string $aciklama): array => [
            'name' => $ad, 'description' => $aciklama,
            'input_schema' => ['type' => 'object', 'properties' => ['gun' => self::GUN], 'additionalProperties' => false],
        ];

        return [
            $gunlu('rapor_ozet', 'Huni özeti: reklam / organik ziyaret, ödemeye geçen, deneme, ödedi (ilk ödeme ya da fatura); reklam ölçümü izni; günlük seri.'),
            $gunlu('rapor_kaynaklar', 'Kaynak / ortam / kampanya başına ziyaret, ödemeye geçti, deneme, ödedi ve tahsil edilen gelir (para birimi başına, iade düşülmüş).'),
            $gunlu('rapor_kohort', 'Kohort geliri: ziyaret haftası (UTC) × ilk 24 saat / 7 / 30 / 60 gün, ziyaret başına birikimli gelir; dolmamış pencere olgun=false.'),
            $gunlu('rapor_kampanyalar', 'Meta kampanyası başına harcama, aynı adlı utm_campaign ile Meta\'dan gelen ziyaret / ödedi / gelir ve ROAS (hesabın para biriminde).'),
            $gunlu('rapor_cevaplar', 'Anket cevapları: seçenek başına seçen ziyaret ve abone olan (dönüşüm = abone / seçen).'),
            [
                'name' => 'deney_sonuclari',
                'description' => 'Deneyler ve sonuçları (varyant başına ziyaret, ödemeye geçti, abone, ödedi, kazanma olasılığı). ad verilmezse hepsi.',
                'input_schema' => ['type' => 'object', 'properties' => ['ad' => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$']], 'additionalProperties' => false],
            ],
        ];
    }

    /**
     * @param array<mixed> $girdi
     *
     * @return array{icerik: array<string, mixed>, hata: bool}
     */
    public function calistir(string $ad, array $girdi, string $app): array
    {
        $gun = $girdi['gun'] ?? 30;
        if (!\in_array($gun, Rapor::GUNLER, true)) {
            return ['icerik' => ['hata' => 'gun: 7, 30 ya da 90'], 'hata' => true];
        }

        return match ($ad) {
            'rapor_ozet' => ['icerik' => ['gun' => $gun, 'sayimlar' => $this->rapor->sayimlar($gun, RaporSuzgeci::uygulama($app)), 'izinler' => $this->rapor->izinler($gun, RaporSuzgeci::uygulama($app)), 'gunluk' => $this->rapor->gunluk($gun, RaporSuzgeci::uygulama($app))], 'hata' => false],
            'rapor_kaynaklar' => ['icerik' => ['gun' => $gun, 'kaynaklar' => $this->rapor->utm($gun, RaporSuzgeci::uygulama($app))], 'hata' => false],
            'rapor_kohort' => ['icerik' => ['gun' => $gun, 'kohort' => $this->rapor->kohort($gun, RaporSuzgeci::uygulama($app))], 'hata' => false],
            'rapor_kampanyalar' => ['icerik' => ['gun' => $gun, 'kampanyalar' => $this->rapor->kampanyalar($gun, RaporSuzgeci::uygulama($app))], 'hata' => false],
            'rapor_cevaplar' => ['icerik' => ['gun' => $gun, 'cevaplar' => $this->rapor->cevaplar($gun, RaporSuzgeci::uygulama($app))], 'hata' => false],
            'deney_sonuclari' => $this->deneyler($app, $girdi['ad'] ?? null),
            default => ['icerik' => ['hata' => "bilinmeyen araç: {$ad}"], 'hata' => true],
        };
    }

    /** @return array{icerik: array<string, mixed>, hata: bool} */
    private function deneyler(string $app, mixed $ad): array
    {
        if (\is_string($ad)) {
            $s = $this->deneyler->sonuclar($app, $ad);

            return null === $s ? ['icerik' => ['hata' => "deney yok: {$ad}"], 'hata' => true] : ['icerik' => $s, 'hata' => false];
        }
        $hepsi = [];
        foreach ($this->deneyler->liste($app) as $d) {
            $hepsi[] = 'taslak' === $d['durum'] ? $d : ($this->deneyler->sonuclar($app, $d['ad']) ?? $d);
        }

        return ['icerik' => ['deneyler' => $hepsi], 'hata' => false];
    }
}
