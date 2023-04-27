<?php
namespace Tui\PageBundle\Search;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;
use Tui\PageBundle\Entity\IsIndexableInterface;
use Tui\PageBundle\Entity\PageInterface;

class SearchSubscriber implements EventSubscriber
{
    private $searcher;
    private $logger;
    private $enabled;
    private $collections = null;

    public function __construct(
        TypesenseClient $searcher,
        LoggerInterface $logger,
        bool $searchEnabled
    ) {
        if (!$searchEnabled) {
            return;
        }

        $this->searcher = $searcher;
        $this->logger = $logger;
        $this->enabled = $searchEnabled;
    }

    // preUpdate - get diff of availableLanguages, ensure indexes exist, add/remove translated document from each
    // postPersist -- add new document to index
    // preRemove -- delete from indexes

    public function getSubscribedEvents(): array
    {
        return [
            'postPersist',
            'preUpdate',
            'preRemove',
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        if ($entity instanceof IsIndexableInterface && !$entity->isIndexable()) {
            return;
        }

        try {
            $this->updateDocumentIndexes($args);
        } catch (\Exception $e) {
            $this->logger->error('Search index update failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        if ($entity instanceof IsIndexableInterface && !$entity->isIndexable()) {
            return;
        }

        $pageData = $entity->getPageData();
        foreach ($pageData->getAvailableLanguages() as $lang) {
            try {
                $this->upsertForLang($entity, $lang);
            } catch (\Exception $e) {
                $this->logger->error('Search index update failed', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        if (!$this->enabled) {
            return;
        }

        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        $pageData = $entity->getPageData();
        foreach ($pageData->getAvailableLanguages() as $lang) {
            try {
                $index = $this->searcher->getCollectionNameForLanguage($lang);
                $this->deleteFromIndex($entity, $index);
            } catch (\Exception $e) {
                $this->logger->error('Search index update failed', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function updateDocumentIndexes(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        if ($args->hasChangedField('pageData')) {
            $langsToRemove = array_diff(
                $args->getOldValue('pageData')->getAvailableLanguages(),
                $args->getNewValue('pageData')->getAvailableLanguages()
            );
            foreach ($langsToRemove as $lang) {
                $index = $this->searcher->getCollectionNameForLanguage($lang);
                $this->deleteFromIndex($entity, $index);
            }
        }

        $pageData = $entity->getPageData();

        foreach ($pageData->getAvailableLanguages() as $lang) {
            $this->upsertForLang($entity, $lang);
        }
    }

    private function deleteFromIndex(PageInterface $page, string $index): void
    {
        $this->logger->info(sprintf('Unindexing document %s (%s)', $page->getId(), $index));
        if (!$page->getId()) {
            $this->logger->warning('Document has no id, skipping deletion.');

            return;
        }
        try {
            $this->searcher->deleteDocument($index, (string) $page->getId());
        } catch (\Exception $e) {
            $this->logger->warning('Document deletion raised error', ['message' => $e->getMessage()]);
        }
    }

    private function upsertForLang(PageInterface $page, string $lang): void
    {
        $this->logger->info(sprintf('Indexing document %s (%s)', $page->getId(), $lang));
        if (!$page->getId()) {
            $this->logger->warning('Document has no id, skipping upsert.');

            return;
        }

        $translatedPage = $this->searcher->createSearchDocument($page, $lang);
        $index = $this->searcher->getCollectionNameForLanguage($lang);
        $this->createCollectionIfNotExists($index);

        try {
            $this->searcher->upsertDocument($index, $translatedPage);
        } catch (\Exception $e) {
            $this->logger->warning('Document deletion raised error', ['message' => $e->getMessage()]);
        }
    }

    private function createCollectionIfNotExists(string $name): void
    {
        // Load the list of collections if we haven't already
        if (is_null($this->collections)) {
            $this->collections = array_map(function ($collection) {
                return $collection['name'];
            }, $this->searcher->listCollections());
        }

        // Create the index if it's not in the list
        if (!in_array($name, $this->collections)) {
            $this->logger->info('Creating collection ' . $name);
            $this->searcher->createCollection($name);
            $this->collections[] = $name;
        }
    }
}
