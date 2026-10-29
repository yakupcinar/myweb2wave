<?php
// G1 ölçümü (geçici, git dışı): aynı adımlar, varyant CSS'i enjekte edilerek PNG.
require __DIR__.'/../vendor/autoload.php';
[$_, $kok, $anahtar] = $argv;
$varyantlar = json_decode(file_get_contents(__DIR__.'/g1-varyantlar.json'), true);
$ip = gethostbyname('ekran');
$v = json_decode(file_get_contents("http://{$ip}:9222/json/version"), true);
$b = HeadlessChromium\BrowserFactory::connectToBrowser(preg_replace('#^ws://[^/]+#', "ws://{$ip}:9222", $v['webSocketDebuggerUrl']));
$p = $b->createPage();
$p->setDeviceMetricsOverride(['width' => 390, 'height' => 844, 'deviceScaleFactor' => 2, 'mobile' => true])->await();
$hazir = "(()=>{document.querySelector('astro-dev-toolbar')?.remove();return !document.querySelector('#durum')&&!!document.querySelector('[data-adim]:not([hidden])')&&[...document.images].every(i=>i.complete)&&document.fonts.status==='loaded'})()";
foreach (['karsilama', 'kiminle', 'paywall'] as $adim) {
    foreach ($varyantlar as $ad => $css) {
        $p->navigate("{$kok}&adim={$adim}&onizleme={$anahtar}")->waitForNavigation('load', 15000);
        for ($i = 0; $i < 100 && true !== $p->evaluate($hazir)->getReturnValue(); $i++) usleep(100000);
        // Önizleme şeridi her varyantta aynı; ölçüme gürültü katmasın.
        $p->evaluate("(()=>{document.querySelector('.onizleme')?.remove();const s=document.createElement('style');s.textContent=".json_encode($css).";document.head.append(s)})()");
        usleep(400000);
        file_put_contents(__DIR__."/g1/{$ad}-{$adim}.png", base64_decode($p->screenshot(['format' => 'png'])->getBase64()));
    }
    echo "{$adim} tamam\n";
}
$p->close();
$b->getConnection()->disconnect();
