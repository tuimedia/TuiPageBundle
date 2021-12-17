<?php

namespace Tui\PageBundle\Search;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TypesenseClient
{
    private array $hosts;
    private HttpClientInterface $http;
    private string $indexPrefix;
    private LoggerInterface $log;
    private string $typesenseApiKey;

    /** @var TransformerInterface[] */
    private array $transformers = [];

    public function __construct(
        LoggerInterface $log,
        HttpClientInterface $http,
        string $indexPrefix,
        array $searchHosts,
        string $typesenseApiKey,
        array $componentTransformers = null,
    ) {
        if ($componentTransformers) {
            $this->transformers = $componentTransformers;
        }

        $this->http = $http;
        $this->hosts = $searchHosts;
        $this->indexPrefix = $indexPrefix;
        $this->log = $log;
        $this->typesenseApiKey = $typesenseApiKey;
    }

    public function setTransformers(array $componentTransformers): void
    {
        $this->transformers = $componentTransformers;
    }

    public function getCollectionNameForLanguage(string $lang): string
    {
        return vsprintf('%s_%s', [
            $this->indexPrefix,
            $lang,
        ]);
    }

    public function listCollections(): array
    {
        return $this->http->request('GET', vsprintf('%s/collections', [
            $this->hosts[0],
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
            ],
        ])->toArray();
    }

    public function deleteCollection(string $name): array
    {
        return $this->http->request('DELETE', vsprintf('%s/collections/%s', [
            $this->hosts[0],
            $name,
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
            ],
        ])->toArray();
    }

    public function createCollection(string $lang): array
    {
        // Create initial collection
        $config = [
            'name' => $this->getCollectionNameForLanguage($lang),
            'fields' => [
                [ 'name' => 'id', 'type' => 'string'],
                [ 'name' => 'slug', 'type' => 'string'],
                [ 'name' => 'state', 'type' => 'string', 'facet' => true],
                [ 'name' => 'revision', 'type' => 'string'],
                [ 'name' => 'searchableText', 'type' => 'string[]'],
            ],
        ];

        // Transform it
        foreach ($this->transformers as $transformer) {
            $config = $transformer->transformSchema($config);
        }

        // Send it
        return $this->http->request('POST', vsprintf('%s/collections', [$this->hosts[0]]), [
            'headers' => [ 'X-TYPESENSE-API-KEY' => $this->typesenseApiKey ],
            'json' => $config,
        ])->toArray();
    }

    public function upsertDocument(string $collection, array $doc): array
    {
        return $this->http->request('POST', vsprintf('%s/collections/%s/documents', [
            $this->hosts[0],
            $collection,
        ]), [
            'headers' => [ 'X-TYPESENSE-API-KEY' => $this->typesenseApiKey ],
            'query' => ['action' => 'upsert'],
            'json' => $doc,
        ])->toArray();

    }

    public function bulkImport(string $collection, array $docs): array
    {
        // Typesense takes JSONL(ines) instead of a JSON array of JSON docs, because… ???
        // Anyway, make that
        $body = join(PHP_EOL, array_map(function ($doc) {
            return json_encode($doc);
        }, $docs));

        $response = $this->http->request('POST', vsprintf('%s/collections/%s/documents/import', [
            $this->hosts[0],
            $collection,
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
                'Content-Type' => 'text/plain',
            ],
            'query' => ['action' => 'upsert'],
            'body' => $body,
        ]);

        $content = $response->getContent();
        // Parse responses, look for errors
        $responseLines = array_map(function ($line): array {
            return (array) json_decode($line, true);
        }, explode("\n", $content));
        $errors = array_filter($responseLines, function ($response): bool {
            return $response['success'] !== true;
        });
        if (count($errors)) {
            foreach ($errors as $docIdx => $error) {
                $this->log->error(sprintf('Bulk import returned an error for document index %d', $docIdx), $error);
            }
            throw new BulkImportException(sprintf('Bulk import returned %d error(s)', count($errors)));
        }

        return $responseLines;
    }
}
