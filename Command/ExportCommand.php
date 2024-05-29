<?php
namespace Tui\PageBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\TranslationHandler;

#[AsCommand('pages:export-xliff', description: 'Create an XLIFF export for translation')]
class ExportCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface    $logger,
        private readonly PageRepository     $pageRepository,
        private readonly TranslationHandler $translationHandler
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Create an XLIFF export for translation')
            ->addArgument('target_language', InputArgument::REQUIRED, 'Target language')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Page state', 'live')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Output filename');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
            return 0;
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
            return (int) $result;
        }

        foreach ($pages as $page) {
            $this->logger->info('Creating translation file', ['slug' => $page->getSlug()]);
            $file = $this->translationHandler->generateXliff($page, $targetLanguage);
            $zip->addFromString(vsprintf('%s.%s.xliff', [
                $page->getSlug(),
                $targetLanguage,
            ]), $file);
        }
        $zip->close();

        return 0;
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
