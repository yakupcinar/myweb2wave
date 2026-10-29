<?php

declare(strict_types=1);

namespace App\Huni;

use App\Medya\Medyalar;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hunilerin taslağı, yayınlanmış sürümleri, önizlemesi (A7) ve etikete göre
 * seçimi (A8). Bir uygulamanın birden çok hunisi var (app, ad); tam biri
 * varsayılan. Yayınlanan sürüm değişmez; huni sayfası en sonuncuyu okur.
 */
final readonly class Huniler
{
    /** Önizleme bağlantısının ömrü. */
    public const ONIZLEME_SN = 3600;

    /** Kuralların bakabildiği adres parametreleri — huni sayfası YALNIZ bunları gönderir. */
    public const SECIM_PARAMETRELERI = ['k', 'utm_source', 'utm_campaign', 'utm_content'];

    public function __construct(
        private Connection $db,
        private Medyalar $medyalar,
        /** @var array<string, array<string, string>> uygulama → plan → Stripe fiyatı */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
        #[Autowire('%kernel.secret%')]
        private string $sir,
    ) {
    }

    /**
     * Bu ziyarete hangi huni (A8): `huni` parametresi (doğrudan test adresi),
     * yoksa sıralı kurallardan ilk tutan, yoksa varsayılan. Yalnız yayınlanmış
     * huniler seçilir — yayınlanmamışı gösteren kural atlanır.
     *
     * @param array<string, string> $parametreler adresten; büyük/küçük harf duyarsız
     */
    public function sec(string $app, array $parametreler): ?string
    {
        $dogrudan = $parametreler['huni'] ?? null;
        if (null !== $dogrudan && $this->yayinlanmis($app, $dogrudan)) {
            return $dogrudan;
        }
        foreach ($this->kurallar($app) as $k) {
            $deger = $parametreler[$k['parametre']] ?? null;
            if (null !== $deger && mb_strtolower(trim($deger)) === $k['deger'] && $this->yayinlanmis($app, $k['huni'])) {
                return $k['huni'];
            }
        }
        $v = $this->db->fetchOne('SELECT ad FROM huniler WHERE app = :app AND varsayilan', ['app' => $app]);

        return \is_string($v) && $this->yayinlanmis($app, $v) ? $v : null;
    }

    /** @return array{surum: int, tanim: array<mixed>}|null */
    public function yayinda(string $app, string $ad): ?array
    {
        $s = $this->db->fetchAssociative(
            'SELECT surum, tanim FROM huni_surumleri WHERE app = :app AND ad = :ad ORDER BY surum DESC LIMIT 1',
            ['app' => $app, 'ad' => $ad],
        );

        return false === $s ? null : ['surum' => self::sayi($s['surum']), 'tanim' => self::json($s['tanim'])];
    }

    public const CAKISMA = 'Bu taslak başka bir sekmede ya da başka biri tarafından değiştirildi — sayfayı yenileyip yeniden düzenleyin.';

    /** Veritabanındaki taslak metninin SHA-256'sı (JSONB metni kararlı). */
    private const IMZA = "encode(sha256(convert_to(taslak::text, 'UTF8')), 'hex')";

    /** @return array<mixed>|null */
    public function taslak(string $app, string $ad): ?array
    {
        // Ç41: arşivdeki huni düzenlenmez, kural almaz, kopya kaynağı olmaz.
        $t = $this->db->fetchOne('SELECT taslak FROM huniler WHERE app = :app AND ad = :ad AND arsiv_at IS NULL', ['app' => $app, 'ad' => $ad]);

        return false === $t ? null : self::json($t);
    }

    /**
     * Uygulamanın hunileri: varsayılan önce, sonra ada göre.
     *
     * @return list<array{ad: string, varsayilan: bool, yayinda: int|null, farkli: bool, ziyaretli: bool}>
     */
    public function huniler(string $app): array
    {
        return array_map(static fn (array $s): array => [
            'ad' => \is_string($s['ad']) ? $s['ad'] : '',
            'varsayilan' => true === $s['varsayilan'],
            'yayinda' => null === $s['yayinda'] ? null : self::sayi($s['yayinda']),
            'farkli' => true === $s['farkli'],
            // Ç41: ziyaret varsa panel "Arşivle", yoksa "Sil" der.
            'ziyaretli' => true === $s['ziyaretli'],
        ], $this->db->fetchAllAssociative(
            'SELECT h.ad, h.varsayilan,
                    EXISTS (SELECT 1 FROM huni_ziyaretleri z WHERE z.app = h.app AND z.huni = h.ad) AS ziyaretli,
                    (SELECT max(surum) FROM huni_surumleri s WHERE s.app = h.app AND s.ad = h.ad) AS yayinda,
                    h.taslak IS DISTINCT FROM (SELECT tanim FROM huni_surumleri s WHERE s.app = h.app AND s.ad = h.ad ORDER BY surum DESC LIMIT 1) AS farkli
             FROM huniler h WHERE h.app = :app AND h.arsiv_at IS NULL ORDER BY h.varsayilan DESC, h.ad',
            ['app' => $app],
        ));
    }

    /**
     * Yeni huni: taslak, varsa `kaynak` hunisinin taslağından kopya, yoksa boş
     * şablon. Uygulamanın ilk hunisi varsayılan olur.
     *
     * @return list<string> hatalar
     */
    public function yeniHuni(string $app, string $ad, ?string $kaynak): array
    {
        if (!isset($this->uygulamalar[$app])) {
            return ['bilinmeyen uygulama'];
        }
        if (1 !== preg_match(Tanim::KIMLIK, $ad)) {
            return ['huni adı küçük harf, rakam, - ve _ (1–64)'];
        }
        $taslak = null === $kaynak ? $this->bosTaslak($app) : $this->taslak($app, $kaynak);
        if (null === $taslak) {
            return ["kaynak huni '{$kaynak}' yok"];
        }
        try {
            $this->db->executeStatement(
                'INSERT INTO huniler (app, ad, taslak, varsayilan)
                 VALUES (:app, :ad, CAST(:t AS jsonb), NOT EXISTS (SELECT 1 FROM huniler WHERE app = :app))',
                ['app' => $app, 'ad' => $ad, 't' => self::kodla($taslak)],
            );
        } catch (UniqueConstraintViolationException) {
            return ["'{$ad}' adında huni zaten var"];
        }

        return [];
    }

    /**
     * Ç41: huniyi panelden kaldırır. Ziyareti yoksa iz bırakmadan siler
     * (sürümleri, kuralları); varsa arşivler — rapor ziyaretin sürümüne
     * bağlı, silinemez. Her iki durumda kuralları kalkar. Varsayılan
     * kaldırılamaz (hiçbir kural tutmayınca açılacak huni kalmazdı).
     *
     * @return 'silindi'|'arsivlendi'|string sonuç ya da hata
     */
    public function kaldir(string $app, string $ad): string
    {
        return $this->db->transactional(function (Connection $db) use ($app, $ad): string {
            // fetchOne olmaz: varsayilan=false ile "satır yok" ikisi de false döner.
            $huni = $db->fetchAssociative('SELECT varsayilan FROM huniler WHERE app = :app AND ad = :ad AND arsiv_at IS NULL FOR UPDATE', ['app' => $app, 'ad' => $ad]);
            if (false === $huni) {
                return "'{$ad}' yok ya da arşivde";
            }
            if (true === $huni['varsayilan']) {
                return 'varsayılan huni kaldırılamaz — önce başka bir huniyi varsayılan yap';
            }
            $p = ['app' => $app, 'ad' => $ad];
            $db->executeStatement('DELETE FROM huni_kurallari WHERE app = :app AND huni = :ad', $p);
            if (false !== $db->fetchOne('SELECT 1 FROM huni_ziyaretleri WHERE app = :app AND huni = :ad LIMIT 1', $p)) {
                $db->executeStatement('UPDATE huniler SET arsiv_at = now() WHERE app = :app AND ad = :ad', $p);

                return 'arsivlendi';
            }
            $db->executeStatement('DELETE FROM huni_surumleri WHERE app = :app AND ad = :ad', $p);
            $db->executeStatement('DELETE FROM huniler WHERE app = :app AND ad = :ad', $p);

            return 'silindi';
        });
    }

    public function varsayilanYap(string $app, string $ad): bool
    {
        return $this->db->transactional(function (Connection $db) use ($app, $ad): bool {
            if (false === $db->fetchOne('SELECT 1 FROM huniler WHERE app = :app AND ad = :ad AND arsiv_at IS NULL', ['app' => $app, 'ad' => $ad])) {
                return false;
            }
            // Önce hepsini kaldır: kısmi tekil dizin aynı anda iki varsayılanı reddeder.
            $db->executeStatement('UPDATE huniler SET varsayilan = false WHERE app = :app AND varsayilan', ['app' => $app]);
            $db->executeStatement('UPDATE huniler SET varsayilan = true WHERE app = :app AND ad = :ad', ['app' => $app, 'ad' => $ad]);

            return true;
        });
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function taslakKaydet(string $app, string $ad, mixed $tanim, ?string $beklenenImza = null): array
    {
        $hatalar = $this->hatalar($app, $tanim);
        if ([] !== $hatalar) {
            return $hatalar;
        }
        // Ç39: imza verildiyse karşılaştırma AYNI UPDATE'te — okuyup sonra
        // yazmak iki kaydı arasına üçüncüsünü sokardı.
        $n = $this->db->executeStatement(
            'UPDATE huniler SET taslak = CAST(:t AS jsonb), updated_at = now()
             WHERE app = :app AND ad = :ad AND (CAST(:imza AS text) IS NULL OR '.self::IMZA.' = :imza)',
            ['app' => $app, 'ad' => $ad, 't' => self::kodla($tanim), 'imza' => $beklenenImza],
        );
        if (1 === $n) {
            return [];
        }

        return null === $this->taslakImzasi($app, $ad) ? ["'{$ad}' adında huni yok"] : [self::CAKISMA];
    }

    /** Ç39: taslağın imzası — düzenleyici açılışta alır, kayıtta geri yollar. */
    public function taslakImzasi(string $app, string $ad): ?string
    {
        $imza = $this->db->fetchOne('SELECT '.self::IMZA.' FROM huniler WHERE app = :app AND ad = :ad', ['app' => $app, 'ad' => $ad]);

        return \is_string($imza) ? $imza : null;
    }

    /**
     * Taslağı yeni, değişmez sürüm olarak yayınlar. Taslak geçersizse
     * (ör. plan yapılandırmadan kalktı) ya da yayındakiyle aynıysa yayınlamaz.
     *
     * @return int|list<string> yeni sürüm ya da hatalar
     */
    public function yayinla(string $app, string $ad): int|array
    {
        return $this->db->transactional(function (Connection $db) use ($app, $ad): int|array {
            // Aynı anda iki yayın aynı sürüm numarasını almasın.
            $t = $db->fetchOne('SELECT taslak FROM huniler WHERE app = :app AND ad = :ad FOR UPDATE', ['app' => $app, 'ad' => $ad]);
            if (false === $t) {
                return ["'{$ad}' adında huni yok"];
            }
            $tanim = self::json($t);
            $hatalar = $this->hatalar($app, $tanim);
            if ([] !== $hatalar) {
                return $hatalar;
            }
            if (!$this->taslakFarkli($app, $ad)) {
                return ['taslak yayındaki sürümle aynı — yayınlanacak değişiklik yok'];
            }
            $surum = $db->fetchOne(
                'INSERT INTO huni_surumleri (app, ad, surum, tanim)
                 SELECT :app, :ad, coalesce(max(surum), 0) + 1, CAST(:t AS jsonb) FROM huni_surumleri WHERE app = :app AND ad = :ad
                 RETURNING surum',
                ['app' => $app, 'ad' => $ad, 't' => self::kodla($tanim)],
            );

            return self::sayi($surum);
        });
    }

    /**
     * Yayınlanmış sürümler, en yenisi önce; her biriyle açılan ziyaret sayısı.
     *
     * @return list<array{surum: int, zaman: string, ziyaret: int}>
     */
    public function surumler(string $app, string $ad): array
    {
        return array_map(static fn (array $s): array => [
            'surum' => self::sayi($s['surum']),
            'zaman' => \is_string($s['created_at']) ? $s['created_at'] : '',
            'ziyaret' => self::sayi($s['ziyaret']),
        ], $this->db->fetchAllAssociative(
            'SELECT s.surum, s.created_at,
                    (SELECT count(*) FROM huni_ziyaretleri z WHERE z.app = s.app AND z.huni = s.ad AND z.surum = s.surum) AS ziyaret
             FROM huni_surumleri s WHERE s.app = :app AND s.ad = :ad ORDER BY s.surum DESC',
            ['app' => $app, 'ad' => $ad],
        ));
    }

    /** Taslak yayındaki son sürümden farklı mı (yayınlanmamış değişiklik). */
    public function taslakFarkli(string $app, string $ad): bool
    {
        return true === $this->db->fetchOne(
            'SELECT h.taslak IS DISTINCT FROM (SELECT tanim FROM huni_surumleri s WHERE s.app = h.app AND s.ad = h.ad ORDER BY surum DESC LIMIT 1)
             FROM huniler h WHERE h.app = :app AND h.ad = :ad',
            ['app' => $app, 'ad' => $ad],
        );
    }

    /** Eski bir sürümü taslağa kopyalar (geri alma = geri yükle + yeniden yayınla). */
    public function surumuTaslagaAl(string $app, string $ad, int $surum): bool
    {
        return 1 === $this->db->executeStatement(
            'UPDATE huniler SET taslak = s.tanim, updated_at = now()
             FROM huni_surumleri s
             WHERE huniler.app = :app AND huniler.ad = :ad AND s.app = :app AND s.ad = :ad AND s.surum = :surum',
            ['app' => $app, 'ad' => $ad, 'surum' => $surum],
        );
    }

    /**
     * Yönlendirme kuralları, sıraya göre.
     *
     * @return list<array{id: int, sira: int, parametre: string, deger: string, huni: string}>
     */
    public function kurallar(string $app): array
    {
        return array_map(static fn (array $s): array => [
            'id' => self::sayi($s['id']),
            'sira' => self::sayi($s['sira']),
            'parametre' => \is_string($s['parametre']) ? $s['parametre'] : '',
            'deger' => \is_string($s['deger']) ? $s['deger'] : '',
            'huni' => \is_string($s['huni']) ? $s['huni'] : '',
        ], $this->db->fetchAllAssociative(
            'SELECT id, sira, parametre, deger, huni FROM huni_kurallari WHERE app = :app ORDER BY sira, id',
            ['app' => $app],
        ));
    }

    /** @return list<string> hatalar */
    public function kuralEkle(string $app, string $parametre, string $deger, string $huni): array
    {
        $deger = mb_strtolower(trim($deger));
        if (!\in_array($parametre, self::SECIM_PARAMETRELERI, true)) {
            return ['parametre '.implode(', ', self::SECIM_PARAMETRELERI).' olmalı'];
        }
        if ('' === $deger || mb_strlen($deger) > 200) {
            return ['değer 1–200 karakter olmalı'];
        }
        if (null === $this->taslak($app, $huni)) {
            return ["'{$huni}' adında huni yok"];
        }
        try {
            $this->db->executeStatement(
                'INSERT INTO huni_kurallari (app, sira, parametre, deger, huni)
                 SELECT :app, coalesce(max(sira), 0) + 1, :p, :d, :h FROM huni_kurallari WHERE app = :app',
                ['app' => $app, 'p' => $parametre, 'd' => $deger, 'h' => $huni],
            );
        } catch (UniqueConstraintViolationException) {
            return ["{$parametre} = {$deger} için zaten bir kural var"];
        }

        return [];
    }

    public function kuralSil(string $app, int $id): bool
    {
        return 1 === $this->db->executeStatement('DELETE FROM huni_kurallari WHERE app = :app AND id = :id', ['app' => $app, 'id' => $id]);
    }

    /** Kuralı bir üste ya da alta taşır (komşusuyla sırasını değiştirir). */
    public function kuralTasi(string $app, int $id, int $yon): bool
    {
        $liste = $this->kurallar($app);
        $i = array_search($id, array_column($liste, 'id'), true);
        if (false === $i) {
            return false;
        }
        $j = $i + ($yon < 0 ? -1 : 1);
        if (!isset($liste[$j])) {
            return false;
        }
        $this->db->transactional(function (Connection $db) use ($liste, $i, $j): void {
            // Sıralar yeniden numaralanır; iki kural aynı sırayı paylaşmaz.
            [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
            foreach ($liste as $n => $k) {
                $db->executeStatement('UPDATE huni_kurallari SET sira = :s WHERE id = :id', ['s' => $n + 1, 'id' => $k['id']]);
            }
        });

        return true;
    }

    /** Taslağı göstermek için süreli, imzalı anahtar (giriş gerektirmez); uygulamaya ve huniye bağlı. */
    public function onizlemeAnahtari(string $app, string $ad, int $simdi): string
    {
        $son = $simdi + self::ONIZLEME_SN;

        return $son.'.'.hash_hmac('sha256', "onizleme\n{$app}\n{$ad}\n{$son}", $this->sir);
    }

    public function anahtarGecerli(string $app, string $ad, string $anahtar, int $simdi): bool
    {
        $parca = explode('.', $anahtar, 2);
        if (2 !== \count($parca) || !ctype_digit($parca[0]) || (int) $parca[0] < $simdi) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "onizleme\n{$app}\n{$ad}\n{$parca[0]}", $this->sir), $parca[1]);
    }

    /**
     * Boş başlangıç taslağı (doğrulamadan geçmez — metinler doldurulmalı).
     *
     * @return array<string, mixed>
     */
    public function bosTaslak(string $app): array
    {
        $plan = (string) (array_key_first($this->uygulamalar[$app] ?? []) ?? 'monthly');

        return ['sayfaBasligi' => '', 'adimlar' => [
            ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => '', 'metin' => '', 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => '', 'metin' => '', 'dugme' => 'Abone ol', 'plan' => $plan],
        ]];
    }

    private function yayinlanmis(string $app, string $ad): bool
    {
        return false !== $this->db->fetchOne(
            'SELECT 1 FROM huni_surumleri s JOIN huniler h ON h.app = s.app AND h.ad = s.ad
             WHERE s.app = :app AND s.ad = :ad AND h.arsiv_at IS NULL LIMIT 1',
            ['app' => $app, 'ad' => $ad],
        );
    }

    /**
     * Tanımın bu uygulamadaki tam doğrulaması: biçim + plan + görsel sahipliği.
     * Kaydetmeden önce ölçmek isteyen (AI aracı) de bunu kullanır.
     *
     * @return list<string>
     */
    public function hatalar(string $app, mixed $tanim): array
    {
        if (!isset($this->uygulamalar[$app])) {
            return ['bilinmeyen uygulama'];
        }

        $hatalar = Tanim::hatalar($tanim, array_map(strval(...), array_keys($this->uygulamalar[$app])));
        // E2.3: görsel bu uygulamaya yüklenmiş olmalı (başka uygulamanınki ya da silinmiş değil).
        foreach ($this->medyalar->eksikler($app, Tanim::gorseller($tanim)) as $id) {
            $hatalar[] = 'görsel '.substr($id, 0, 8).'… bu uygulamada yok';
        }

        return $hatalar;
    }

    private static function kodla(mixed $tanim): string
    {
        return json_encode($tanim, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
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
