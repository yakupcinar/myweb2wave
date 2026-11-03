<?php

declare(strict_types=1);

namespace App\Command;

use App\Billing\Planlar;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * M1a: Stripe'ta önceden açılmış fiyatı kataloğa alır — M1a'dan önce
 * ortam değişkeninde duran plan (KeepChat aylık) bununla taşınır:
 *   bin/console app:plan-ice-aktar keepchat monthly price_… --ad "Aylık".
 * Yeni plan panelden açılır (Stripe ürünü + fiyatı orada oluşur).
 */
#[AsCommand('app:plan-ice-aktar', "Stripe'taki fiyatı plan olarak kataloğa al: <app> <anahtar> <fiyat>")]
final readonly class PlanIceAktarCommand
{
    public function __construct(
        private Planlar $planlar,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    public function __invoke(
        OutputInterface $cikti,
        #[Argument('uygulama, ör. keepchat')] string $app,
        #[Argument('plan anahtarı (huni paywall\'u bununla seçer), ör. monthly')] string $anahtar,
        #[Argument('Stripe fiyat kimliği, price_…')] string $fiyat,
        #[Option('paneldeki ad')] string $ad = '',
    ): int {
        if (!isset($this->uygulamalar[$app])) {
            $cikti->writeln("bilinmeyen uygulama: {$app}");

            return Command::INVALID;
        }
        $hatalar = $this->planlar->iceAktar($app, $anahtar, '' === $ad ? $anahtar : $ad, $fiyat);
        foreach ($hatalar as $hata) {
            $cikti->writeln($hata);
        }
        if ([] === $hatalar) {
            $cikti->writeln("{$app} · {$anahtar} → {$fiyat} kataloğa alındı.");
        }

        return [] === $hatalar ? Command::SUCCESS : Command::FAILURE;
    }
}
