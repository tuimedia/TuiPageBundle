<?php
namespace Tui\PageBundle\Search;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use ElasticSearcher\ElasticSearcher;
use ElasticSearcher\Managers\DocumentsManager;
use ElasticSearcher\Managers\IndicesManager;
use ElasticSearcher\Abstracts\AbstractIndex;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Search\TranslatedPageFactory;
use Tui\PageBundle\Search\TranslatedPageIndexFactory;

class SearchSubscriber implements EventSubscriber
{
    private $searcher;
    private $logger;
    private $normalizer;
    private $indexFactory;
    private $pageFactory;
    private $enabled;

    /** @var AbstractIndex[] */
    private $languageIndex = [];

    /** @var IndicesManager */
    private $indexManager;

    /** @var DocumentsManager */
    private $documentManager;


    public function __construct(
        ElasticSearcher $searcher,
        NormalizerInterface $normalizer,
        LoggerInterface $logger,
        TranslatedPageFactory $pageFactory,
        TranslatedPageIndexFactory $indexFactory,
        bool $searchEnabled
    )
    {
        if (!$searchEnabled) {
            return;
        }

        $this->searcher = $searcher;
        $this->normalizer = $normalizer;
        $this->logger = $logger;
        $this->indexFactory = $indexFactory;
        $this->pageFactory = $pageFactory;
        $this->enabled = $searchEnabled;

        $this->indexManager = $this->searcher->indicesManager();
        $this->documentManager = $this->searcher->documentsManager();
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

        $this->updateDocumentIndexes($args);
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

        $pageData = $entity->getPageData();
        foreach ($pageData->getAvailableLanguages() as $lang) {
            $this->upsertToIndex($entity, $lang);
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
            $this->deleteFromIndex($entity, $lang);
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
                $index = $this->getIndexForLanguage($lang);
                $this->deleteFromIndex($entity, $lang);
            }
        }

        $pageData = $entity->getPageData();

        foreach ($pageData->getAvailableLanguages() as $lang) {
            $index = $this->getIndexForLanguage($lang);
            $this->upsertToIndex($entity, $lang);
        }
    }

    private function deleteFromIndex(PageInterface $page, string $lang): void
    {
        $this->logger->info(sprintf('Unindexing document %s (%s)', $page->getId(), $lang));
        if (!$page->getId()) {
            $this->logger->warning('Document has no id, skipping deletion.');
            return;
        }
        $this->documentManager->delete(
            $this->getIndexForLanguage($lang)->getName(),
            'pages',
            (string) $page->getId()
        );
    }

    private function upsertToIndex(PageInterface $page, string $lang): void
    {
        $this->logger->info(sprintf('Indexing document %s (%s)', $page->getId(), $lang));
        if (!$page->getId()) {
            $this->logger->warning('Document has no id, skipping upsert.');
            return;
        }
        $translatedPage = $this->normalizer->normalize($this->pageFactory->createFromPage($page, $lang));

        $this->documentManager->updateOrIndex(
            $this->getIndexForLanguage($lang)->getName(),
            'pages',
            (string) $page->getId(),
           (array) $translatedPage
        );
    }

    private function getIndexForLanguage(string $language): AbstractIndex
    {
        if (isset($this->languageIndex[$language])) {
            return $this->languageIndex[$language];
        }

        // Create and register an index manager
        $index = $this->indexFactory->createTranslatedPageIndex($language);
        $this->logger->info(sprintf('Registering index %s', $index->getName()));
        $this->indexManager->register($index);

        if ($this->indexManager->exists($index->getName())) {
            $this->logger->info('Index exists. Updating.');
            $this->indexManager->update($index->getName());
        } else {
            $this->logger->info('Index does not exist. Creating.');
            $this->indexManager->create($index->getName());
        }

        $this->languageIndex[$language] = $index;

        return $index;
    }
}
