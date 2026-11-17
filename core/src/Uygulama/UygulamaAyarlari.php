<?php

declare(strict_types=1);

namespace App\Uygulama;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * M9a (A23): uygulama kimliği — panelde düzenlenir (eskiden yaml / .env).
 *
 * - Şema giriş bağlantısını uygulamada açar (<şema>://giris?jeton=…); iOS
 *   Info.plist'tekiyle aynı olmalı — değişirse eski bağlantılar açılmaz.
 * - Ad ve destek e-postası her e-postada: gönderen adı ve yanıt adresi
 *   (no-reply'a yanıt yazan kişi kaybolmasın).
 * - Ayar satırı yoksa yedek YOK: şema gerektiren yol hata verir (§12).
 */
final readonly class UygulamaAyarlari
{
    /** alan => en çok karakter (göçteki CHECK ve sözleşmeyle aynı). */
    public const ALANLAR = ['ad' => 60, 'sema' => 32, 'magazaAdresi' => 300, 'destekEpostasi' => 254];

    public function __construct(
        private Connection $db,
        #[Autowire('%env(MAIL_GONDEREN)%')]
        private string $gonderen,
    ) {
    }

    /** @return array{ad: string, sema: string, magazaAdresi: string, destekEpostasi: string} */
    public function oku(string $app): array
    {
        $s = $this->db->fetchAssociative('SELECT ad, sema, magaza_adresi, destek_epostasi FROM uygulama_ayarlari WHERE app = ?', [$app]);
        $metin = static fn (string $a): string => \is_array($s) && \is_string($s[$a] ?? null) ? $s[$a] : '';

        return ['ad' => $metin('ad'), 'sema' => $metin('sema'), 'magazaAdresi' => $metin('magaza_adresi'), 'destekEpostasi' => $metin('destek_epostasi')];
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function kaydet(string $app, mixed $veri): array
    {
        if (!\is_array($veri)) {
            return ['gövde bir nesne olmalı'];
        }
        $hatalar = [];
        if ([] !== $fazla = array_diff(array_map(strval(...), array_keys($veri)), array_keys(self::ALANLAR))) {
            $hatalar[] = 'bilinmeyen alan: '.implode(', ', $fazla);
        }
        $d = [];
        foreach (self::ALANLAR as $alan => $azami) {
            $v = $veri[$alan] ?? '';
            if (!\is_string($v)) {
                $hatalar[] = "{$alan}: metin olmalı";
                $v = '';
            }
            $d[$alan] = trim($v);
            if (mb_strlen($d[$alan]) > $azami) {
                $hatalar[] = "{$alan}: en çok {$azami} karakter";
            }
        }
        if ([] !== $hatalar) {
            return $hatalar;
        }
        if ('' === $d['ad']) {
            $hatalar[] = 'ad: boş olamaz';
        }
        if (1 !== preg_match('/^[a-z][a-z0-9+.-]{1,31}$/', $d['sema'])) {
            $hatalar[] = 'sema: küçük harfle başlayan 2–32 karakter (a-z, 0-9, + . -), ör. keepchat';
        }
        if ('' !== $d['magazaAdresi'] && (!str_starts_with($d['magazaAdresi'], 'https://') || false === filter_var($d['magazaAdresi'], \FILTER_VALIDATE_URL))) {
            $hatalar[] = 'magazaAdresi: https:// ile başlayan geçerli adres ya da boş';
        }
        if ('' !== $d['destekEpostasi'] && false === filter_var($d['destekEpostasi'], \FILTER_VALIDATE_EMAIL)) {
            $hatalar[] = 'destekEpostasi: geçerli e-posta ya da boş';
        }
        if ([] !== $hatalar) {
            return $hatalar;
        }
        $this->db->executeStatement(
            'INSERT INTO uygulama_ayarlari (app, ad, sema, magaza_adresi, destek_epostasi) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (app) DO UPDATE SET ad = EXCLUDED.ad, sema = EXCLUDED.sema, magaza_adresi = EXCLUDED.magaza_adresi,
                 destek_epostasi = EXCLUDED.destek_epostasi, updated_at = now()',
            [$app, $d['ad'], $d['sema'], $d['magazaAdresi'], $d['destekEpostasi']],
        );

        return [];
    }

    public function sema(string $app): string
    {
        $sema = $this->oku($app)['sema'];

        return '' !== $sema ? $sema : throw new \LogicException("'{$app}' için URL şeması yapılandırılmamış (panel → Ayarlar)");
    }

    public function magazaAdresi(string $app): ?string
    {
        $adres = $this->oku($app)['magazaAdresi'];

        return '' === $adres ? null : $adres;
    }

    /** Uygulamanın adıyla gönderen + destek e-postası yanıt adresi; alıcı ve içerik çağıranda. */
    public function eposta(string $app): Email
    {
        $a = $this->oku($app);
        $e = (new Email())->from(new Address($this->gonderen, $a['ad']));

        return '' === $a['destekEpostasi'] ? $e : $e->replyTo($a['destekEpostasi']);
    }
}
