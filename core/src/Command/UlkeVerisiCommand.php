<?php

declare(strict_types=1);

namespace App\Command;

use MaxMind\Db\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * M1c: DB-IP IP-to-Country Lite (CC BY 4.0, aylık) — ayın dosyasını indirir,
 * açar, okunabildiğini ölçer, sonra yerine koyar (yarım dosya kullanılmaz).
 * Ay başında yeni dosya geç yayınlanabilir: bu ay yoksa geçen ay denenir.
 *   bin/console app:ulke-verisi.
 */
#[AsCommand('app:ulke-verisi', 'DB-IP ülke verisini indir (para birimi seçimi, M1c)')]
final readonly class UlkeVerisiCommand
{
    private const ADRES = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%/var/ulke/dbip-country-lite.mmdb')]
        private string $dosya,
    ) {
    }

    public function __invoke(OutputInterface $cikti, #[Option('ay (YYYY-MM); boşsa bu ay, yoksa geçen ay')] string $ay = ''): int
    {
        $aylar = '' !== $ay ? [$ay] : [date('Y-m'), date('Y-m', (int) strtotime('first day of last month'))];
        foreach ($aylar as $a) {
            $yanit = $this->http->request('GET', \sprintf(self::ADRES, $a));
            if (200 !== $yanit->getStatusCode()) {
                $cikti->writeln("{$a}: HTTP {$yanit->getStatusCode()}");
                continue;
            }
            $acik = gzdecode($yanit->getContent());
            if (false === $acik) {
                $cikti->writeln("{$a}: gzip açılamadı");

                return Command::FAILURE;
            }
            @mkdir(\dirname($this->dosya), 0o775, true);
            $gecici = $this->dosya.'.yeni';
            file_put_contents($gecici, $acik);
            try {
                $tur = (new Reader($gecici))->metadata()->databaseType;
            } catch (\Throwable $e) {
                @unlink($gecici);
                $cikti->writeln("{$a}: dosya okunamadı — {$e->getMessage()}");

                return Command::FAILURE;
            }
            rename($gecici, $this->dosya);
            $cikti->writeln("{$a}: {$tur}, ".round(\strlen($acik) / 1_048_576, 1)." MB → {$this->dosya}");

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
