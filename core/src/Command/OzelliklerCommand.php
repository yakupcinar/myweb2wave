<?php

declare(strict_types=1);

namespace App\Command;

use App\Ozellik\Ozellikler;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * T2: özellik deposunu okur — ziyaretin (ya da --kisi ile kişinin)
 * özellikleri JSON. Ölçüm ve hata ayıklama için; panel CRM'i (P5) aynı
 * servisi kullanacak. Örnek: bin/console app:ozellikler <ziyaret-uuid>.
 */
#[AsCommand('app:ozellikler', 'Özellik deposu: <ziyaret> · --kisi <kişi> · --sozluk')]
final readonly class OzelliklerCommand
{
    public function __construct(private Ozellikler $ozellikler)
    {
    }

    public function __invoke(
        OutputInterface $cikti,
        #[Argument('ziyaret (ya da --kisi ile kişi) kimliği')] string $kimlik = '',
        #[Option('kimlik kişinin')] bool $kisi = false,
        #[Option('ad sözlüğünü yaz')] bool $sozluk = false,
    ): int {
        if ($sozluk) {
            $cikti->writeln(self::json(array_map(static fn (array $t): array => ['tur' => $t[0], 'aciklama' => $t[1]], Ozellikler::SOZLUK)));

            return Command::SUCCESS;
        }
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $kimlik)) {
            $cikti->writeln(self::json(['hatalar' => ['kimlik UUID olmalı']]));

            return Command::INVALID;
        }
        $cikti->writeln(self::json($kisi ? $this->ozellikler->kisinin($kimlik) : $this->ozellikler->ziyaretin($kimlik)));

        return Command::SUCCESS;
    }

    private static function json(mixed $deger): string
    {
        return json_encode($deger, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
