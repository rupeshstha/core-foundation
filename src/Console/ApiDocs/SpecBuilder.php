<?php

namespace CoreFoundation\Console\ApiDocs;

/**
 * SpecBuilder
 *
 * @deprecated Part of the GenerateApiDocs pipeline which is deprecated.
 *   Use dedoc/scramble instead — it assembles the full OpenAPI 3.1 spec automatically.
 *   This class will be removed in a future release.
 */
class SpecBuilder
{
    private array $config;

    private array $paths = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Add one built operation to the spec.
     *
     * @param  array  $operation  Output of OperationBuilder::build()
     */
    public function addOperation(ReadResult $result, array $operation): void
    {
        $uri = $result->route->uri;
        $method = strtolower($result->route->method);

        // Initialise the path entry if not seen yet
        if (! isset($this->paths[$uri])) {
            $this->paths[$uri] = [];
        }

        $this->paths[$uri][$method] = $operation;
    }

    /**
     * Assemble and return the complete OpenAPI 3.0 document as a PHP array.
     * Passed to the writers for YAML/JSON serialisation.
     */
    public function build(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => $this->buildInfo(),
            'servers' => $this->buildServers(),
            'paths' => $this->paths,
        ];
    }

    private function buildInfo(): array
    {
        return [
            'title' => $this->config['title'] ?? config('app.name', 'API'),
            'description' => $this->config['description'] ?? '',
            'version' => $this->config['version'] ?? '1.0.0',
            'contact' => array_filter([
                'name' => $this->config['contact']['name'] ?? null,
                'email' => $this->config['contact']['email'] ?? null,
                'url' => $this->config['contact']['url'] ?? null,
            ]),
        ];
    }

    private function buildServers(): array
    {
        $servers = $this->config['servers'] ?? [];

        if (empty($servers)) {
            return [
                [
                    'url' => config('app.url', 'http://localhost'),
                    'description' => 'Current environment',
                ],
            ];
        }

        return $servers;
    }
}
