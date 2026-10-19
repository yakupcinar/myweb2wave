<?php

declare(strict_types=1);

namespace App\Tests\Support;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerçek yanıtı contract/openapi.yaml'a göre doğrular (R2). Uymazsa istisna
 * atar — huninin tipleri aynı dosyadan üretildiği için iki taraf ancak bu
 * dosya üzerinden kayabilir.
 */
final class Sozlesme
{
    public static function yanitiDogrula(string $method, string $path, Response $yanit): void
    {
        $f = new Psr17Factory();
        $psr = (new PsrHttpFactory($f, $f, $f, $f))->createResponse($yanit);

        (new ValidatorBuilder())
            ->fromYamlFile('/contract/openapi.yaml')
            ->getResponseValidator()
            ->validate(new OperationAddress($path, strtolower($method)), $psr);
    }
}
