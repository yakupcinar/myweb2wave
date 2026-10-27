<?php

declare(strict_types=1);

namespace App\Command;

use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * AI araç katmanını konsoldan çalıştırır (A1): panel sohbeti (A2) ve MCP
 * (A5) ile AYNI araçlar, aynı doğrulama — ayrı eşleme yok. Girdi aracın
 * JSON girdisi (`--girdi` ya da stdin), çıktı sonucun JSON'u; araç hata
 * döndürürse çıkış kodu 1. Örnek (depodan, Claude Code):
 *   bin/console app:huni-arac keepchat huni_oku --girdi '{"huni":"genel"}'
 *   bin/console app:huni-arac keepchat taslak_kaydet < istek.json.
 */
#[AsCommand('app:huni-arac', 'AI araçlarını çalıştır: <app> <araç> (girdi JSON: --girdi ya da stdin) · --liste araçlar')]
final readonly class HuniAracCommand
{
    public function __construct(
        private HuniAraclari $araclar,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    public function __invoke(
        OutputInterface $cikti,
        #[Argument('uygulama, ör. keepchat')] string $app,
        #[Argument('araç adı (--liste ile gör)')] string $arac = '',
        #[Option('aracın girdisi (JSON nesnesi); yoksa stdin')] ?string $girdi = null,
        #[Option('yalnız bu huniye dokun (panel sohbeti gibi)')] ?string $huni = null,
        #[Option('araç tanımlarını (şemalarıyla) yaz')] bool $liste = false,
    ): int {
        if (!isset($this->uygulamalar[$app])) {
            $cikti->writeln(self::json(['hatalar' => ["bilinmeyen uygulama: {$app}"]]));

            return Command::INVALID;
        }
        $kapsam = new AracKapsami($app, $huni);
        if ($liste || '' === $arac) {
            $cikti->writeln(self::json($this->araclar->tanimlar($kapsam)));

            return Command::SUCCESS;
        }
        $metin = $girdi ?? (string) stream_get_contents(\STDIN);
        try {
            $veri = json_decode('' === trim($metin) ? '{}' : $metin, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $cikti->writeln(self::json(['hatalar' => ['girdi JSON değil: '.$e->getMessage()]]));

            return Command::INVALID;
        }
        $sonuc = $this->araclar->calistir($arac, $veri, $kapsam);
        $cikti->writeln(self::json($sonuc->icerik));

        return $sonuc->hata ? Command::FAILURE : Command::SUCCESS;
    }

    private static function json(mixed $deger): string
    {
        return json_encode($deger, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
