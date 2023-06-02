<?php
namespace Tui\PageBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Repository\PageRepository;

class UpgradeCommand extends Command
{
    final public const CURRENT_VERSION = 2;
    protected static $defaultName = 'pages:upgrade';
    protected static $defaultDescription = 'Upgrade page data to current version';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PageRepository $pageRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pages = $this->pageRepository->findAll();

        if (!count($pages)) {
            $this->logger->info('No pages found, exiting early');

            return Command::SUCCESS;
        }

        foreach ($pages as $page) {
            $content = $page->getPageData()->getContent();
            $version = $content['schemaVersion'] ?? 1;
            while ($version < self::CURRENT_VERSION) {
                $this->logger->info(sprintf('Migrating page %s to version %d', $page->getId(), $version + 1));
                $migrateMethod = sprintf('migrateToVersion%d', $version + 1);
                $this->logger->debug('Migrator method: ' . $migrateMethod);
                if (!\method_exists($this, $migrateMethod)) {
                    throw new \Exception(sprintf('Missing migrator for version %d', $version + 1));
                }
                $page = \call_user_func([$this, $migrateMethod], $page);
                $this->logger->debug('Migrated page', [
                    'version' => $version + 1,
                    'content' => $page->getPageData()->getContent(),
                ]);
                ++$version;
            }
            $this->pageRepository->save($page);
        }

        return Command::SUCCESS;
    }

    private function migrateToVersion2(PageInterface $page): PageInterface
    {
        $content = $page->getPageData()->getContent();

        // Add version
        $content['schemaVersion'] = 2;

        // Ensure rows have ids
        $content['layout'] = array_map(function ($row) {
            if (!array_key_exists('id', $row) || !$row['id']) {
                $row['id'] = $this->generateId();
            }

            return $row;
        }, $content['layout'] ?? []);

        // Move rows to blocks
        $newLayout = array_map(fn ($row) => $row['id'], $content['layout']);
        foreach ($content['layout'] as $row) {
            $content['blocks'][$row['id']] = $row;
        }
        $content['layout'] = $newLayout;

        // Remove styles prop from blocks
        $content['blocks'] = array_map(function ($block) {
            if (array_key_exists('styles', $block)) {
                unset($block['styles']);

                return $block;
            }

            return $block;
        }, $content['blocks']);

        $page->getPageData()->setContent($content);

        return $page;
    }

    private function generateId(): string
    {
        return base64_encode(random_bytes(8));
    }
}
