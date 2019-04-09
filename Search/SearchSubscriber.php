<?php
namespace Tui\PageBundle\Search;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use ElasticSearcher\ElasticSearcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Search\TranslatedPageFactory;
use Tui\PageBundle\Search\TranslatedPageIndexFactory;

class SearchSubscriber implements EventSubscriber
{
    private $searcher;
    private $logger;
    private $serializer;
    private $checkedIndexes = [];
    private $languageIndex = [];
    private $indexManager;
    private $indexFactory;
    private $documentManager;
    private $pageFactory;

    public function __construct(
        ElasticSearcher $searcher,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        TranslatedPageFactory $pageFactory,
        TranslatedPageIndexFactory $indexFactory
    )
    {
        $this->searcher = $searcher;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->indexFactory = $indexFactory;
        $this->pageFactory = $pageFactory;

        $this->indexManager = $this->searcher->indicesManager();
        $this->documentManager = $this->searcher->documentsManager();
    }

    // preUpdate - get diff of availableLanguages, ensure indexes exist, add/remove translated document from each
    // postPersist -- add new document to index
    // preRemove -- delete from indexes

    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'preUpdate',
            'preRemove',
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        $this->updateDocumentIndexes($args);
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        $pageData = $entity->getPageData();
        foreach ($pageData->getAvailableLanguages() as $lang) {
            $this->upsertToIndex($entity, $lang);
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        $pageData = $entity->getPageData();
        foreach ($pageData->getAvailableLanguages() as $lang) {
            $this->deleteFromIndex($entity, $lang);
        }
    }

    private function updateDocumentIndexes(PreUpdateEventArgs $args)
    {
        $entity = $args->getObject();
        if (!$entity instanceof PageInterface) {
            return;
        }

        // oh balls this doesn't work because availablelanguages is on the pagedata entity and those are always new
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

    private function deleteFromIndex(PageInterface $page, string $lang)
    {
        $this->logger->info(sprintf('Unindexing document %s (%s)', $page->getId(), $lang));
        $this->documentManager->delete(
            $this->getIndexForLanguage($lang)->getName(),
            'pages',
            $page->getId()
        );
    }

    private function upsertToIndex(PageInterface $page, string $lang)
    {
        $this->logger->info(sprintf('Indexing document %s (%s)', $page->getId(), $lang));
        $translatedPage = $this->serializer->normalize($this->pageFactory->createFromPage($page, $lang));

        $this->documentManager->updateOrIndex(
            $this->getIndexForLanguage($lang)->getName(),
            'pages',
            $page->getId(),
            $translatedPage
        );
    }

    private function getIndexForLanguage($language)
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
