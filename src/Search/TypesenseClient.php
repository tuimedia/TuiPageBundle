<?php

namespace Tui\PageBundle\Search;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttplugClient;
use Tui\PageBundle\Entity\PageInterface;
use Typesense\Client;

class TypesenseClient
{
    private Client $typesense;

    /** @var TransformerInterface[] */
    private array $transformers = [];

    public function __construct(
        private LoggerInterface $log,
        private string $indexPrefix,
        string $typesenseApiKey,
        array $searchHosts,
        array $componentTransformers = null,
    ) {
        if ($componentTransformers) {
            $this->transformers = $componentTransformers;
        }
        $this->typesense = new Client([
            'api_key' => $typesenseApiKey,
            'nodes' => $this->formatHosts($searchHosts),
            'client' => new HttplugClient(),
        ]);
    }

    private function formatHosts(array $searchHosts): array
    {
        return array_map(fn ($host) => [
            'protocol' => parse_url($host, PHP_URL_SCHEME),
            'host' => parse_url($host, PHP_URL_HOST),
            'port' => parse_url($host, PHP_URL_PORT),
        ], $searchHosts);
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

        return $this->typesense->collections[$collection]->documents->search($queryMerged);
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
        return $this->typesense->collections->retrieve();
    }

    public function deleteCollection(string $name): array
    {
        return $this->typesense->collections[$name]->delete();
    }

    public function createCollection(string $name): array
    {
        // Create initial collection
        $config = [
            'name' => $name,
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'slug', 'type' => 'string'],
                ['name' => 'state', 'type' => 'string', 'facet' => true],
                ['name' => 'revision', 'type' => 'string'],
                ['name' => 'searchableText', 'type' => 'string[]'],
            ],
        ];

        // Transform it
        foreach ($this->transformers as $transformer) {
            $config = $transformer->transformSchema($config);
        }

        // Send it
        return $this->typesense->collections->create($config);
    }

    public function upsertDocument(string $collection, array $doc): array
    {
        return $this->typesense->collections[$collection]->documents->upsert($doc);
    }

    public function deleteDocument(string $collection, string $id): array
    {
        return $this->typesense->collections[$collection]->documents[$id]->delete();
    }

    public function bulkImport(string $collection, array $docs): array
    {
        $result = $this->typesense->collections[$collection]->documents->import($docs, [
            'action' => 'upsert',
        ]);

        // Look for errors in the responses
        $errors = array_filter((array) $result, fn ($response): bool => $response['success'] !== true);
        if (count($errors)) {
            foreach ($errors as $docIdx => $error) {
                $this->log->error(sprintf('Bulk import returned an error for document index %d', $docIdx), ['error' => $error]);
            }
            throw new BulkImportException(sprintf('Bulk import returned %d error(s)', count($errors)));
        }

        return (array) $result;
    }
}
