<?php

declare(strict_types=1);

namespace App\Posta;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Bridge\Postmark\Event\PostmarkDeliveryEvent;
use Symfony\Component\Mime\Header\MailboxListHeader;

/**
 * Postmark alıcıyı pasif sayınca (406) köprü atmaz, bu olayı yayar; e-posta
 * gitmemiştir. Bastırma listesine yazılır — webhook gelmemiş ya da
 * gecikmiş olsa da (tuzak §57).
 */
#[AsEventListener]
final readonly class PostmarkTeslimDinleyicisi
{
    public function __construct(private Bastirma $bastirma, private LoggerInterface $logger)
    {
    }

    public function __invoke(PostmarkDeliveryEvent $olay): void
    {
        $basliklar = $olay->getHeaders();
        $akis = $basliklar->get('X-PM-Message-Stream')?->getBodyAsString() ?: Bastirma::ISLEM;
        $alici = $basliklar->get('To');
        if (!$alici instanceof MailboxListHeader) {
            return;
        }
        foreach ($alici->getAddresses() as $adres) {
            $this->bastirma->yaz($adres->getAddress(), $akis, true, 'InactiveRecipient', new \DateTimeImmutable());
        }
        // Adres log'a yazılmaz (kişisel veri); kayıt tablodadır.
        $this->logger->warning('Postmark alıcıyı pasif saydı, e-posta gitmedi', ['akis' => $akis, 'kod' => $olay->getErrorCode()]);
    }
}
