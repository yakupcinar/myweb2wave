<?php

declare(strict_types=1);

namespace App\Huni;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Huni taslağı, yayınlanmış sürümler ve önizleme (A7).
 * Yayınlanan sürüm değişmez; huni sayfası en sonuncuyu okur.
 */
final readonly class Huniler
{
    /** Önizleme bağlantısının ömrü. */
    public const ONIZLEME_SN = 3600;

    public function __construct(
        private Connection $db,
        /** @var array<string, array<string, string>> uygulama → plan → Stripe fiyatı */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
        #[Autowire('%kernel.secret%')]
        private string $sir,
    ) {
    }

    /** @return array{surum: int, tanim: array<mixed>}|null */
    public function yayinda(string $app): ?array
    {
        $s = $this->db->fetchAssociative(
            'SELECT surum, tanim FROM huni_surumleri WHERE app = :app ORDER BY surum DESC LIMIT 1',
            ['app' => $app],
        );

        return false === $s ? null : ['surum' => self::sayi($s['surum']), 'tanim' => self::json($s['tanim'])];
    }

    /** @return array<mixed>|null */
    public function taslak(string $app): ?array
    {
        $t = $this->db->fetchOne('SELECT taslak FROM huniler WHERE app = :app', ['app' => $app]);

        return false === $t ? null : self::json($t);
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function taslakKaydet(string $app, mixed $tanim): array
    {
        $hatalar = $this->hatalar($app, $tanim);
        if ([] === $hatalar) {
            $this->db->executeStatement(
                'INSERT INTO huniler (app, taslak) VALUES (:app, CAST(:t AS jsonb))
                 ON CONFLICT (app) DO UPDATE SET taslak = EXCLUDED.taslak, updated_at = now()',
                ['app' => $app, 't' => json_encode($tanim, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)],
            );
        }

        return $hatalar;
    }

    /**
     * Taslağı yeni, değişmez sürüm olarak yayınlar. Taslak geçersizse
     * (ör. plan yapılandırmadan kalktı) ya da yayındakiyle aynıysa yayınlamaz.
     *
     * @return int|list<string> yeni sürüm ya da hatalar
     */
    public function yayinla(string $app): int|array
    {
        return $this->db->transactional(function (Connection $db) use ($app): int|array {
            // Aynı anda iki yayın aynı sürüm numarasını almasın.
            $t = $db->fetchOne('SELECT taslak FROM huniler WHERE app = :app FOR UPDATE', ['app' => $app]);
            if (false === $t) {
                return ['bu uygulamanın taslağı yok'];
            }
            $tanim = self::json($t);
            $hatalar = $this->hatalar($app, $tanim);
            if ([] !== $hatalar) {
                return $hatalar;
            }
            if (!$this->taslakFarkli($app)) {
                return ['taslak yayındaki sürümle aynı — yayınlanacak değişiklik yok'];
            }
            $surum = $db->fetchOne(
                'INSERT INTO huni_surumleri (app, surum, tanim)
                 SELECT :app, coalesce(max(surum), 0) + 1, CAST(:t AS jsonb) FROM huni_surumleri WHERE app = :app
                 RETURNING surum',
                ['app' => $app, 't' => json_encode($tanim, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)],
            );

            return self::sayi($surum);
        });
    }

    /**
     * Yayınlanmış sürümler, en yenisi önce; her biriyle açılan ziyaret sayısı.
     *
     * @return list<array{surum: int, zaman: string, ziyaret: int}>
     */
    public function surumler(string $app): array
    {
        return array_map(static fn (array $s): array => [
            'surum' => self::sayi($s['surum']),
            'zaman' => \is_string($s['created_at']) ? $s['created_at'] : '',
            'ziyaret' => self::sayi($s['ziyaret']),
        ], $this->db->fetchAllAssociative(
            'SELECT s.surum, s.created_at,
                    (SELECT count(*) FROM huni_ziyaretleri z WHERE z.app = s.app AND z.surum = s.surum) AS ziyaret
             FROM huni_surumleri s WHERE s.app = :app ORDER BY s.surum DESC',
            ['app' => $app],
        ));
    }

    /** Taslak yayındaki son sürümden farklı mı (yayınlanmamış değişiklik). */
    public function taslakFarkli(string $app): bool
    {
        return true === $this->db->fetchOne(
            'SELECT h.taslak IS DISTINCT FROM (SELECT tanim FROM huni_surumleri s WHERE s.app = h.app ORDER BY surum DESC LIMIT 1)
             FROM huniler h WHERE h.app = :app',
            ['app' => $app],
        );
    }

    /** Eski bir sürümü taslağa kopyalar (geri alma = geri yükle + yeniden yayınla). */
    public function surumuTaslagaAl(string $app, int $surum): bool
    {
        return 1 === $this->db->executeStatement(
            'UPDATE huniler SET taslak = s.tanim, updated_at = now()
             FROM huni_surumleri s WHERE huniler.app = :app AND s.app = :app AND s.surum = :surum',
            ['app' => $app, 'surum' => $surum],
        );
    }

    /** Taslağı göstermek için süreli, imzalı anahtar (giriş gerektirmez). */
    public function onizlemeAnahtari(string $app, int $simdi): string
    {
        $son = $simdi + self::ONIZLEME_SN;

        return $son.'.'.hash_hmac('sha256', "onizleme\n{$app}\n{$son}", $this->sir);
    }

    public function anahtarGecerli(string $app, string $anahtar, int $simdi): bool
    {
        $parca = explode('.', $anahtar, 2);
        if (2 !== \count($parca) || !ctype_digit($parca[0]) || (int) $parca[0] < $simdi) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "onizleme\n{$app}\n{$parca[0]}", $this->sir), $parca[1]);
    }

    /** @return list<string> */
    private function hatalar(string $app, mixed $tanim): array
    {
        if (!isset($this->uygulamalar[$app])) {
            return ['bilinmeyen uygulama'];
        }

        return Tanim::hatalar($tanim, array_map(strval(...), array_keys($this->uygulamalar[$app])));
    }

    /** @return array<mixed> */
    private static function json(mixed $d): array
    {
        $j = \is_string($d) ? json_decode($d, true) : null;

        return \is_array($j) ? $j : [];
    }

    private static function sayi(mixed $d): int
    {
        return is_numeric($d) ? (int) $d : 0;
    }
}
