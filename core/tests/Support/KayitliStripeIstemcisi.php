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

        return (new CurlClient())->request($method, $absUrl, $headers, $params, $hasFile, $apiMode, $maxNetworkRetries);
    }
}
