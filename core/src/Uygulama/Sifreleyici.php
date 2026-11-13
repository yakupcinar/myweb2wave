<?php

declare(strict_types=1);

namespace App\Uygulama;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A30: panelden girilen dış servis anahtarları (Meta) veritabanında şifreli
 * durur — DB yedeği sızsa da okunmaz. sodium secretbox; anahtar ortamda
 * (AYAR_SIFRELEME_ANAHTARI, 32 bayt base64). Anahtar yoksa ya da değişmişse
 * şifreli değer okunamaz (null) — kayıt silinmez, panelde yeniden girilir.
 */
final readonly class Sifreleyici
{
    public function __construct(
        #[Autowire('%env(AYAR_SIFRELEME_ANAHTARI)%')]
        private string $anahtar,
    ) {
    }

    public function hazir(): bool
    {
        return null !== $this->ham();
    }

    /** Anahtar yoksa çağrılmaz (hazir()); çağrılırsa hata. */
    public function sifrele(string $acik): string
    {
        $anahtar = $this->ham() ?? throw new \LogicException('AYAR_SIFRELEME_ANAHTARI yok ya da 32 bayt değil');
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($acik, $nonce, $anahtar));
    }

    public function coz(string $kapali): ?string
    {
        $anahtar = $this->ham();
        $ham = base64_decode($kapali, true);
        if (null === $anahtar || false === $ham || \strlen($ham) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $acik = sodium_crypto_secretbox_open(substr($ham, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($ham, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $anahtar);

        return false === $acik ? null : $acik;
    }

    private function ham(): ?string
    {
        $a = base64_decode($this->anahtar, true);

        return false !== $a && \SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($a) ? $a : null;
    }
}
