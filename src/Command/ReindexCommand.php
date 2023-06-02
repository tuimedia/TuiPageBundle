<?php
namespace Tui\PageBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tui\PageBundle\Entity\IsIndexableInterface;
use Tui\PageBundle\Repository\PageDataRepository;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Search\TypesenseClient;

class ReindexCommand extends Command
{
    protected static $defaultName = 'pages:reindex';
    protected static $defaultDescription = 'Recreate search index'; // https://typesense.org/docs/0.22.1/api/documents.html#configure-batch-size
    private array $indexingQueue = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TypesenseClient $searcher,
        private readonly PageRepository $pageRepository,
        private readonly PageDataRepository $pageDataRepository,
        private readonly int $bulkIndexThreshold = 40
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('no-reset', null, InputOption::VALUE_NONE, 'Don\'t delete existing indexes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Set up the indexes for each language
        $languages = $this->pageDataRepository->getAllLanguages();

        // Set up indexes
        if (!$input->getOption('no-reset')) {
            $collections = array_map(fn ($collection) => $collection['name'], $this->searcher->listCollections());

            foreach ($languages as $language) {
                $collectionName = $this->searcher->getCollectionNameForLanguage($language);
                if (in_array($collectionName, $collections)) {
                    $this->logger->info('Deleting existing collection ' . $collectionName);
                    $this->searcher->deleteCollection($collectionName);
                }
                $this->logger->info('Creating collection ' . $collectionName);
                $this->searcher->createCollection($collectionName);
            }
        }

        // Get all pages
        $count = $this->pageRepository->count([]);
        $limit = $this->bulkIndexThreshold;

        $resultOffsets = $count > $limit ?
            range(0, $count, $limit) :
            // Don't bulk update more than once if we have fewer pages than the update limit
            [0];

        foreach ($resultOffsets as $offset) {
            $batch = [];
            $pages = $this->pageRepository->getPagesForIndexing($this->bulkIndexThreshold, $offset);
            foreach ($pages as $page) {
                if ($page instanceof IsIndexableInterface && !$page->isIndexable()) {
                    continue;
                }
                foreach ($page->getPageData()->getAvailableLanguages() as $language) {
                    $translatedPage = $this->searcher->createSearchDocument($page, $language);
                    if (!isset($batch[$language])) {
                        $batch[$language] = [];
                    }
                    $this->queueIndex($language, $translatedPage);
                }
            }
        }

        $this->drainQueue();

        return Command::SUCCESS;
    }

    private function queueIndex(string $language, array $translatedPage): void
    {
        $this->logger->debug('Adding document to indexing queue');
        if (!array_key_exists($language, $this->indexingQueue)) {
            $this->indexingQueue[$language] = [];
        }
        array_push($this->indexingQueue[$language], $translatedPage);
        if ((is_countable($this->indexingQueue[$language]) ? count($this->indexingQueue[$language]) : 0) >= $this->bulkIndexThreshold) {
            $this->logger->debug('Triggering bulk import');
            $this->bulkIndex($language, $this->indexingQueue[$language]);
            $this->indexingQueue[$language] = [];
        }
    }

    private function bulkIndex(string $language, array $docs): void
    {
        if (!count($docs)) {
            $this->logger->info('No remaining documents; skipping bulk import');
            return;
        }
        $this->logger->info('Performing bulk import');
        $collectionName = $this->searcher->getCollectionNameForLanguage($language);
        $this->searcher->bulkImport($collectionName, $docs);
    }

    private function drainQueue(): void
    {
        $this->logger->info('Draining indexing queue');
        foreach ($this->indexingQueue as $index => $queue) {
            $this->bulkIndex($index, $queue);
            $this->indexingQueue[$index] = [];
        }
    }
}
