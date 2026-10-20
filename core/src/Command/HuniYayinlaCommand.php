<?php

declare(strict_types=1);

namespace App\Command;

use App\Huni\Huniler;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Taslağı yayınlar ya da önizleme bağlantısı üretir (A7). Panel ekranı
 * (E1.2) gelene kadar tek yol; sonra da sunucuda elle kullanılabilir.
 */
#[AsCommand('app:huni', 'Huni: yayinla <app> [<huni>] | onizleme <app> [<huni>]')]
final readonly class HuniYayinlaCommand
{
    public function __construct(
        private Huniler $huniler,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $funnelBaseUrl,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('yayinla ya da onizleme')] string $islem,
        #[Argument('uygulama, ör. keepchat')] string $app,
        #[Argument('huni adı (A8)')] string $huni = 'genel',
    ): int {
        if ('onizleme' === $islem) {
            $io->writeln($this->funnelBaseUrl.'/'.$app.'/?huni='.$huni.'&onizleme='.$this->huniler->onizlemeAnahtari($app, $huni, time()));

            return Command::SUCCESS;
        }
        if ('yayinla' !== $islem) {
            $io->error('işlem yayinla ya da onizleme');

            return Command::INVALID;
        }
        $sonuc = $this->huniler->yayinla($app, $huni);
        if (\is_array($sonuc)) {
            $io->error(['Yayınlanmadı:', ...$sonuc]);

            return Command::FAILURE;
        }
        $io->success("{$app}/{$huni} v{$sonuc} yayında");

        return Command::SUCCESS;
    }
}
