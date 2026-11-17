<?php

declare(strict_types=1);

namespace App\Tests\Support;

use cebe\openapi\Reader;
use cebe\openapi\spec\Schema;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\SchemaValidator;
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
    /**
     * Bir veriyi sözleşmedeki adlı şemaya göre doğrular (yanıt gövdesi
     * olmayan veri için: ör. SSE olayının data alanı, A2).
     */
    public static function semayaGore(string $sema, mixed $veri): void
    {
        $belge = Reader::readFromYamlFile('/contract/openapi.yaml');
        $s = $belge->components?->schemas[$sema] ?? null;
        if (!$s instanceof Schema) {
            throw new \InvalidArgumentException("sözleşmede {$sema} yok");
        }
        // Doğrulayıcı JSON nesnesini ilişkisel DİZİ bekler (Keywords\\Required:
        // arrayType; stdClass "geçersiz şema" verir — ölçüldü).
        (new SchemaValidator())->validate(json_decode((string) json_encode($veri), true), $s);
    }

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
