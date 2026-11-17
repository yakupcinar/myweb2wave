<?php

declare(strict_types=1);

namespace App\Posta;

use App\Uygulama\UygulamaAyarlari;
use App\Yasal\Taslaklar;
use Doctrine\DBAL\Connection;

/**
 * E1a: müşteriye giden e-postaların konusu ve metni — uygulama × şablon ×
 * dil. Kayıt yoksa VARSAYILANLAR (tr bugünkü metinler; en karşılığı); e-posta
 * her durumda gider.
 *
 * - Yer tutucular ({{baglanti}}…) gönderimde doldurulur; şablonun izinli
 *   listesi dışındaki yer tutucu kaydedilmez (yazım hatası müşteriye ham
 *   gitmesin), ZORUNLU olanlar eksikse de kaydedilmez (kodsuz doğrulama
 *   e-postası, bağlantısız giriş e-postası işe yaramaz).
 * - Düz metin; HTML yok (gönderim ->text()).
 * - Diller yasal metinlerinkiyle aynı (Taslaklar::DILLER).
 */
final readonly class EpostaSablonlari
{
    public const AZAMI_KONU = 200;
    public const AZAMI_METIN = 5000;

    /** şablon => [panel adı, açıklama, izinli yer tutucular, zorunlu] */
    public const SABLONLAR = [
        'dogrulama_kodu' => ['Doğrulama kodu', 'Uygulamada "Aboneliğimi bağla" ile istenen 6 haneli kod.', ['kod', 'dakika', 'uygulama'], ['kod']],
        'giris_baglantisi' => ['Giriş bağlantısı', 'Ödeme tamamlanınca: uygulamayı açan tek kullanımlık bağlantı.', ['baglanti', 'saat', 'uygulama'], ['baglanti']],
        'portal_baglantisi' => ['Aboneliği yönet', 'Kart, iptal ve faturalar için tek kullanımlık bağlantı.', ['baglanti', 'dakika', 'uygulama'], ['baglanti']],
        'aktivasyon_1' => ['Aktivasyon 1', 'Ödeyip uygulamaya bağlanmayana ilk hatırlatma.', ['baglanti', 'magaza', 'saat', 'uygulama'], ['baglanti']],
        'aktivasyon_2' => ['Aktivasyon 2', 'Hâlâ bağlanmayana ikinci hatırlatma.', ['baglanti', 'magaza', 'saat', 'uygulama'], ['baglanti']],
        'kurtarma_1' => ['Kurtarma 1', 'Ödemeyi yarıda bırakana ilk e-posta (izinliyse).', ['baglanti', 'uygulama'], ['baglanti']],
        'kurtarma_2' => ['Kurtarma 2', 'Ödemeyi yarıda bırakana ikinci e-posta (izinliyse).', ['baglanti', 'uygulama'], ['baglanti']],
    ];

    private const AKTIVASYON_TR = "Aboneliğiniz etkin, ama uygulamaya henüz bağlanmadınız.\n\n1. Uygulamayı telefonunuza kurun:\n{{magaza}}\n2. Bu bağlantıya telefonunuzda dokunun:\n{{baglanti}}\n\nBağlantı tek kullanımlıktır ve {{saat}} saat geçerlidir. Süresi dolarsa uygulamada \"Aboneliğimi bağla\" ile bu e-posta adresini girin.\n\nBu e-postayı aboneliğiniz nedeniyle alıyorsunuz; bağlandığınızda bir daha gönderilmez.";
    private const AKTIVASYON_EN = "Your subscription is active, but you haven't connected the app yet.\n\n1. Install the app on your phone:\n{{magaza}}\n2. Tap this link on your phone:\n{{baglanti}}\n\nThe link works once and is valid for {{saat}} hours. If it expires, use \"Link my subscription\" in the app with this email address.\n\nYou're receiving this because of your subscription; it stops once you connect.";
    private const KURTARMA_TR = "Ödeme sayfasında kaldınız. Kaldığınız yerden devam etmek için:\n{{baglanti}}\n\nBağlantı 30 gün geçerlidir; seçtiğiniz plan ve tutar aynı kalır.\n\nBu e-postayı, e-posta almayı kabul ettiğiniz için alıyorsunuz.";
    private const KURTARMA_EN = "You stopped at the checkout page. To continue where you left off:\n{{baglanti}}\n\nThe link is valid for 30 days; your plan and price stay the same.\n\nYou're receiving this because you agreed to receive emails.";

    /** şablon => dil => [konu, metin] */
    public const VARSAYILANLAR = [
        'dogrulama_kodu' => [
            'tr' => ['Doğrulama kodunuz', "Kodunuz: {{kod}}\n\nKod {{dakika}} dakika geçerlidir. Bu isteği siz yapmadıysanız bu e-postayı yok sayın."],
            'en' => ['Your verification code', "Your code: {{kod}}\n\nThe code is valid for {{dakika}} minutes. If you didn't request it, ignore this email."],
        ],
        'giris_baglantisi' => [
            'tr' => ['Uygulamaya giriş bağlantınız', "Aboneliğiniz için teşekkürler.\n\nUygulamayı telefonunuza kurduktan sonra bu bağlantıya telefonunuzda dokunun:\n{{baglanti}}\n\nBağlantı tek kullanımlıktır ve {{saat}} saat geçerlidir. Süresi dolarsa uygulamada \"Aboneliğimi bağla\" ile bu e-posta adresini girin."],
            'en' => ['Your sign-in link', "Thanks for subscribing.\n\nAfter installing the app on your phone, tap this link on your phone:\n{{baglanti}}\n\nThe link works once and is valid for {{saat}} hours. If it expires, use \"Link my subscription\" in the app with this email address."],
        ],
        'portal_baglantisi' => [
            'tr' => ['Aboneliğinizi yönetin', "Aboneliğinizi yönetmek (kart, iptal, faturalar) için bağlantı:\n{{baglanti}}\n\nBağlantı tek kullanımlıktır ve {{dakika}} dakika geçerlidir. Bu isteği siz yapmadıysanız e-postayı yok sayın."],
            'en' => ['Manage your subscription', "Link to manage your subscription (card, cancellation, invoices):\n{{baglanti}}\n\nThe link works once and is valid for {{dakika}} minutes. If you didn't request it, ignore this email."],
        ],
        'aktivasyon_1' => ['tr' => ['Aboneliğiniz hazır — uygulamaya bağlanın', self::AKTIVASYON_TR], 'en' => ['Your subscription is ready — connect the app', self::AKTIVASYON_EN]],
        'aktivasyon_2' => ['tr' => ['Aboneliğinizi henüz kullanmadınız', self::AKTIVASYON_TR], 'en' => ["You haven't used your subscription yet", self::AKTIVASYON_EN]],
        'kurtarma_1' => ['tr' => ['Ödemeniz yarım kaldı', self::KURTARMA_TR], 'en' => ['Your payment is unfinished', self::KURTARMA_EN]],
        'kurtarma_2' => ['tr' => ['Aboneliğiniz hâlâ sizi bekliyor', self::KURTARMA_TR], 'en' => ['Your subscription is still waiting for you', self::KURTARMA_EN]],
    ];

    public function __construct(private Connection $db, private UygulamaAyarlari $ayarlar)
    {
    }

    /**
     * Gönderim için: kayıtlı (yoksa varsayılan) şablon, yer tutucular dolu.
     * {{uygulama}} ayarlardaki görünen ad.
     *
     * @param array<string, string|int> $degerler
     *
     * @return array{konu: string, metin: string}
     */
    public function doldur(string $app, string $sablon, string $dil, array $degerler): array
    {
        $dil = \in_array($dil, Taslaklar::DILLER, true) ? $dil : 'tr';
        [$konu, $metin] = $this->ham($app, $sablon, $dil);
        $tum = $degerler + ['uygulama' => $this->ayarlar->oku($app)['ad']];
        $yaz = static fn (string $m): string => (string) preg_replace_callback('/\{\{(\w+)\}\}/', static fn (array $e): string => (string) ($tum[$e[1]] ?? $e[0]), $m);

        return ['konu' => $yaz($konu), 'metin' => $yaz($metin)];
    }

    /**
     * Panel için: her şablon × dil — kayıtlıysa o, değilse varsayılan.
     *
     * @return list<array{sablon: string, dil: string, ad: string, aciklama: string, konu: string, metin: string, kayitli: bool, guncellendi: string|null, yerTutucular: list<string>, zorunlu: list<string>}>
     */
    public function liste(string $app): array
    {
        $kayitli = [];
        foreach ($this->db->fetchAllAssociative('SELECT sablon, dil, konu, metin, updated_at FROM eposta_sablonlari WHERE app = ?', [$app]) as $r) {
            $kayitli[self::dizge($r['sablon']).'/'.self::dizge($r['dil'])] = $r;
        }
        $liste = [];
        foreach (self::SABLONLAR as $sablon => [$ad, $aciklama, $yerTutucular, $zorunlu]) {
            foreach (Taslaklar::DILLER as $dil) {
                $r = $kayitli["{$sablon}/{$dil}"] ?? null;
                [$konu, $metin] = self::VARSAYILANLAR[$sablon][$dil];
                $liste[] = [
                    'sablon' => $sablon, 'dil' => $dil, 'ad' => $ad, 'aciklama' => $aciklama,
                    'konu' => null !== $r ? self::dizge($r['konu']) : $konu,
                    'metin' => null !== $r ? self::dizge($r['metin']) : $metin,
                    'kayitli' => null !== $r,
                    'guncellendi' => null !== $r ? (new \DateTimeImmutable(self::dizge($r['updated_at'])))->format(\DATE_ATOM) : null,
                    'yerTutucular' => $yerTutucular, 'zorunlu' => $zorunlu,
                ];
            }
        }

        return $liste;
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function kaydet(string $app, string $sablon, string $dil, mixed $veri): array
    {
        if (!\is_array($veri) || [] !== array_diff(array_map(strval(...), array_keys($veri)), ['konu', 'metin'])
            || !\is_string($veri['konu'] ?? null) || !\is_string($veri['metin'] ?? null)) {
            return ['gövde yalnız konu ve metin alanları olan bir nesne olmalı'];
        }
        $konu = trim($veri['konu']);
        $metin = trim(str_replace("\r\n", "\n", $veri['metin']));
        $h = [];
        if ('' === $konu || mb_strlen($konu) > self::AZAMI_KONU || str_contains($konu, "\n")) {
            $h[] = 'konu: 1–'.self::AZAMI_KONU.' karakter, tek satır';
        }
        if ('' === $metin || mb_strlen($metin) > self::AZAMI_METIN) {
            $h[] = 'metin: 1–'.self::AZAMI_METIN.' karakter';
        }
        [, , $izinli, $zorunlu] = self::SABLONLAR[$sablon];
        $kullanilan = array_unique([...self::yerTutucular($konu), ...self::yerTutucular($metin)]);
        if ([] !== $bilinmeyen = array_values(array_diff($kullanilan, $izinli))) {
            $h[] = 'bilinmeyen yer tutucu: '.implode(', ', array_map(static fn (string $y): string => '{{'.$y.'}}', $bilinmeyen));
        }
        if ([] !== $eksik = array_values(array_diff($zorunlu, self::yerTutucular($metin)))) {
            $h[] = 'metinde olmalı: '.implode(', ', array_map(static fn (string $y): string => '{{'.$y.'}}', $eksik));
        }
        if ([] !== $h) {
            return $h;
        }
        $this->db->executeStatement(
            'INSERT INTO eposta_sablonlari (app, sablon, dil, konu, metin) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (app, sablon, dil) DO UPDATE SET konu = EXCLUDED.konu, metin = EXCLUDED.metin, updated_at = now()',
            [$app, $sablon, $dil, $konu, $metin],
        );

        return [];
    }

    /** Varsayılana dön; kayıt yoksa false. */
    public function sil(string $app, string $sablon, string $dil): bool
    {
        return 1 === $this->db->executeStatement('DELETE FROM eposta_sablonlari WHERE app = ? AND sablon = ? AND dil = ?', [$app, $sablon, $dil]);
    }

    /**
     * Ödeme oturumunun ziyaretindeki huninin dili (M9d2); bilinmiyorsa tr.
     * Giriş, aktivasyon ve kurtarma e-postaları müşterinin gördüğü dilde.
     */
    public static function oturumDili(Connection $db, string $oturum): string
    {
        $dil = $db->fetchOne(
            "SELECT s.tanim->>'dil' FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
             JOIN huni_surumleri s ON s.app = z.app AND s.ad = z.huni AND s.surum = z.surum
             WHERE o.stripe_checkout_session_id = ?",
            [$oturum],
        );

        return \is_string($dil) && \in_array($dil, Taslaklar::DILLER, true) ? $dil : 'tr';
    }

    /** @return array{string, string} */
    private function ham(string $app, string $sablon, string $dil): array
    {
        $r = $this->db->fetchAssociative('SELECT konu, metin FROM eposta_sablonlari WHERE app = ? AND sablon = ? AND dil = ?', [$app, $sablon, $dil]);

        return false !== $r ? [self::dizge($r['konu']), self::dizge($r['metin'])] : self::VARSAYILANLAR[$sablon][$dil];
    }

    /** @return list<string> */
    private static function yerTutucular(string $metin): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $metin, $m);

        return array_values(array_unique($m[1]));
    }

    private static function dizge(mixed $d): string
    {
        return \is_string($d) ? $d : '';
    }
}
