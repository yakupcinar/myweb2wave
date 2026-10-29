<?php

declare(strict_types=1);

namespace App\Huni\Ekran;

/** Ç72: bir huni adresinin telefon ekranındaki görüntüsü (AI'ın gözü). */
interface EkranCekici
{
    /**
     * @return string JPEG baytları
     *
     * @throws \RuntimeException tarayıcıya ulaşılamadı ya da sayfa açılmadı (mesaj modele gider)
     */
    public function cek(string $adres): string;
}
