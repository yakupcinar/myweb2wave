<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

/**
 * Stripe'a giden her isteği kaydedip stripe-mock'a iletir. stripe-mock
 * parametre adlarını/tiplerini doğrular ama DEĞERLERİ (hangi fiyat, hangi
 * mod) bilmez — onları bu kayıttan ölçeriz.
 */
final class KayitliStripeIstemcisi implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<mixed>}> */
    public array $istekler = [];

    /** Doluysa istek stripe-mock'a gitmez, bu atılır (Stripe'a ulaşılamaması). */
    public ?\Throwable $firlat = null;

    /**
     * M1b: adres sonu → yanıt gövdesi; stripe-mock'un sabit nesnesinin
     * ölçemediği değerler için (ör. tek seferlik giriş fiyatı — mock her
     * fiyatı yinelenen döndürür).
     *
     * @var array<string, array<string, mixed>>
     */
    public array $yanitlar = [];

    /**
     * @param 'delete'|'get'|'post' $method
     * @param string                $absUrl
     * @param array<mixed>          $headers
     * @param array<mixed>          $params
     * @param bool                  $hasFile
     * @param 'v1'|'v2'             $apiMode
     * @param int|null              $maxNetworkRetries
     *
     * @return array<mixed> CurlClient'ın kendi dönüş tipi (stripe-php tipsiz bildiriyor)
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->istekler[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];
        if (null !== $this->firlat) {
            throw $this->firlat;
        }
        foreach ($this->yanitlar as $son => $govde) {
            // Sorgu (ör. expand) yol eşleşmesini bozmasın. Anahtar "post /v1/…"
            // ise yalnız o yöntem; "__durum" yanıtın HTTP kodu (ör. 402 kart reddi).
            $parca = explode(' ', $son, 2);
            $yontem = isset($parca[1]) ? $parca[0] : null;
            $yol = $parca[1] ?? $parca[0];
            if ((null === $yontem || $yontem === $method) && str_ends_with(explode('?', $absUrl)[0], $yol)) {
                $durum = \is_int($govde['__durum'] ?? null) ? $govde['__durum'] : 200;
                unset($govde['__durum']);

                return [json_encode($govde, \JSON_THROW_ON_ERROR), $durum, []];
            }
        }

        return (new CurlClient())->request($method, $absUrl, $headers, $params, $hasFile, $apiMode, $maxNetworkRetries);
    }
}
