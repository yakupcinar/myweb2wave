<?php

declare(strict_types=1);

namespace App\Panel;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Ç152: paneldeki her yazmanın denetim izi (panel_denetim, yalnız ekleme) —
 * rota başına elle değil tek yerden, yeni uç da kendiliğinden yazılır.
 * Yalnız oturum açmış kullanıcı: oturumsuz istek bir şey değiştiremez, tablo
 * da taramayla dolmasın. Gövde yazılmaz (anahtar, sır, metin); kaynak rota
 * parametreleridir. Reddedilen (403/422…) deneme de durumuyla yazılır.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class PanelDenetimi
{
    private const YAZMA = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private Connection $db, private TokenStorageInterface $jetonlar)
    {
    }

    public function __invoke(ResponseEvent $olay): void
    {
        $istek = $olay->getRequest();
        if (!$olay->isMainRequest() || !\in_array($istek->getMethod(), self::YAZMA, true) || !str_starts_with($istek->getPathInfo(), '/panel')) {
            return;
        }
        $kullanici = $this->jetonlar->getToken()?->getUserIdentifier();
        if (null === $kullanici || '' === $kullanici) {
            return;
        }
        $kaynak = $istek->attributes->get('_route_params');
        $rota = $istek->attributes->get('_route');
        $this->db->insert('panel_denetim', [
            'kullanici' => $kullanici,
            'yontem' => $istek->getMethod(),
            'rota' => \is_string($rota) ? $rota : '',
            'kaynak' => json_encode(\is_array($kaynak) ? $kaynak : [], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'durum' => $olay->getResponse()->getStatusCode(),
        ]);
    }

    /**
     * Son kayıtlar, yeniden eskiye; kaynak nesne (panel sayfası ve komut).
     *
     * @return list<array{zaman: string, kullanici: string, yontem: string, rota: string, kaynak: array<string, string>, durum: int}>
     */
    public function son(int $adet): array
    {
        /** @var list<array{zaman: string, kullanici: string, yontem: string, rota: string, kaynak: string, durum: int}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT to_char(zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS zaman, kullanici, yontem, rota, kaynak::text AS kaynak, durum
             FROM panel_denetim ORDER BY id DESC LIMIT :n",
            ['n' => $adet],
            ['n' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static function (array $s): array {
            /** @var array<string, string> $kaynak */
            $kaynak = json_decode($s['kaynak'], true, flags: \JSON_THROW_ON_ERROR);
            ksort($kaynak); // JSONB anahtar sırasını tutmaz (tuzak §28)
            $s['kaynak'] = $kaynak;

            return $s;
        }, $satirlar);
    }
}
