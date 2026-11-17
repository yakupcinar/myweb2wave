<?php

declare(strict_types=1);

namespace App\Controller;

use App\Yasal\YasalMetinler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M9b1 (A23): herkese açık yasal sayfalar — huni ve paywall bağlanır.
 * Yalnız yöneticinin kaydettiği metin; kaydedilmemişse 404 (taslak yayında
 * değildir).
 */
final class YasalController extends AbstractController
{
    public function __construct(private readonly YasalMetinler $metinler)
    {
    }

    #[Route('/yasal/{app}/{belge}/{dil}', name: 'yasal_sayfa', methods: ['GET'])]
    public function __invoke(string $app, string $belge, string $dil): Response
    {
        // Kaydedilmiş satır yoksa 404 — bilinmeyen belge / dil de böyle (DB kısıtı başka türü kabul etmez).
        $sayfa = $this->metinler->sayfa($app, $belge, $dil);
        if (null === $sayfa) {
            throw $this->createNotFoundException();
        }

        return $this->render('yasal.html.twig', ['sayfa' => $sayfa, 'dil' => $dil]);
    }
}
