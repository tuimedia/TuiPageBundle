<?php
namespace Tui\PageBundle\Command;

use ElasticSearcher\ElasticSearcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\Search\TranslatedPageFactory;
use Tui\PageBundle\Search\TranslatedPageIndexFactory;

class ReindexCommand extends Command
{
    protected static $defaultName = 'pages:reindex';
    private $indexFactory;
    private $logger;
    private $pageFactory;
    private $pageRepository;
    private $searcher;
    private $serializer;
    private $languageIndex = [];

    public function __construct(
        SerializerInterface $serializer,
        LoggerInterface $logger,
        ElasticSearcher $searcher,
        PageRepository $pageRepository,
        TranslatedPageFactory $pageFactory,
        TranslatedPageIndexFactory $indexFactory
    ) {
        $this->indexFactory = $indexFactory;
        $this->logger = $logger;
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->searcher = $searcher;
        $this->serializer = $serializer;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Recreate search index')
            // ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('no-reset', null, InputOption::VALUE_NONE, 'Don\'t delete existing indexes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $indexManager = $this->searcher->indicesManager();
        $documentManager = $this->searcher->documentsManager();

        // Get all pages
        $pages = $this->pageRepository->findAll();
        $this->logger->info(sprintf('Fetched %d pages', count($pages)));

        $languages = $this->getLanguages($pages);
        $this->logger->info(sprintf('Found %d languages: %s', count($languages), join(', ', $languages)));

        // Prepare (reset or update) each language index
        foreach ($languages as $language) {
            // Create and register an index manager
            $index = $this->indexFactory->createTranslatedPageIndex($language);
            $this->languageIndex[$language] = $index->getName();
            $this->logger->info(sprintf('Preparing index %s', $index->getName()));
            $indexManager->register($index);

            // Create if it doesn't exist
            if (!$indexManager->exists($index->getName())) {
                $this->logger->info('Index not found, creating');
                $indexManager->create($index->getName());
                continue;
            }

            // Update but don't reset if the --no-reset option is given
            if ($input->getOption('no-reset')) {
                $this->logger->info('Updating index (no reset)');
                $indexManager->update($index->getName());
                continue;
            }

            // Else delete and recreate
            $this->logger->info('Recreating index');
            $indexManager->delete($index->getName());
            $indexManager->create($index->getName());
        }

        // Process pages. Create a translated search document for each available language and index it
        foreach ($pages as $page) {
            $this->logger->info(vsprintf('Indexing page id %s, state %s, slug %s', [
                $page->getId(),
                $page->getState(),
                $page->getSlug(),
            ]));
            foreach ($page->getPageData()->getAvailableLanguages() as $language) {
                $translatedPage = $this->serializer->normalize($this->pageFactory->createFromPage($page, $language));
                $this->logger->debug('Translated page: ' . $this->serializer->encode($translatedPage, 'json'));
                $documentManager->updateOrIndex($this->languageIndex[$language], 'pages', $page->getId(), $translatedPage);
            }
        }


        // $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }

    private function getLanguages(array $pages): array
    {
        // Build list of languages
        return array_reduce($pages, function ($accumulator, $page) {
            $extraLanguages = array_diff($page->getPageData()->getAvailableLanguages(), $accumulator);

            if ($extraLanguages) {
                array_push($accumulator, ...$extraLanguages);
            }

            return $accumulator;
        }, []);
    }
}
