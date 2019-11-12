<?php
namespace Tui\PageBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\TranslationHandler;

class ExportCommand extends Command
{
    protected static $defaultName = 'pages:export-xliff';
    private $logger;
    private $pageRepository;
    private $translationHandler;

    public function __construct(
        LoggerInterface $logger,
        PageRepository $pageRepository,
        TranslationHandler $translationHandler
    ) {
        $this->logger = $logger;
        $this->pageRepository = $pageRepository;
        $this->translationHandler = $translationHandler;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Create an XLIFF export for translation')
            ->addArgument('target_language', InputArgument::REQUIRED, 'Target language')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Page state', 'live')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Output filename')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $state = $input->getOption('state');
        if (!$state) {
            $state = 'live';
        }
        $targetLanguage = $input->getArgument('target_language');

        $pages = $this->pageRepository->findBy([
            'state' => $state,
        ]);

        if (!count($pages)) {
            $this->logger->info('No pages found, exiting early');
            return null;
        }

        $filename = $input->getOption('file');
        if (!$filename) {
            $filename = vsprintf('translations.%s.%s.zip', [
                $state,
                $targetLanguage,
            ]);
        }
        $zip = new \ZipArchive;
        $result = $zip->open((string) filter_var($filename, FILTER_SANITIZE_STRING), \ZipArchive::CREATE | \ZipArchive::EXCL);
        if ($result !== true) {
            $this->logger->error('Failed to create zip archive', [
                'errorCode' => $result,
                'message' => $this->getZipErrorMessage($result),
            ]);
            $io->error('Failed to create zip archive: ' . $this->getZipErrorMessage($result));
            return $result;
        }

        foreach ($pages as $page) {
            if ($this->pageRepository->ensureRowIds($page)) {
                $this->logger->info('Row ids added to page', ['slug' => $page->getSlug()]);
                $this->pageRepository->save($page);
            }

            $this->logger->info('Creating translation file', ['slug' => $page->getSlug()]);
            $file = $this->translationHandler->generateXliff($page, $targetLanguage);
            $zip->addFromString(vsprintf('%s.%s.xliff', [
                $page->getSlug(),
                $targetLanguage,
            ]), $file);
        }
        $zip->close();

        // $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }

    private function getZipErrorMessage($code)
    {
        switch($code) {
            case \ZipArchive::ER_EXISTS: return 'File already exists';
            case \ZipArchive::ER_INVAL: return 'Invalid filename';
            case \ZipArchive::ER_OPEN: return 'Unable to open file';
            case \ZipArchive::ER_MEMORY: return 'Memory error';
            default: return 'Unknown error';
        };
    }
}
