<?php

declare(strict_types=1);

namespace App\Command;

use App\Ai\AiAyarlari;
use App\Ai\HuniAsistani;
use App\Huni\Huniler;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A6: AI değerlendirmesi — her brief için geçici (boş şablonlu) huni açar,
 * panel asistanının döngüsünü gerçek Claude ile koşar, sonucu ölçer, huniyi
 * kaldırır. Ölçü: geçerli tanım (kaydedildi + tamam), tur, maliyet, süre;
 * maliyet kayıtları ai_istekleri'nde kalır. Model/efor karşılaştırması için
 * --model / --efor. Anahtar yoksa hiçbir istek gitmez.
 * Örnek: bin/console app:ai-degerlendir --efor=low.
 */
#[AsCommand('app:ai-degerlendir', 'AI değerlendirmesi: brief seti → geçerli tanım oranı, tur, maliyet (JSON)')]
final readonly class AiDegerlendirCommand
{
    public function __construct(
        private HuniAsistani $asistan,
        private AiAyarlari $ayarlar,
        private Huniler $huniler,
        private Connection $db,
        #[Autowire('%kernel.project_dir%/config/ai/degerlendirme.json')]
        private string $varsayilanBriefler,
    ) {
    }

    public function __invoke(
        OutputInterface $cikti,
        #[Option('brief seti (JSON: [{ad, istem}])')] string $briefler = '',
        #[Option('uygulama')] string $app = 'keepchat',
        #[Option('model (boş = ayardaki)')] string $model = '',
        #[Option('efor: low, medium, high, xhigh, max (boş = ayardaki)')] string $efor = '',
        #[Option('geçici huni adı öneki')] string $onek = '',
    ): int {
        if (!$this->ayarlar->acik()) {
            $cikti->writeln('AI kapalı: ANTHROPIC_API_KEY boş (core/.env.local). İstek gönderilmedi.');

            return Command::FAILURE;
        }
        $set = json_decode((string) @file_get_contents('' === $briefler ? $this->varsayilanBriefler : $briefler), true);
        if (!\is_array($set) || [] === $set) {
            $cikti->writeln('Brief seti okunamadı.');

            return Command::FAILURE;
        }
        $ayar = new AiAyarlari(
            $this->ayarlar->anahtar,
            '' === $model ? $this->ayarlar->model : $model,
            '' === $efor ? $this->ayarlar->efor : $efor,
            $this->ayarlar->azamiTur,
            $this->ayarlar->azamiJeton,
        );
        $asistan = $this->asistan->ayarla($ayar);
        $onek = '' === $onek ? 'ai-deger-'.gmdate('YmdHis') : $onek;

        $sonuclar = [];
        foreach (array_values($set) as $i => $b) {
            $ad = \is_array($b) && \is_string($b['ad'] ?? null) ? $b['ad'] : 'brief-'.($i + 1);
            $istem = \is_array($b) && \is_string($b['istem'] ?? null) ? $b['istem'] : '';
            $huni = $onek.'-'.($i + 1);
            $hatalar = $this->huniler->yeniHuni($app, $huni, null);
            if ([] !== $hatalar) {
                $cikti->writeln("Geçici huni açılamadı ({$huni}): ".implode(' · ', $hatalar));

                return Command::FAILURE;
            }
            try {
                $sonuc = $asistan->calistir($app, $huni, $istem, static function (): void {});
                $kayit = $this->db->fetchAssociative(
                    'SELECT tur, maliyet_mikrodolar, kaydedildi, sure_ms FROM ai_istekleri WHERE app = :app AND huni = :huni ORDER BY id DESC LIMIT 1',
                    ['app' => $app, 'huni' => $huni],
                ) ?: [];
                $taslak = $this->huniler->taslak($app, $huni);
                $kaydedildi = true === ($kayit['kaydedildi'] ?? false);
                $sonuclar[] = [
                    'ad' => $ad,
                    'huni' => $huni,
                    'sonuc' => $sonuc,
                    'kaydedildi' => $kaydedildi,
                    // Kayıt çekirdekte doğrulanır: kaydedildiyse tanım geçerli.
                    'gecerli' => $kaydedildi && 'tamam' === $sonuc,
                    'adim' => \is_array($taslak['adimlar'] ?? null) ? \count($taslak['adimlar']) : 0,
                    'tur' => self::sayi($kayit['tur'] ?? 0),
                    'maliyetMikrodolar' => self::sayi($kayit['maliyet_mikrodolar'] ?? 0),
                    'sureMs' => self::sayi($kayit['sure_ms'] ?? 0),
                ];
            } finally {
                $this->huniler->kaldir($app, $huni);
            }
        }

        $n = \count($sonuclar);
        $gecerli = \count(array_filter($sonuclar, static fn (array $s): bool => $s['gecerli']));
        $cikti->writeln(json_encode([
            'briefler' => $sonuclar,
            'ozet' => [
                'brief' => $n,
                'gecerli' => $gecerli,
                'gecerliOran' => round($gecerli / $n, 3),
                'ortTur' => round(array_sum(array_column($sonuclar, 'tur')) / $n, 2),
                'toplamMaliyetMikrodolar' => array_sum(array_column($sonuclar, 'maliyetMikrodolar')),
                'model' => $ayar->model,
                'efor' => $ayar->efor,
                'ortSureMs' => (int) round(array_sum(array_column($sonuclar, 'sureMs')) / $n),
            ],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    private static function sayi(mixed $d): int
    {
        return is_numeric($d) ? (int) $d : 0;
    }
}
