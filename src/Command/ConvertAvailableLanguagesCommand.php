<?php
namespace Tui\PageBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrites PageData.availableLanguages from Doctrine's PHP-serialised `array` format to JSON.
 *
 * Works on raw rows through DBAL so it can run before the column is migrated, while the stored
 * values can't be hydrated by the `json` mapping. Values that are already JSON are left alone,
 * so it's safe to run more than once.
 */
#[AsCommand('pages:convert-available-languages', description: 'Convert stored availableLanguages values from PHP-serialised arrays to JSON')]
class ConvertAvailableLanguagesCommand extends Command
{
    /**
     * @param class-string $pageDataClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $pageDataClass,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $metadata = $this->entityManager->getClassMetadata($this->pageDataClass);
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();

        $table = $quoteStrategy->getTableName($metadata, $platform);
        $idColumn = $quoteStrategy->getColumnName($metadata->getSingleIdentifierFieldName(), $metadata, $platform);
        $languagesColumn = $quoteStrategy->getColumnName('availableLanguages', $metadata, $platform);

        $rows = $connection->fetchAllNumeric(sprintf('SELECT %s, %s FROM %s', $idColumn, $languagesColumn, $table));

        $converted = 0;
        $skipped = 0;
        $failed = [];

        if (!$dryRun) {
            $connection->beginTransaction();
        }

        try {
            foreach ($rows as [$id, $value]) {
                if (!is_string($value) || $this->isJsonList($value)) {
                    ++$skipped;
                    continue;
                }

                $languages = @unserialize($value, ['allowed_classes' => false]);
                if (!is_array($languages)) {
                    $failed[] = (string) $id;
                    continue;
                }

                if (!$dryRun) {
                    $connection->update($table, [
                        $languagesColumn => json_encode(array_values($languages), JSON_THROW_ON_ERROR),
                    ], [
                        $idColumn => $id,
                    ]);
                }
                ++$converted;
            }

            if (!$dryRun) {
                $connection->commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun) {
                $connection->rollBack();
            }

            throw $e;
        }

        $io->table(['Rows', 'Count'], [
            [$dryRun ? 'Would convert' : 'Converted', $converted],
            ['Already JSON (skipped)', $skipped],
            ['Unreadable', count($failed)],
        ]);

        if ($failed) {
            $io->error(sprintf('Could not read availableLanguages for these %s rows: %s', $this->pageDataClass, implode(', ', $failed)));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function isJsonList(string $value): bool
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($decoded) && array_is_list($decoded);
    }
}
