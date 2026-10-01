<?php

namespace Tui\PageBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tui\PageBundle\Repository\PageDataRepository;
use Tui\PageBundle\Tests\App\Entity\PageData;

/**
 * The 0.11 → 0.12 upgrade, following UPGRADE-0.12.md, on whichever database DATABASE_URL points at.
 *
 * 0.11 stored PageData.availableLanguages with Doctrine's `array` type: PHP serialize() output in
 * a text column. Each test recreates that state, then converts and migrates.
 */
class LegacyUpgradeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->bootApp();
    }

    public function testConvertThenMigrate(): void
    {
        $this->createPage(self::fixture('page.json'));
        $french = self::fixture('page.json');
        $french['slug'] = 'french-page';
        $french['pageData']['availableLanguages'] = ['en_GB', 'fr'];
        $this->createPage($french);
        $extended = self::fixture('extended-page.json');
        $extended['id'] = null;
        $this->createPage($extended);

        $legacy = [
            'hello-world' => serialize(['en_GB']),
            // array_unique() without array_values() left gaps in the keys
            'french-page' => serialize([0 => 'en_GB', 2 => 'fr']),
            'extended-example-page' => serialize(['en_GB']),
        ];
        $this->makeLegacy($legacy);

        [$status] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(500, $status, 'Unconverted rows cannot be read by the json mapping');

        $before = $this->storedLanguages();
        $dryRun = $this->command();
        self::assertSame(0, $dryRun->execute(['--dry-run' => true]));
        self::assertMatchesRegularExpression('/Would convert\s+3\b/', $dryRun->getDisplay());
        self::assertSame($before, $this->storedLanguages(), 'A dry run writes nothing');

        $convert = $this->command();
        self::assertSame(0, $convert->execute([]), $convert->getDisplay());
        self::assertMatchesRegularExpression('/Converted\s+3\b/', $convert->getDisplay());
        self::assertSame([
            'extended-example-page' => '["en_GB"]',
            'french-page' => '["en_GB","fr"]',
            'hello-world' => '["en_GB"]',
        ], $this->storedLanguages());

        $again = $this->command();
        self::assertSame(0, $again->execute([]));
        self::assertMatchesRegularExpression('/Converted\s+0\b/', $again->getDisplay());
        self::assertMatchesRegularExpression('/Already JSON \(skipped\)\s+3\b/', $again->getDisplay());

        // Pages load straight after converting, before the column itself is migrated
        foreach (array_keys($legacy) as $slug) {
            [$status, $page] = $this->request('GET', "/api/pages/$slug?state=live");
            self::assertSame(200, $status, self::describe($page));
        }

        $this->migrate();

        [$status, $page] = $this->request('GET', '/api/pages/french-page?state=live');
        self::assertSame(200, $status);
        self::assertSame(['en_GB', 'fr'], $page['pageData']['availableLanguages']);

        $languages = $this->pageDataRepository()->getAllLanguages();
        sort($languages);
        self::assertSame(['en_GB', 'fr'], $languages);
    }

    public function testUnreadableRowsAreReportedAndTheRestConverted(): void
    {
        $this->createPage(self::fixture('page.json'));
        $broken = self::fixture('page.json');
        $broken['slug'] = 'broken-page';
        $this->createPage($broken);

        $this->makeLegacy([
            'hello-world' => serialize(['en_GB']),
            'broken-page' => 'not serialised at all',
        ]);

        $convert = $this->command();
        self::assertSame(1, $convert->execute([]));
        self::assertMatchesRegularExpression('/Unreadable\s+1\b/', $convert->getDisplay());
        self::assertStringContainsString($this->revisionOf('broken-page'), $convert->getDisplay());
        self::assertSame('["en_GB"]', $this->storedLanguages()['hello-world']);
    }

    /**
     * Put availableLanguages back the way 0.11 stored it.
     *
     * @param array<string, string> $valuesBySlug serialised values to store, by page slug
     */
    private function makeLegacy(array $valuesBySlug): void
    {
        $connection = $this->connection();
        $platform = $connection->getDatabasePlatform();

        // DBAL 3's `array` type: LONGTEXT on MySQL/MariaDB, TEXT on Postgres, CLOB (as json already is) on SQLite
        if ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement("ALTER TABLE page_data MODIFY availableLanguages LONGTEXT NOT NULL COMMENT '(DC2Type:array)'");
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $connection->executeStatement('ALTER TABLE page_data ALTER availableLanguages TYPE TEXT');
            $connection->executeStatement("COMMENT ON COLUMN page_data.availableLanguages IS '(DC2Type:array)'");
        }

        foreach ($valuesBySlug as $slug => $value) {
            $connection->executeStatement('UPDATE page_data SET availableLanguages = ? WHERE revision = ?', [$value, $this->revisionOf($slug)]);
        }
    }

    /**
     * The consumer's doctrine:migrations:diff + migrate, with the edit UPGRADE-0.12.md asks
     * Postgres users to make.
     */
    private function migrate(): void
    {
        $em = $this->entityManager();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $sql = $tool->getUpdateSchemaSql($metadata);

        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            // DBAL 3 writes the column name in lower case; Postgres folds unquoted names, so it's the same statement
            $isGenerated = static fn (string $statement): bool => strcasecmp($statement, 'ALTER TABLE page_data ALTER availableLanguages TYPE JSON') === 0;
            self::assertNotEmpty(array_filter($sql, $isGenerated), 'UPGRADE-0.12.md quotes this statement; update the doc if it changes');
            $sql = array_map(static fn (string $statement) => $isGenerated($statement) ? 'ALTER TABLE page_data ALTER availableLanguages TYPE JSON USING availableLanguages::json' : $statement, $sql);
        }

        foreach ($sql as $statement) {
            $this->connection()->executeStatement($statement);
        }

        self::assertSame([], $tool->getUpdateSchemaSql($metadata), 'The schema is in sync after migrating');
    }

    private function command(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel not booted'));

        return new CommandTester($application->find('pages:convert-available-languages'));
    }

    /**
     * @return array<string, string> stored availableLanguages values, by page slug
     */
    private function storedLanguages(): array
    {
        $rows = $this->connection()->fetchAllNumeric('SELECT p.slug, pd.availableLanguages FROM page p JOIN page_data pd ON pd.revision = p.pageData_id ORDER BY p.slug');

        return array_column($rows, 1, 0);
    }

    private function revisionOf(string $slug): string
    {
        return (string) $this->connection()->fetchOne('SELECT pageData_id FROM page WHERE slug = ?', [$slug]);
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    private function pageDataRepository(): PageDataRepository
    {
        $repository = $this->entityManager()->getRepository(PageData::class);
        self::assertInstanceOf(PageDataRepository::class, $repository);

        return $repository;
    }
}
