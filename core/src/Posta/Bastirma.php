<?php

declare(strict_types=1);

namespace App\Posta;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Postmark'ın bastırma listesi (A22). Postmark bastırılmış alıcıya göndermez
 * ve Symfony köprüsü bunu HATA OLARAK ATMAZ (406 → PostmarkDeliveryEvent,
 * send() başarılı döner; tuzak §57). Yerel kopya iki şey için: gitmeyecek
 * e-postayı kuyrukta sonsuza dek denememek ve "gitti" sanmamak.
 */
final readonly class Bastirma
{
    /** Postmark'ın varsayılan işlem (transactional) akışı. */
    public const ISLEM = 'outbound';

    public function __construct(private Connection $db)
    {
    }

    /** Eski tarihli (yeniden denenmiş, sırası karışmış) bildirim yenisini ezmez. */
    public function yaz(string $email, string $akis, bool $bastirildi, ?string $neden, \DateTimeImmutable $at): void
    {
        $this->db->executeStatement(
            'INSERT INTO posta_bastirma (email, akis, bastirildi, neden, degisti_at) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (email, akis) DO UPDATE
             SET bastirildi = EXCLUDED.bastirildi, neden = EXCLUDED.neden, degisti_at = EXCLUDED.degisti_at
             WHERE posta_bastirma.degisti_at <= EXCLUDED.degisti_at',
            [mb_strtolower($email), $akis, $bastirildi, $neden, $at->format(\DATE_ATOM)],
            [2 => ParameterType::BOOLEAN],
        );
    }
}
