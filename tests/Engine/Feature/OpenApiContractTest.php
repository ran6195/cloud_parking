<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Yaml\Yaml;

/**
 * Ogni rotta del motore deve essere documentata in openapi/v1.yaml.
 * La spec può contenere endpoint non ancora implementati, non il contrario.
 */
function openApiPaths(): array
{
    $spec = Yaml::parseFile(base_path('openapi/v1.yaml'));

    $paths = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $paths[] = strtoupper($method).' '.preg_replace('/\{[^}]+\}/', '{}', $path);
            }
        }
    }

    return $paths;
}

it('documenta nella spec ogni rotta del motore', function () {
    $documented = openApiPaths();

    $routes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'engine.v1.'))
        ->flatMap(fn (Route $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' '.preg_replace(
                '/\{[^}]+\}/',
                '{}',
                substr($route->uri(), strlen('api/v1')),
            )));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($documented)->toContain($route);
    }
});

it('ha una spec OpenAPI 3.1 leggibile', function () {
    $spec = Yaml::parseFile(base_path('openapi/v1.yaml'));

    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['paths'])->toHaveKey('/health');
});
