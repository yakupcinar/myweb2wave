<?php

declare(strict_types=1);

namespace App\Huni\Arac;

use App\Huni\Huniler;
use App\Huni\Tanim;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Huni araçları (A0) — panel AI sohbeti (A2) ve MCP sunucusu (A5) AYNI
 * tanımları ve aynı çalıştırıcıyı kullanır. Yalnız taslak: yayınlama,
 * silme, kural değiştirme aracı YOK (yayın insanda; araştırma:
 * docs/decisions/ai-asistan-arastirmasi.md). Doğrulama çekirdekte
 * (Huniler::hatalar); araç girdisi modelden gelir, güvenilmez.
 */
final readonly class HuniAraclari
{
    private const IMZA = '/^[0-9a-f]{64}$/';

    public function __construct(
        private Huniler $huniler,
        #[Autowire('%kernel.project_dir%/config/huni/tanim-semasi.json')]
        private string $semaDosyasi,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $funnelBaseUrl,
    ) {
    }

    /**
     * Araç tanımları (Claude API `tools` / MCP `tools/list` biçimi). Tanım
     * alanının şeması sözleşmeden üretilmiş dosyadan.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function tanimlar(AracKapsami $kapsam): array
    {
        $huni = ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,64}$', 'description' => 'Huni adı (küçük harf, rakam, - ve _)'];
        $tanim = ['$ref' => '#/$defs/'.HuniSemasi::KOK];
        $imza = ['type' => 'string', 'description' => 'huni_oku ya da son taslak_kaydet yanıtındaki imza (eşzamanlı düzenlemeyi ezmemek için)'];
        $nesne = static fn (array $alanlar, array $zorunlu): array => ['type' => 'object', 'properties' => (object) $alanlar, 'required' => $zorunlu, 'additionalProperties' => false];
        $araclar = [
            ['name' => 'huniler_listele', 'description' => "'{$kapsam->app}' uygulamasının hunileri: ad, varsayılan mı, yayındaki sürüm, yayınlanmamış değişiklik var mı.", 'input_schema' => $nesne([], [])],
            ['name' => 'huni_oku', 'description' => 'Hunin taslak tanımını ve imzasını döndürür. Değiştirmeden önce oku.', 'input_schema' => $nesne(['huni' => $huni], ['huni'])],
            ['name' => 'tanim_dogrula', 'description' => 'Tanımı KAYDETMEDEN doğrular; hataları Türkçe döndürür.', 'input_schema' => $this->semali($nesne(['tanim' => $tanim], ['tanim']))],
            ['name' => 'taslak_kaydet', 'description' => 'Tanımı hunin TASLAĞINA yazar (yayınlamaz; yayın insanda). Geçersizse yazmaz, hataları döndürür — düzeltip yeniden dene.', 'input_schema' => $this->semali($nesne(['huni' => $huni, 'tanim' => $tanim, 'imza' => $imza], ['huni', 'tanim', 'imza']))],
            ['name' => 'onizleme_adresi', 'description' => 'Taslağı görmek için süreli önizleme bağlantısı (1 saat).', 'input_schema' => $nesne(['huni' => $huni], ['huni'])],
        ];
        if (null === $kapsam->huni) {
            $araclar[] = ['name' => 'yeni_huni', 'description' => "'{$kapsam->app}' uygulamasında yeni huni açar (boş şablon ya da kaynak huninin taslağından kopya); taslağı sonra taslak_kaydet ile doldur.", 'input_schema' => $nesne(['huni' => $huni, 'kaynak' => ['description' => 'kopyalanacak huni (isteğe bağlı)'] + $huni], ['huni'])];
        }

        return $araclar;
    }

    public function calistir(string $ad, mixed $girdi, AracKapsami $kapsam): AracSonucu
    {
        if (!\is_array($girdi)) {
            return AracSonucu::hata('girdi bir nesne olmalı');
        }
        $tanimlar = array_column($this->tanimlar($kapsam), null, 'name');
        if (!isset($tanimlar[$ad])) {
            return AracSonucu::hata("bilinmeyen araç: {$ad}");
        }
        $sema = $tanimlar[$ad]['input_schema'];
        $izinli = array_keys((array) $sema['properties']);
        if ([] !== $fazla = array_diff(array_map(strval(...), array_keys($girdi)), $izinli)) {
            return AracSonucu::hata('bilinmeyen alan: '.implode(', ', $fazla));
        }
        /** @var list<string> $zorunlu */
        $zorunlu = $sema['required'];
        if ([] !== $eksik = array_diff($zorunlu, array_map(strval(...), array_keys($girdi)))) {
            return AracSonucu::hata('eksik alan: '.implode(', ', $eksik));
        }
        foreach (['huni', 'kaynak'] as $alan) {
            if (isset($girdi[$alan]) && (!\is_string($girdi[$alan]) || 1 !== preg_match(Tanim::KIMLIK, $girdi[$alan]))) {
                return AracSonucu::hata("{$alan}: küçük harf, rakam, - ve _ (1–64)");
            }
        }
        if (isset($girdi['huni']) && null !== $kapsam->huni && $girdi['huni'] !== $kapsam->huni) {
            return AracSonucu::hata("bu oturum yalnız '{$kapsam->huni}' hunisinde çalışır");
        }
        $app = $kapsam->app;
        /** @var string $huni */
        $huni = $girdi['huni'] ?? '';

        return match ($ad) {
            'huniler_listele' => new AracSonucu(['uygulama' => $app, 'huniler' => array_map(static fn (array $h): array => [
                'ad' => $h['ad'],
                'varsayilan' => $h['varsayilan'],
                'yayindaki_surum' => $h['yayinda'],
                'yayinlanmamis_degisiklik' => $h['farkli'],
            ], $this->huniler->huniler($app))]),
            'huni_oku' => null === ($taslak = $this->huniler->taslak($app, $huni))
                ? AracSonucu::hata("'{$huni}' adında huni yok")
                : new AracSonucu(['huni' => $huni, 'tanim' => $taslak, 'imza' => $this->huniler->taslakImzasi($app, $huni)]),
            'tanim_dogrula' => new AracSonucu(['gecerli' => [] === ($h = $this->huniler->hatalar($app, $girdi['tanim'])), 'hatalar' => $h]),
            'taslak_kaydet' => $this->kaydet($app, $huni, $girdi['tanim'], $girdi['imza']),
            'onizleme_adresi' => null === $this->huniler->taslak($app, $huni)
                ? AracSonucu::hata("'{$huni}' adında huni yok")
                : new AracSonucu(['adres' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$huni.'&onizleme='.$this->huniler->onizlemeAnahtari($app, $huni, time())]),
            'yeni_huni' => [] === ($h = $this->huniler->yeniHuni($app, $huni, isset($girdi['kaynak']) && \is_string($girdi['kaynak']) ? $girdi['kaynak'] : null))
                ? new AracSonucu(['huni' => $huni, 'imza' => $this->huniler->taslakImzasi($app, $huni)])
                : AracSonucu::hata(...$h),
            default => AracSonucu::hata("bilinmeyen araç: {$ad}"),
        };
    }

    private function kaydet(string $app, string $huni, mixed $tanim, mixed $imza): AracSonucu
    {
        // İmza zorunlu: model okumadan yazamaz, panelde biri değiştirdiyse ezmez (Ç39).
        if (!\is_string($imza) || 1 !== preg_match(self::IMZA, $imza)) {
            return AracSonucu::hata('imza: önce huni_oku ile güncel imzayı al');
        }
        $hatalar = $this->huniler->taslakKaydet($app, $huni, $tanim, $imza);

        return [] === $hatalar
            ? new AracSonucu(['kaydedildi' => true, 'imza' => $this->huniler->taslakImzasi($app, $huni)])
            : AracSonucu::hata(...$hatalar);
    }

    /**
     * Tanım alanı olan araç şemasına `$defs` eklenir (tek başına geçerli şema).
     *
     * @param array<string, mixed> $sema
     *
     * @return array<string, mixed>
     */
    private function semali(array $sema): array
    {
        /** @var array{'$defs': array<string, mixed>} $dosya */
        $dosya = json_decode((string) file_get_contents($this->semaDosyasi), true, flags: \JSON_THROW_ON_ERROR);

        return $sema + ['$defs' => $dosya['$defs']];
    }
}
