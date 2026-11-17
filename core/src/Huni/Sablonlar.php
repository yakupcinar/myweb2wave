<?php

declare(strict_types=1);

namespace App\Huni;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * V6: niş şablonları — depodaki tanım dosyaları (`config/huni/sablonlar/`),
 * yeni huninin başlangıcı ve AI için örnek. Seçim tanımı KOPYALAR (V2 karar
 * 2 gibi): şablon sonradan değişse açılmış huniler değişmez.
 *
 * Dosyada uygulamaya bağlı hiçbir şey yok: tema hazır temanın adıyla
 * (`hazirTema`, Tema::HAZIR_TEMALAR — tek kaynak), paywall planı boş — açılışta
 * uygulamanın ilk planı yazılır (boş şablonla aynı kural). Görsel yok:
 * medya uygulamaya ait (E2.3), başka uygulamada geçersiz olurdu.
 */
final readonly class Sablonlar
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/huni/sablonlar')]
        private string $dizin,
    ) {
    }

    /** @return list<array{anahtar: string, ad: string, aciklama: string}> dosya adı sırasıyla */
    public function liste(): array
    {
        $liste = [];
        foreach ($this->dosyalar() as $anahtar => $dosya) {
            $s = self::oku($dosya);
            $liste[] = ['anahtar' => $anahtar, 'ad' => self::metin($s['ad'] ?? null), 'aciklama' => self::metin($s['aciklama'] ?? null)];
        }

        return $liste;
    }

    /** @return array<mixed>|null bu plana bağlanmış tanım; şablon yoksa null */
    public function tanim(string $anahtar, string $plan): ?array
    {
        $dosya = $this->dosyalar()[$anahtar] ?? null;
        if (null === $dosya) {
            return null;
        }
        $s = self::oku($dosya);
        $tanim = \is_array($s['tanim'] ?? null) ? $s['tanim'] : [];
        $tema = Tema::HAZIR_TEMALAR[self::metin($s['hazirTema'] ?? null)]['tema'] ?? null;
        if (null !== $tema) {
            $tanim = ['tema' => $tema] + $tanim;
        }
        $adimlar = \is_array($tanim['adimlar'] ?? null) ? $tanim['adimlar'] : [];
        foreach ($adimlar as $i => $adim) {
            if (\is_array($adim) && 'paywall' === ($adim['tur'] ?? null)) {
                $adimlar[$i] = ['plan' => $plan] + $adim;
            }
        }
        $tanim['adimlar'] = $adimlar;

        return $tanim;
    }

    /** @return array<string, string> anahtar → dosya */
    private function dosyalar(): array
    {
        $d = [];
        foreach (glob($this->dizin.'/*.json') ?: [] as $dosya) {
            $d[basename($dosya, '.json')] = $dosya;
        }
        ksort($d);

        return $d;
    }

    /** @return array<mixed> */
    private static function oku(string $dosya): array
    {
        $j = json_decode((string) file_get_contents($dosya), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($j) ? $j : [];
    }

    private static function metin(mixed $d): string
    {
        return \is_string($d) ? $d : '';
    }
}
