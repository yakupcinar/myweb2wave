<?php

declare(strict_types=1);

namespace App\Panel;

use App\Huni\Tanim;
use Symfony\Component\HttpFoundation\Request;

/**
 * R1a: raporun bütün sorgularına aynı süzgeç — ziyaret (`z`) üzerinden
 * uygulama, huni, kaynak (Rapor::KAYNAK ile aynı tanım: "doğrudan",
 * "meta (fbclid)" da seçilebilir), kampanya, ülke, deney (+ varyant). Boş alan
 * süzmez. Değerler parametre olarak gider; SQL yalnız sabit parçalardan.
 */
final readonly class RaporSuzgeci
{
    /** Panelin gönderdiği adres parametreleri (sözleşme RaporSuzgeci). */
    public const ALANLAR = ['huni', 'kaynak', 'kampanya', 'ulke', 'deney', 'varyant'];

    public function __construct(
        public ?string $app = null,
        public ?string $huni = null,
        public ?string $kaynak = null,
        public ?string $kampanya = null,
        public ?string $ulke = null,
        public ?string $deney = null,
        public ?string $varyant = null,
    ) {
    }

    /** MCP ve eski çağıranlar: yalnız uygulama süzgeci. */
    public static function uygulama(?string $app): self
    {
        return new self($app);
    }

    /**
     * Panel adresinden; biçimsiz değer açık hata (sessizce süzmemek, yanlış
     * sayıyı "süzülmüş" sanmaktan iyidir).
     *
     * @return self|list<string>
     */
    public static function istekten(Request $request): self|array
    {
        $d = [];
        $hatalar = [];
        foreach (self::ALANLAR as $ad) {
            $v = $request->query->getString($ad);
            if ('' === $v) {
                $d[$ad] = null;
                continue;
            }
            $gecerli = match ($ad) {
                'huni', 'deney' => 1 === preg_match(Tanim::KIMLIK, $v),
                'ulke' => 1 === preg_match('/^[A-Z]{2}$/', $v),
                default => mb_strlen($v) <= 200,
            };
            if (!$gecerli) {
                $hatalar[] = "{$ad} geçersiz";
            }
            $d[$ad] = $v;
        }
        if (null !== $d['varyant'] && null === $d['deney']) {
            $hatalar[] = 'varyant deneysiz olmaz';
        }

        return [] !== $hatalar ? $hatalar : new self(null, $d['huni'], $d['kaynak'], $d['kampanya'], $d['ulke'], $d['deney'], $d['varyant']);
    }

    /** SQL koşulu (" AND …"), ziyaret takma adı `z`; süzgeç yoksa boş. */
    public function kosul(): string
    {
        $k = '';
        if (null !== $this->app) {
            $k .= ' AND z.app = :s_app';
        }
        if (null !== $this->huni) {
            $k .= ' AND z.huni = :s_huni';
        }
        if (null !== $this->kaynak) {
            $k .= ' AND '.Rapor::KAYNAK.' = :s_kaynak';
        }
        if (null !== $this->kampanya) {
            $k .= ' AND z.utm_campaign = :s_kampanya';
        }
        if (null !== $this->ulke) {
            $k .= ' AND z.ulke = :s_ulke';
        }
        if (null !== $this->deney) {
            $k .= ' AND EXISTS (SELECT 1 FROM ziyaret_deneyleri zd WHERE zd.ziyaret_id = z.id AND zd.deney = :s_deney'
                .(null !== $this->varyant ? ' AND zd.varyant = :s_varyant' : '').')';
        }

        return $k;
    }

    /** @return array<string, string> */
    public function parametreler(): array
    {
        return array_filter([
            's_app' => $this->app, 's_huni' => $this->huni, 's_kaynak' => $this->kaynak, 's_kampanya' => $this->kampanya,
            's_ulke' => $this->ulke, 's_deney' => $this->deney, 's_varyant' => null !== $this->deney ? $this->varyant : null,
        ], static fn (?string $v): bool => null !== $v);
    }
}
