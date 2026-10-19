<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    // Çalışma zamanı her zaman dizge verir; assert yalnız statik analize söyler.
    assert(is_string($context['APP_ENV']));

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
