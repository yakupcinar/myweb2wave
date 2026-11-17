<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Billing\AbonelikYonetimi;
use App\Huni\Deneyler;
use App\Panel\Kullanicilar;
use Doctrine\DBAL\Connection;

/**
 * M7c2 (A21): MCP yazma araçları — yalnız yönetim izinli anahtarda kurulur.
 * Panelle aynı servisler (iptal dönem sonunda, iade son faturanın kalanı,
 * deney geçişleri); abonelik anahtarın uygulamasınınsa. E-posta maskeli.
 */
final readonly class YonetimAraclari
{
    private const ABONELIK = ['type' => 'string', 'pattern' => '^sub_[A-Za-z0-9]{1,250}$', 'description' => 'Stripe abonelik kimliği (abonelikler_listele)'];

    public function __construct(
        private Connection $db,
        private Kullanicilar $kullanicilar,
        private AbonelikYonetimi $abonelik,
        private Deneyler $deneyler,
    ) {
    }

    /** @return list<array{name: string, description: string, input_schema: array<string, mixed>, yikici: bool, okur: bool}> */
    public function tanimlar(): array
    {
        $tek = ['type' => 'object', 'required' => ['abonelik'], 'properties' => ['abonelik' => self::ABONELIK], 'additionalProperties' => false];

        return [
            ['name' => 'abonelikler_listele', 'okur' => true, 'yikici' => false,
                'description' => 'Abonelikler (en çok 200, en yeni güncellenen önce): kimlik, durum, maskeli e-posta, iptal planı, dönem sonu, son fatura. ara: e-posta / kaynak / kampanya içinde; durum: active, trialing, past_due, canceled…',
                'input_schema' => ['type' => 'object', 'properties' => ['ara' => ['type' => 'string', 'maxLength' => 100], 'durum' => ['type' => 'string', 'maxLength' => 40]], 'additionalProperties' => false]],
            ['name' => 'abonelik_iptal_planla', 'okur' => false, 'yikici' => true,
                'description' => 'Aboneliği DÖNEM SONUNDA iptal et (ödenen dönem kullanılır, yenilenmez; dönem bitene kadar geri alınabilir).', 'input_schema' => $tek],
            ['name' => 'abonelik_iptal_geri_al', 'okur' => false, 'yikici' => false,
                'description' => 'Planlı iptali geri al (dönem bitmeden).', 'input_schema' => $tek],
            ['name' => 'abonelik_son_fatura_iade', 'okur' => false, 'yikici' => true,
                'description' => 'Son tahsil edilen faturanın iade edilmemiş kalanını TAM iade et (geri alınamaz, gerçek para). Kullanıcı açıkça istemedikçe çağırma.', 'input_schema' => $tek],
            ['name' => 'deney_kaydet', 'okur' => false, 'yikici' => false,
                'description' => 'Yeni deney taslağı ya da taslaktakini güncelle. tur: icerik (aynı huni, koşulda deney.<ad>) | yonlendirme (varyant başına huni). varyantlar: [{ad, pay (toplam 100), huni? (yönlendirmede)}], 2–4 varyant; ilki kontrol.',
                'input_schema' => ['type' => 'object', 'required' => ['ad', 'tur', 'huni', 'varyantlar'], 'additionalProperties' => false, 'properties' => [
                    'ad' => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$'],
                    'tur' => ['type' => 'string', 'enum' => ['icerik', 'yonlendirme']],
                    'huni' => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$'],
                    'varyantlar' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 4, 'items' => ['type' => 'object']],
                ]]],
            ['name' => 'deney_durum', 'okur' => false, 'yikici' => true,
                'description' => 'Deney durumu: taslak → aktif ↔ durdu → bitti (bitti geri alınamaz; aktife geçerken huniler yayında olmalı).',
                'input_schema' => ['type' => 'object', 'required' => ['ad', 'durum'], 'additionalProperties' => false, 'properties' => [
                    'ad' => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$'], 'durum' => ['type' => 'string', 'enum' => ['aktif', 'durdu', 'bitti']],
                ]]],
        ];
    }

    /**
     * @param array<mixed> $g
     *
     * @return array{icerik: array<string, mixed>, hata: bool}
     */
    public function calistir(string $ad, array $g, string $app): array
    {
        if ('abonelikler_listele' === $ad) {
            $ara = \is_string($g['ara'] ?? null) ? $g['ara'] : '';
            $durum = \is_string($g['durum'] ?? null) ? $g['durum'] : '';

            return self::tamam(['abonelikler' => array_map(static fn (array $s): array => array_intersect_key($s, array_flip(
                ['abonelik', 'durum', 'email', 'iptalDonemSonu', 'donemSonu', 'sonFatura', 'kaynak', 'kampanya'],
            )), $this->kullanicilar->liste($ara, $durum, $app))]);
        }
        if (str_starts_with($ad, 'abonelik_')) {
            $sub = $g['abonelik'] ?? null;
            // Başka uygulamanın aboneliği "yok" sayılır (varlığı da sızmasın).
            if (!\is_string($sub) || false === $this->db->fetchOne('SELECT 1 FROM subscriptions WHERE stripe_subscription_id = ? AND app = ?', [$sub, $app])) {
                return self::hata('bilinmeyen abonelik');
            }
            $sonuc = match ($ad) {
                'abonelik_iptal_planla' => $this->abonelik->donemSonundaIptal($sub, true),
                'abonelik_iptal_geri_al' => $this->abonelik->donemSonundaIptal($sub, false),
                'abonelik_son_fatura_iade' => $this->abonelik->sonFaturayiIadeEt($sub),
                default => "bilinmeyen araç: {$ad}",
            };

            return null === $sonuc ? self::tamam(['tamam' => true, 'abonelik' => $sub]) : self::hata($sonuc);
        }
        if ('deney_kaydet' === $ad) {
            $h = $this->deneyler->kaydet($app, $g['ad'] ?? null, $g['tur'] ?? null, $g['huni'] ?? null, $g['varyantlar'] ?? null);

            return [] === $h ? self::tamam(['tamam' => true]) : self::hata(implode(' ', $h));
        }
        if ('deney_durum' === $ad) {
            $deney = \is_string($g['ad'] ?? null) ? $g['ad'] : '';
            $h = $this->deneyler->durum($app, $deney, $g['durum'] ?? null);

            return null === $h ? self::hata("deney yok: {$deney}") : ([] === $h ? self::tamam(['tamam' => true]) : self::hata(implode(' ', $h)));
        }

        return self::hata("bilinmeyen araç: {$ad}");
    }

    /**
     * @param array<string, mixed> $icerik
     *
     * @return array{icerik: array<string, mixed>, hata: bool}
     */
    private static function tamam(array $icerik): array
    {
        return ['icerik' => $icerik, 'hata' => false];
    }

    /** @return array{icerik: array<string, mixed>, hata: bool} */
    private static function hata(string $mesaj): array
    {
        return ['icerik' => ['hata' => $mesaj], 'hata' => true];
    }
}
