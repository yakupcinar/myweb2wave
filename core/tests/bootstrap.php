<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// M1a: planlar veritabanında; testlerin ortak 'monthly' planı (eskiden
// .env.test'teki fiyat kimliği). Tablolar test başına boşaltılır ama planlar
// değil — plan testleri bu satırı silmez, kendi anahtarlarını kullanır.
$kernel = new App\Kernel('test', false);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine.dbal.default_connection');
assert($db instanceof Doctrine\DBAL\Connection);
// Yarım kalmış önceki koşunun planları (tearDown koşmadıysa) burada silinir.
$db->executeStatement("DELETE FROM planlar WHERE NOT (app = 'keepchat' AND anahtar = 'monthly')");
$db->executeStatement(
    "INSERT INTO planlar (app, anahtar, ad, stripe_urun, stripe_fiyat) VALUES ('keepchat', 'monthly', 'Aylık', 'prod_test_keepchat', 'price_test_keepchat_aylik') ON CONFLICT (app, anahtar) DO UPDATE SET stripe_urun = EXCLUDED.stripe_urun, stripe_fiyat = EXCLUDED.stripe_fiyat",
);
$kernel->shutdown();
