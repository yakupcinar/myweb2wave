<?php

declare(strict_types=1);

namespace App\Command;

use App\Atif\MetaHarcamalari;
use App\Atif\TiktokHarcamalari;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * M5d: günlük cron — kampanya harcaması, son N gün (yeniden yazılır). A31:
 * bağlı her ağdan (Meta, TikTok); biri hata verirse öteki yine yazılır, çıkış
 * kodu hata olur. Hiçbiri bağlı değilse hata (cron sessizce boşa dönmesin).
 */
#[AsCommand('app:reklam-harcamalari:cek', 'Meta ve TikTok kampanya harcamasını çek (son N gün)')]
final readonly class ReklamHarcamalariCekCommand
{
    public function __construct(private MetaHarcamalari $meta, private TiktokHarcamalari $tiktok)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Kaç gün (bugün dahil, 1–90)')] int $gun = 7): int
    {
        if ($gun < 1 || $gun > 90) {
            $io->error('--gun 1 ile 90 arasında olmalı');

            return Command::INVALID;
        }
        $aglar = array_filter(['Meta' => $this->meta, 'TikTok' => $this->tiktok], static fn (MetaHarcamalari|TiktokHarcamalari $a): bool => $a->acik());
        if ([] === $aglar) {
            $io->error('Reklam harcaması kapalı: panelde Entegrasyonlar › Meta ya da TikTok bağlı değil (Meta için META_GRAPH_API_VERSION da gerekir)');

            return Command::FAILURE;
        }
        $hata = false;
        foreach ($aglar as $ad => $ag) {
            $sonuc = $ag->cek($gun);
            if (\is_string($sonuc)) {
                $io->error("{$ad}: {$sonuc}");
                $hata = true;
                continue;
            }
            $io->writeln(sprintf('%s — Yazılan: %d; atlanan (biçim dışı): %d', $ad, $sonuc['satir'], $sonuc['atlanan']));
        }

        return $hata ? Command::FAILURE : Command::SUCCESS;
    }
}
