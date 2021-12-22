<?php

namespace Tui\PageBundle\Search;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tui\PageBundle\Entity\PageInterface;

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

    public function createSearchDocument(PageInterface $page, string $language): array
    {
        $translatedPage = [
            'id' => (string) $page->getId(),
            'revision' => (string) $page->getPageData()->getRevision(),
            'state' => (string) $page->getState(),
            'slug' => (string) $page->getSlug(),
            'searchableText' => [],
        ];

        foreach ($this->transformers as $transformer) {
            $translatedPage = $transformer->transformDocument($translatedPage, $page, $language);
        }

        $translatedPage['searchableText'] = array_values(array_filter($translatedPage['searchableText']));

        return $translatedPage;
    }

    public function search(string $collection, array $query): ?array
    {
        $queryMerged = array_replace([
            'query_by' => 'searchableText',
            'prefix' => false,
        ], $query);

        // `q` is required, but it can be an empty string or * to return all
        if (!isset($queryMerged['q'])) {
            return null;
        }

        return $this->http->request('GET', vsprintf('%s/collections/%s/documents/search', [
            $this->getSearchHost(),
            $collection,
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
            ],
            'query' => $queryMerged,
        ])->toArray();
    }

    /** TODO: handle multiple servers somehow instead of always returning the first one */
    private function getSearchHost(): string
    {
        return $this->hosts[0];
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
            $this->getSearchHost(),
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
            ],
        ])->toArray();
    }

    public function deleteCollection(string $name): array
    {
        return $this->http->request('DELETE', vsprintf('%s/collections/%s', [
            $this->getSearchHost(),
            $name,
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
            ],
        ])->toArray();
    }

    public function createCollection(string $name): array
    {
        // Create initial collection
        $config = [
            'name' => $name,
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
            $this->getSearchHost(),
            $collection,
        ]), [
            'headers' => [ 'X-TYPESENSE-API-KEY' => $this->typesenseApiKey ],
            'query' => ['action' => 'upsert'],
            'json' => $doc,
        ])->toArray();
    }

    public function deleteDocument(string $collection, string $id): array
    {
        return $this->http->request('DELETE', vsprintf('%s/collections/%s/documents/%s', [
            $this->getSearchHost(),
            $collection,
            $id,
        ]), [
            'headers' => [ 'X-TYPESENSE-API-KEY' => $this->typesenseApiKey ],
        ])->toArray();
    }

    public function bulkImport(string $collection, array $docs): array
    {
        // Typesense takes JSONL(ines) instead of a JSON array of JSON docs, because… ???
        // Anyway, make that
        $body = join(PHP_EOL, array_map(function ($doc) {
            return json_encode($doc);
        }, $docs));

        $content = $this->http->request('POST', vsprintf('%s/collections/%s/documents/import', [
            $this->getSearchHost(),
            $collection,
        ]), [
            'headers' => [
                'X-TYPESENSE-API-KEY' => $this->typesenseApiKey,
                'Content-Type' => 'text/plain',
            ],
            'query' => ['action' => 'upsert'],
            'body' => $body,
        ])->getContent();

        // Parse responses, look for errors
        $responseLines = array_map(function ($line): array {
            return (array) json_decode($line, true);
        }, explode("\n", $content));
        $errors = array_filter($responseLines, function ($response): bool {
            return $response['success'] !== true;
        });
        if (count($errors)) {
            foreach ($errors as $docIdx => $error) {
                $this->log->error(sprintf('Bulk import returned an error for document index %d', $docIdx), ['error' => $error]);
            }
            throw new BulkImportException(sprintf('Bulk import returned %d error(s)', count($errors)));
        }

        return $responseLines;
    }
}
