<?php

declare(strict_types=1);

namespace App\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaMessageParam;
use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Beta\Messages\BetaRedactedThinkingBlock;
use Anthropic\Beta\Messages\BetaRedactedThinkingBlockParam;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaTextBlockParam;
use Anthropic\Beta\Messages\BetaThinkingBlock;
use Anthropic\Beta\Messages\BetaThinkingBlockParam;
use Anthropic\Beta\Messages\BetaToolResultBlockParam;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Beta\Messages\BetaToolUseBlockParam;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use App\Huni\Huniler;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Panel içi AI asistanı (A2): yöneticinin istemini açık huninin taslağına
 * uygular. Döngü bizde (tur sınırı, doğrulama hatası modele geri, olaylar
 * panele): her tur akışsız `beta->messages->create`; araçlar HuniAraclari
 * (A0) — panelle, konsolla, MCP ile AYNI; kapsam açık huni. Yalnız taslak;
 * yayın insanda. Her istek ai_istekleri'ne (jeton, maliyet, sonuç; istem
 * metni YOK). HTTP Symfony istemcisinden (testte SahteClaude, ağa çıkmaz).
 */
final readonly class HuniAsistani
{
    /** Opus 5.5+ ret durumunda sunucu tarafı yedek model (claude-api rehberi). */
    private const YEDEK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private AiAyarlari $ayarlar,
        private HuniAraclari $araclar,
        private Huniler $huniler,
        private UrunBaglami $baglam,
        private Connection $db,
        private HttpClientInterface $http,
    ) {
    }

    /**
     * @param callable(string, array<string, mixed>): void $olay panele giden olay (tür, veri)
     *
     * @return string sonuç: tamam | tur_siniri | reddedildi | jeton_siniri | hata
     */
    public function calistir(string $app, string $huni, string $istem, callable $olay): string
    {
        $basla = hrtime(true);
        $kapsam = new AracKapsami($app, $huni);
        $toplam = ['girdi' => 0, 'cikti' => 0, 'okuma' => 0, 'yazma' => 0];
        $tur = 0;
        $kaydedildi = false;
        $sonuc = 'hata';
        try {
            $istemci = new Client(
                apiKey: $this->ayarlar->anahtar,
                requestOptions: RequestOptions::with(transporter: new Psr18Client($this->http)),
            );
            // SDK'nın InputSchema tipi JSON Şemasından dar ($defs,
            // additionalProperties yok); anahtarlar olduğu gibi gider (PanelAiTest ölçer).
            /** @var list<array{name: string, description: string, inputSchema: array{type: 'object', properties?: array<string, mixed>, required?: list<string>}}> $araclar */
            $araclar = array_map(static fn (array $a): array => [
                'name' => $a['name'],
                'description' => $a['description'],
                'inputSchema' => $a['input_schema'],
            ], $this->araclar->tanimlar($kapsam));
            $efor = Effort::from($this->ayarlar->efor);
            // Önbellek: araçlar + sistem istemi (+ A4 ürün bağlamı) her turda aynı
            // önek; işaret SON blokta, öncesini de kapsar.
            $metinler = [$this->sistemIstemi($kapsam)];
            if (null !== $baglam = $this->baglam->istemMetni($app)) {
                $metinler[] = $baglam;
            }
            $son = \count($metinler) - 1;
            $sistem = array_map(
                static fn (string $m, int $i): BetaTextBlockParam => BetaTextBlockParam::with(text: $m, cacheControl: $i === $son ? ['type' => 'ephemeral'] : null),
                $metinler,
                array_keys($metinler),
            );
            $mesajlar = [BetaMessageParam::with(content: $this->ilkMesaj($app, $huni, $istem), role: 'user')];

            while (true) {
                if ($tur >= $this->ayarlar->azamiTur) {
                    $sonuc = 'tur_siniri';
                    $olay('hata', ['mesaj' => "İstek {$this->ayarlar->azamiTur} turda bitmedi; taslakta kaydedilen son hâl duruyor. Daha küçük bir istekle yeniden deneyin."]);
                    break;
                }
                ++$tur;
                $yanit = $istemci->beta->messages->create(
                    maxTokens: $this->ayarlar->azamiJeton,
                    messages: $mesajlar,
                    model: $this->ayarlar->model,
                    fallbacks: 'default',
                    outputConfig: ['effort' => $efor],
                    system: $sistem,
                    tools: $araclar,
                    betas: [self::YEDEK_BETA],
                );
                $this->topla($toplam, $yanit);

                $sonuclar = [];
                foreach ($yanit->content as $blok) {
                    if ($blok instanceof BetaTextBlock && '' !== trim($blok->text)) {
                        $olay('metin', ['metin' => $blok->text]);
                    }
                    if ($blok instanceof BetaToolUseBlock) {
                        $olay('arac', ['ad' => $blok->name]);
                        $s = $this->araclar->calistir($blok->name, $blok->input, $kapsam);
                        if ('taslak_kaydet' === $blok->name && !$s->hata) {
                            $kaydedildi = true;
                            // Düzenleyici tuvali bununla yeniler (ek istek yok).
                            $olay('taslak', ['imza' => $s->icerik['imza'] ?? null, 'tanim' => $this->huniler->taslak($app, $huni)]);
                        }
                        $olay('arac_sonucu', ['ad' => $blok->name, 'hata' => $s->hata]);
                        $sonuclar[] = BetaToolResultBlockParam::with(
                            toolUseID: $blok->id,
                            content: json_encode($s->icerik, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                            isError: $s->hata,
                        );
                    }
                }

                if ('refusal' === $yanit->stopReason) {
                    $sonuc = 'reddedildi';
                    $olay('hata', ['mesaj' => 'Claude bu isteği yanıtlamadı. İsteği farklı anlatıp yeniden deneyin.']);
                    break;
                }
                if ('max_tokens' === $yanit->stopReason) {
                    $sonuc = 'jeton_siniri';
                    $olay('hata', ['mesaj' => 'Yanıt sınıra takıldı. İsteği bölün (ör. önce sorular, sonra ödeme ekranı).']);
                    break;
                }
                if ([] === $sonuclar) {
                    $sonuc = 'tamam';
                    break;
                }
                // Tüm araç sonuçları TEK kullanıcı mesajında (paralel çağrı).
                $mesajlar[] = BetaMessageParam::with(content: self::geriGonder($yanit), role: 'assistant');
                $mesajlar[] = BetaMessageParam::with(content: $sonuclar, role: 'user');
            }
        } catch (AuthenticationException) {
            $olay('hata', ['mesaj' => 'AI anahtarı geçersiz — core/.env.local içindeki ANTHROPIC_API_KEY.']);
        } catch (RateLimitException) {
            $olay('hata', ['mesaj' => 'Claude şu an yoğun; biraz sonra yeniden deneyin.']);
        } catch (APIStatusException $e) {
            $olay('hata', ['mesaj' => 'Claude isteği hata verdi ('.$e->getCode().').']);
        } catch (APIConnectionException) {
            $olay('hata', ['mesaj' => 'Claude\'a bağlanılamadı.']);
        }

        $maliyet = Maliyet::mikrodolar($this->ayarlar->model, $toplam['girdi'], $toplam['cikti'], $toplam['okuma'], $toplam['yazma']);
        $this->db->insert('ai_istekleri', [
            'app' => $app,
            'huni' => $huni,
            'model' => $this->ayarlar->model,
            'tur' => $tur,
            'girdi_jeton' => $toplam['girdi'],
            'cikti_jeton' => $toplam['cikti'],
            'onbellek_okuma' => $toplam['okuma'],
            'onbellek_yazma' => $toplam['yazma'],
            'maliyet_mikrodolar' => $maliyet,
            'sonuc' => $sonuc,
            'kaydedildi' => $kaydedildi ? 'true' : 'false',
            'sure_ms' => intdiv(hrtime(true) - $basla, 1_000_000),
        ]);
        $olay('bitti', ['sonuc' => $sonuc, 'kaydedildi' => $kaydedildi, 'tur_sayisi' => $tur, 'maliyetMikrodolar' => $maliyet]);

        return $sonuc;
    }

    /**
     * Asistanın yanıtı bir sonraki istekte AYNEN geri gider: düşünme blokları
     * imzalarıyla (Opus 5.5 "preserved thinking"; değişen geçmiş reddedilir).
     * Araç sunucusu kullanmıyoruz → yalnız bu dört tür gelir.
     *
     * @return list<BetaTextBlockParam|BetaToolUseBlockParam|BetaThinkingBlockParam|BetaRedactedThinkingBlockParam>
     */
    private static function geriGonder(BetaMessage $yanit): array
    {
        return array_map(static fn (object $b): object => match (true) {
            $b instanceof BetaTextBlock => BetaTextBlockParam::with(text: $b->text),
            $b instanceof BetaToolUseBlock => BetaToolUseBlockParam::with(id: $b->id, input: $b->input, name: $b->name),
            $b instanceof BetaThinkingBlock => BetaThinkingBlockParam::with(signature: $b->signature, thinking: $b->thinking),
            $b instanceof BetaRedactedThinkingBlock => BetaRedactedThinkingBlockParam::with(data: $b->data),
            default => throw new \LogicException('beklenmeyen yanıt bloğu: '.$b::class),
        }, $yanit->content);
    }

    /** @param array{girdi: int, cikti: int, okuma: int, yazma: int} $toplam */
    private function topla(array &$toplam, BetaMessage $yanit): void
    {
        $u = $yanit->usage;
        $toplam['girdi'] += $u->inputTokens;
        $toplam['cikti'] += $u->outputTokens;
        $toplam['okuma'] += $u->cacheReadInputTokens ?? 0;
        $toplam['yazma'] += $u->cacheCreationInputTokens ?? 0;
    }

    /** Açık huninin taslağı ve imzası ilk mesajda: okuma turu harcanmaz. */
    private function ilkMesaj(string $app, string $huni, string $istem): string
    {
        $taslak = json_encode($this->huniler->taslak($app, $huni), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $imza = $this->huniler->taslakImzasi($app, $huni) ?? '';

        return "İstek:\n{$istem}\n\n'{$huni}' hunisinin şu anki taslağı (imza {$imza}):\n{$taslak}";
    }

    /** Sabit metin (önbellek öneki): yalnız uygulama ve huni adı değişir. */
    private function sistemIstemi(AracKapsami $k): string
    {
        return <<<METIN
            Sen MyWeb2Wave panelinde huni düzenleyen asistansın. Uygulama: {$k->app}. Açık huni: {$k->huni}.
            Huni, reklamdan gelen ziyaretçinin gördüğü adımlardır: tanıtım, soru (tekli, çoklu, serbest yanıt), müşteri yorumu ve ödeme ekranı (paywall).

            Nasıl çalışırsın:
            - İsteği mevcut taslağa uygula ve TAM tanımı taslak_kaydet ile kaydet (imza: verilen ya da son kayıttan dönen). İstenmeyen adımlara ve metinlere dokunma.
            - Kaydetme hata döndürürse hataları düzeltip yeniden kaydet. Emin değilsen önce tanim_dogrula kullanabilirsin.
            - Yalnız taslağa yazarsın; yayınlamak yöneticinin işi.
            - Bitince yaptığını Türkçe, kısa anlat (2-4 cümle); gerekirse yöneticinin kontrol etmesi gerekeni söyle.

            Kurallar:
            - Adım ve seçenek kimlikleri küçük harf, rakam, - ve _; benzersiz. Seçenekteki hedef yalnız SONRAKİ bir adımın kimliği olabilir.
            - Metinler düz metin; HTML, bağlantı, emoji dışında süs yok.
            - Gerçek olmayan müşteri yorumu uydurma: yorum adımı eklersen unvanına "Örnek — yayından önce gerçeğiyle değiştir" yaz.
            - Fiyat yazma: ödeme ekranı fiyatı Stripe'tan kendisi gösterir. Sağlık, gelir ya da kesin sonuç vaadi yazma.
            - Ziyaretçiye "sen" diye, sade Türkçe yaz.
            METIN;
    }
}
