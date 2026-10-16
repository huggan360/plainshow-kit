<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * An extension's API described as OpenAPI 3.1, built from its router's own
 * routes plus short notes, so the description cannot drift from the code.
 *
 * Routes: list of ['method', 'path', 'capability'] where capability is null
 * (public), 'user' (anyone with the service) or a capability name.
 * Notes, keyed "METHOD /path": summary, description, returns, params
 * (path parameter => text), query (name => text), body (name => [type, text,
 * required]), form (true for multipart), internal (hide from docs).
 */
final class OpenApi
{
    public static function build(array $routes, array $docs, array $info, string $server): array
    {
        $paths = [];
        foreach ($routes as $route) {
            $key = $route['method'] . ' ' . $route['path'];
            $doc = $docs[$key] ?? [];
            $operation = [
                'summary' => $doc['summary'] ?? $key,
                'responses' => ['200' => ['description' => $doc['returns'] ?? 'Done']],
            ];
            if (!empty($doc['description'])) {
                $operation['description'] = $doc['description'];
            }
            if ($route['capability'] === null) {
                $operation['security'] = [];
            } elseif ($route['capability'] !== 'user') {
                $operation['x-capability'] = $route['capability'];
            }
            if (str_starts_with($route['path'], '/widgets/') || ($doc['internal'] ?? false)) {
                $operation['x-internal'] = true;
            }
            $parameters = [];
            if (preg_match_all('/\{([a-z_]+)\}/', $route['path'], $names)) {
                foreach ($names[1] as $name) {
                    $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => $doc['params'][$name] ?? ''];
                }
            }
            foreach ($doc['query'] ?? [] as $name => $description) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => $description];
            }
            if ($parameters) {
                $operation['parameters'] = $parameters;
            }
            if (!empty($doc['body'])) {
                $properties = [];
                $required = [];
                foreach ($doc['body'] as $name => [$type, $description, $isRequired]) {
                    $properties[$name] = ['type' => $type, 'description' => $description];
                    if ($isRequired) {
                        $required[] = $name;
                    }
                }
                $schema = ['type' => 'object', 'properties' => $properties] + ($required ? ['required' => $required] : []);
                $operation['requestBody'] = ['content' => [($doc['form'] ?? false) ? 'multipart/form-data' : 'application/json' => ['schema' => $schema]]];
            }
            $paths[$route['path']][strtolower($route['method'])] = $operation;
        }

        return [
            'openapi' => '3.1.0',
            'info' => $info,
            'servers' => [['url' => $server]],
            'security' => [['bearer' => []]],
            'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']]],
            'paths' => $paths,
        ];
    }
}
