<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PostmarkWebhookController;
use App\Posta\Bastirma;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\MessageStreamHeader;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mime\Email;

/**
 * A22: Postmark bastırma listesinin yerel kopyası. Webhook (Subscription
 * Change) HTTP Basic ile; eski tarihli bildirim yenisini ezmez. Pasif
 * alıcıya gönderimde köprü atmaz (406 olayı) — o da listeye yazılır (§57).
 */
final class PostmarkWebhookTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE posta_bastirma');
    }

    public function testBasicAuthOlmadanYaDaYanlisSifreyle401(): void
    {
        $this->gonder($this->degisim(true, '2026-11-10T10:00:00Z'), null);
        self::assertResponseStatusCodeSame(401);
        $this->gonder($this->degisim(true, '2026-11-10T10:00:00Z'), 'yanlis');
        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->satirlar());
    }

    public function testSifreBossaUcKapali(): void
    {
        $uc = new PostmarkWebhookController(new Bastirma($this->db), '');
        $istek = Request::create('/webhooks/postmark', 'POST', server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('postmark:')], content: (string) json_encode($this->degisim(true, '2026-11-10T10:00:00Z')));

        self::assertSame(404, $uc($istek)->getStatusCode());
        self::assertSame([], $this->satirlar());
    }

    public function testBastirmaVeYenidenEtkinlestirme(): void
    {
        $this->gonder($this->degisim(true, '2026-11-10T10:00:00Z', 'HardBounce'));
        self::assertResponseStatusCodeSame(200);
        self::assertSame([['ayse@example.com', 'outbound', true, 'HardBounce']], $this->satirlar());

        $this->gonder($this->degisim(false, '2026-11-10T11:00:00Z', null));
        self::assertResponseStatusCodeSame(200);
        self::assertSame([['ayse@example.com', 'outbound', false, null]], $this->satirlar());
    }

    public function testEskiTarihliBildirimYenisiniEzmez(): void
    {
        // Yeniden deneme sırayı karıştırabilir: yeniden etkinleştirme önce gelir.
        $this->gonder($this->degisim(false, '2026-11-10T11:00:00Z', null));
        $this->gonder($this->degisim(true, '2026-11-10T10:00:00Z', 'SpamComplaint'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([['ayse@example.com', 'outbound', false, null]], $this->satirlar());
    }

    public function testAkisBasinaAyriSatir(): void
    {
        $this->gonder($this->degisim(true, '2026-11-10T10:00:00Z', 'ManualSuppression', 'broadcast'));
        self::assertSame([['ayse@example.com', 'broadcast', true, 'ManualSuppression']], $this->satirlar());
    }

    public function testIlgisizKayitTuruYokSayilirBicimDisi400(): void
    {
        $this->gonder(['RecordType' => 'Bounce', 'Email' => 'a@example.com']);
        self::assertResponseStatusCodeSame(200);

        $eksik = $this->degisim(true, '2026-11-10T10:00:00Z');
        unset($eksik['SuppressSending']);
        $this->gonder($eksik);
        self::assertResponseStatusCodeSame(400);
        $this->gonder($this->degisim(true, 'dun'));
        self::assertResponseStatusCodeSame(400);
        $this->gonder(['RecordType' => 'SubscriptionChange'] + $this->degisim(true, '2026-11-10T10:00:00Z', 'HardBounce', 'outbound', 'adres-degil'));
        self::assertResponseStatusCodeSame(400);

        self::assertSame([], $this->satirlar());
    }

    public function testPasifAliciyaGonderimAtmazListeyeYazilir(): void
    {
        $dagitici = static::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $dagitici);
        // Postmark'ın belgelenmiş yanıtı: HTTP 422, ErrorCode 406 (Inactive recipient).
        $yanit = fn () => new MockResponse((string) json_encode(['ErrorCode' => 406, 'Message' => 'You tried to send to recipient(s) that have been marked as inactive.']), ['http_code' => 422]);
        $tasiyici = new PostmarkApiTransport('anahtar', new MockHttpClient([$yanit(), $yanit()]), $dagitici);

        $tasiyici->send((new Email())->from('no-reply@example.com')->to('Ayse@Example.com')->subject('k')->text('k'));
        $yayin = (new Email())->from('no-reply@example.com')->to('b@example.com')->subject('k')->text('k');
        $yayin->getHeaders()->add(new MessageStreamHeader('broadcast'));
        $tasiyici->send($yayin);

        self::assertSame([
            ['ayse@example.com', 'outbound', true, 'InactiveRecipient'],
            ['b@example.com', 'broadcast', true, 'InactiveRecipient'],
        ], $this->satirlar());
    }

    /** @return array<string, mixed> */
    private function degisim(bool $bastir, string $zaman, ?string $neden = 'HardBounce', string $akis = 'outbound', string $alici = 'Ayse@Example.com'): array
    {
        // Postmark belgesindeki örnek gövdenin alanları.
        return [
            'RecordType' => 'SubscriptionChange', 'MessageID' => null, 'ServerID' => 23, 'MessageStream' => $akis,
            'ChangedAt' => $zaman, 'Recipient' => $alici, 'Origin' => 'Recipient', 'SuppressSending' => $bastir,
            'SuppressionReason' => $neden, 'Tag' => null, 'Metadata' => [],
        ];
    }

    /** @param array<string, mixed> $govde */
    private function gonder(array $govde, ?string $sifre = 'test-postmark-sifre'): void
    {
        $sunucu = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $sifre) {
            $sunucu['HTTP_AUTHORIZATION'] = 'Basic '.base64_encode('postmark:'.$sifre);
        }
        $this->client->request('POST', '/webhooks/postmark', [], [], $sunucu, (string) json_encode($govde));
    }

    /** @return list<array{mixed, mixed, mixed, mixed}> */
    private function satirlar(): array
    {
        return array_map(
            static fn (array $r): array => [$r['email'], $r['akis'], $r['bastirildi'], $r['neden']],
            $this->db->fetchAllAssociative('SELECT email, akis, bastirildi, neden FROM posta_bastirma ORDER BY email'),
        );
    }
}
