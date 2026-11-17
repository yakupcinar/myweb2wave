<?php

declare(strict_types=1);

namespace App\Command;

use App\Billing\MusteriPortali;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** M6b (A19): müşteri portalı ayarını Stripe'ta kur / güncelle (her ortamda bir kez, değişince yine). */
#[AsCommand('app:stripe-portal:kur', 'Stripe müşteri portalı ayarını kodla aynı yap')]
final readonly class StripePortalKurCommand
{
    public function __construct(private MusteriPortali $portal)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $sonuc = $this->portal->kur();
        } catch (ApiErrorException $e) {
            $io->error('Stripe reddetti: '.($e->getError()->message ?? $e->getMessage()));

            return Command::FAILURE;
        }
        $io->writeln(($sonuc['yeni'] ? 'Kuruldu: ' : 'Güncellendi: ').$sonuc['id']);

        return Command::SUCCESS;
    }
}
