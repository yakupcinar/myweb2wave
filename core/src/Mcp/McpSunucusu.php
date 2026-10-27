<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Ai\UrunBaglami;
use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\Psr16SessionStore;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * MCP sunucusu (A5a, B6): A0 araçları (HuniAraclari) — panel sohbeti ve
 * konsolla AYNI tanım ve çalıştırıcı. Kapsam anahtarın uygulaması (huni
 * serbest; yeni huni açılabilir); yayınlama/silme aracı yok. Argümanlar önce
 * SDK'da şemaya göre (opis), sonra HuniAraclari'da doğrulanır. Ürün bağlamı
 * (A4) sunucu talimatında.
 */
final readonly class McpSunucusu
{
    public function __construct(
        private HuniAraclari $araclar,
        private UrunBaglami $baglam,
        private ApiAnahtarlari $anahtarlar,
        #[Autowire(service: 'mcp.oturumlar')]
        private CacheInterface $oturumlar,
        #[Autowire('%env(default::APP_VERSION)%')]
        private ?string $surum,
    ) {
    }

    public function kur(string $app, int $anahtarId): Server
    {
        $kapsam = new AracKapsami($app);
        $kurucu = Server::builder()
            ->setServerInfo('MyWeb2Wave', $this->surum ?? 'gelistirme')
            ->setInstructions($this->talimat($app))
            ->setSession(new Psr16SessionStore($this->oturumlar, 'mcp-'.$app.'-'));
        foreach ($this->araclar->tanimlar($kapsam) as $t) {
            $ad = $t['name'];
            $kurucu->addTool(
                function (RequestContext $baglam) use ($ad, $kapsam, $anahtarId): CallToolResult {
                    $istek = $baglam->getRequest();
                    $girdi = property_exists($istek, 'arguments') ? $istek->arguments : [];
                    $s = $this->araclar->calistir($ad, $girdi, $kapsam);
                    $this->anahtarlar->cagriKaydet($anahtarId, $ad, $s->hata);

                    return new CallToolResult(
                        [new TextContent(json_encode($s->icerik, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR))],
                        $s->hata,
                        $s->icerik,
                    );
                },
                name: $ad,
                description: $t['description'],
                inputSchema: $t['input_schema'],
            );
        }

        return $kurucu->build();
    }

    private function talimat(string $app): string
    {
        $metin = <<<METIN
            MyWeb2Wave huni platformu, uygulama: {$app}. Huni = reklamdan gelen ziyaretçinin gördüğü adımlar (tanıtım, soru: tekli/çoklu/serbest yanıt, müşteri yorumu, ödeme ekranı).
            Çalışma: huniler_listele ile bak; değiştirmeden önce huni_oku ile tanımı ve imzayı al; TAM tanımı taslak_kaydet ile yaz (imza zorunlu). Hata dönerse düzeltip yeniden kaydet. Sonuçta onizleme_adresi ile bağlantı ver.
            Yalnız TASLAĞA yazarsın; yayınlamak panelde insanın işi. Gerçek olmayan müşteri yorumu uydurma (unvana "Örnek — yayından önce gerçeğiyle değiştir"); fiyat yazma (ödeme ekranı Stripe'tan gösterir); kesin sonuç vaadi yazma.
            METIN;
        $baglam = $this->baglam->istemMetni($app);

        return null === $baglam ? $metin : $metin."\n\n".$baglam;
    }
}
