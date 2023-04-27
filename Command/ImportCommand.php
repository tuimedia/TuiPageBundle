<?php
namespace Tui\PageBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Tui\PageBundle\Controller\TuiPageResponseTrait;
use Tui\PageBundle\PageSchema;
use Tui\PageBundle\Repository\PageRepository;
use Tui\PageBundle\TranslationHandler;

class ImportCommand extends Command
{
    use TuiPageResponseTrait;

    protected static $defaultName = 'pages:import-xliff';
    protected static $defaultDescription = 'Import XLIFF translations';

    public function __construct(
        private LoggerInterface $logger,
        private PageRepository $pageRepository,
        private PageSchema $pageSchema,
        private SerializerInterface $serializer,
        private TranslationHandler $translationHandler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'XLIFF or ZIP archive')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Save as a new page in this state')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $destinationState = $input->getOption('state');
        if (is_array($destinationState)) {
            $io->error('Provide only one destination state');

            return Command::FAILURE;
        }
        if ($destinationState && !is_string($destinationState)) {
            $io->error('Invalid destination state name');

            return Command::FAILURE;
        }

        $argFile = (string) filter_var($input->getArgument('file'), FILTER_SANITIZE_STRING);
        if (!file_exists($argFile)) {
            $io->error('File not found');

            return Command::FAILURE;
        }

        // Build a list of files to process
        $files = [];
        if (preg_match('/\.zip$/', $argFile)) {
            if (!$this->hasZipSupport()) {
                $this->logger->error('Zip file given but the PHP zip extension not installed or enabled');
                $io->error('Zip file given but the PHP zip extension is not installed or enabled');

                return Command::FAILURE;
            }
            $zip = new \ZipArchive();
            $result = $zip->open($argFile, \ZipArchive::CHECKCONS);
            if ($result !== true) {
                $this->logger->error('Failed to open zip archive', [
                    'errorCode' => $result,
                    'message' => $this->getZipErrorMessage($result),
                ]);
                $io->error('Failed to create zip archive: ' . $this->getZipErrorMessage($result));

                return (int) $result;
            }

            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $innerFilename = $zip->getNameIndex($i);
                if (preg_match('/\.(?:xlf|xliff)$/', (string) $innerFilename)) {
                    $files[] = sprintf('zip://%s#%s', $argFile, $innerFilename);
                }
            }

            $zip->close();
        } else {
            $files[] = $argFile;
        }

        foreach ($files as $filename) {
            $this->logger->info('Importing translation from', ['filename' => $filename]);

            $content = file_get_contents($filename);
            if ($content === false) {
                $io->error('Unable to read file');
                continue;
            }
            $original = $this->parseOriginal($content);
            $this->logger->debug('Parsed original file attribute', $original);

            $page = $this->pageRepository->findOneBy([
                'slug' => $original['slug'],
                'state' => $original['state'],
            ]);

            if (!$page) {
                $this->logger->error('Unable to find original page, skipping', $original);
                continue;
            }

            if ($destinationState) {
                $this->logger->info('Destination state provided, cloning page', ['state' => $destinationState]);
                $newPageExists = $this->pageRepository->findOneBy([
                    'slug' => $original['slug'],
                    'state' => $destinationState,
                ]);

                if ($newPageExists) {
                    $this->logger->error('Page already exists in destination state, skipping', ['slug' => $original['slug']]);
                    continue;
                }

                $page = clone $page;
                // Set a temporary revision so the page will validate
                $page->getPageData()->setRevision('ffffffff-ffff-ffff-ffff-ffffffffffff');
                $page->setState((string) $destinationState);
            }

            $this->translationHandler->importXliff($page, $content);
            $pageJson = $this->generateTuiPageJson($page, $this->serializer, ['pageGet']);

            // Validate input
            $errors = $this->pageSchema->validate($pageJson);
            if ($errors) {
                $this->logger->error('Translated page failed validation, skipping', [
                    'slug' => $original['slug'],
                    'errors' => (array) $errors,
                ]);
                continue;
            }
            // Remove the temporary revision
            if ($destinationState) {
                $page->getPageData()->setRevision(null);
            }

            $this->pageRepository->save($page);
        }

        return Command::SUCCESS;
    }

    private function getZipErrorMessage(int $code): string
    {
        return match ($code) {
            \ZipArchive::ER_INCONS => 'Zip file is corrupted',
            \ZipArchive::ER_INVAL => 'Invalid filename',
            \ZipArchive::ER_OPEN => 'Unable to open file',
            \ZipArchive::ER_MEMORY => 'Memory error',
            default => 'Unknown error',
        };
    }

    private function parseOriginal(string $content): array
    {
        // Load & check
        $previous = libxml_use_internal_errors(true);
        if (false === $doc = \simplexml_load_string($content)) {
            libxml_use_internal_errors($previous);
            $libxmlError = libxml_get_last_error();
            throw new \RuntimeException(sprintf('Could not read XML source: %s', $libxmlError ? $libxmlError->message : '[no error message]'));
        }
        libxml_use_internal_errors($previous);

        // Register namespace(s)
        $doc->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        // Get the file tag for target language, revision, etc
        $files = $doc->xpath('//xliff:file[1]');
        if (!$files || !isset($files[0])) {
            throw new \Exception('No XLIFF file element found');
        }
        $original = (string) $files[0]->attributes()['original'];

        $query = (string) parse_url($original, PHP_URL_QUERY);
        parse_str($query, $params);
        $path = parse_url($original, PHP_URL_PATH);
        if (!$path) {
            throw new \Exception('Unable to parse original filename');
        }
        $slug = basename($path);

        return [
            'slug' => $slug,
            'state' => $params['state'] ?? '',
            'revision' => $params['revision'] ?? '',
        ];
    }

    private function hasZipSupport(): bool
    {
        return class_exists('\\ZipArchive');
    }
}
