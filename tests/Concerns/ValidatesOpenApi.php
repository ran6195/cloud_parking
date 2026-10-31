<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Osteel\OpenApi\Testing\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica che le risposte del motore rispettino il contratto openapi/v1.yaml.
 */
trait ValidatesOpenApi
{
    private static ?ValidatorInterface $openApiValidator = null;

    protected function openApiValidator(): ValidatorInterface
    {
        return self::$openApiValidator ??= ValidatorBuilder::fromYamlFile(base_path('openapi/v1.yaml'))->getValidator();
    }

    /**
     * @param  TestResponse<Response>  $response
     * @param  string  $path  percorso come scritto nella spec, ad es. "/quotes/{quoteId}"
     */
    protected function assertMatchesOpenApi(TestResponse $response, string $path, string $method): void
    {
        $this->assertTrue(
            $this->openApiValidator()->validate($response->baseResponse, $path, $method),
            "La risposta {$method} {$path} non rispetta openapi/v1.yaml",
        );
    }
}
