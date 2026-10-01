<?php

namespace Tui\PageBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tui\PageBundle\Tests\App\Entity\Page;
use Tui\PageBundle\Tests\App\Entity\PageData;
use Tui\PageBundle\Tests\App\TestKernel;

#[RequiresPhpExtension('zip')]
class CommandTest extends FunctionalTestCase
{
    private string $zip;

    protected function setUp(): void
    {
        $this->bootApp();
        $this->zip = TestKernel::varDir() . '/export-' . bin2hex(random_bytes(4)) . '.zip';
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);
        parent::tearDown();
    }

    public function testExportAndReimportXliffArchive(): void
    {
        $this->createPage(self::fixture('page.json'));
        $second = self::fixture('page.json');
        $second['slug'] = 'second-page';
        $this->createPage($second);

        $export = $this->command('pages:export-xliff');
        self::assertSame(0, $export->execute(['target_language' => 'de', '--state' => 'live', '--file' => $this->zip]));

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->zip));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);
        self::assertSame(['hello-world.de.xliff', 'second-page.de.xliff'], $names);

        $import = $this->command('pages:import-xliff');
        self::assertSame(0, $import->execute(['file' => $this->zip, '--state' => 'imported']), $import->getDisplay());

        [$status, $page] = $this->request('GET', '/api/pages/second-page?state=imported');
        self::assertSame(200, $status, self::describe($page));
        self::assertSame(['en_GB', 'de'], $page['pageData']['availableLanguages']);

        [, $original] = $this->request('GET', '/api/pages/second-page?state=live');
        self::assertSame(['en_GB'], $original['pageData']['availableLanguages']);
    }

    public function testImportFailsCleanlyForAMissingFile(): void
    {
        $import = $this->command('pages:import-xliff');
        self::assertSame(1, $import->execute(['file' => $this->zip]));
        self::assertStringContainsString('File not found', $import->getDisplay());
    }

    public function testUpgradeMigratesVersionOneContent(): void
    {
        $pageData = (new PageData())
            ->setPageRef('legacy-ref')
            ->setContent([
                'layout' => [
                    ['id' => 'row1', 'component' => 'Text', 'languages' => ['en_GB'], 'styles' => ['x' => 'y'], 'blocks' => []],
                    ['component' => 'Text', 'languages' => ['en_GB'], 'blocks' => []],
                ],
                'blocks' => [],
                'langData' => ['en_GB' => []],
            ]);
        $page = (new Page())->setSlug('version-one')->setState('live')->setPageData($pageData);
        $em = $this->entityManager();
        $em->persist($page);
        $em->flush();
        $em->clear();

        $upgrade = $this->command('pages:upgrade');
        self::assertSame(0, $upgrade->execute([]));

        $content = $this->entityManager()->getRepository(Page::class)->findOneBy(['slug' => 'version-one'])?->getPageData()->getContent();
        self::assertIsArray($content);
        self::assertSame(2, $content['schemaVersion']);
        self::assertCount(2, $content['layout']);
        self::assertSame('row1', $content['layout'][0]);
        self::assertIsString($content['layout'][1], 'Rows without an id get one');
        self::assertSame(array_values($content['layout']), array_keys($content['blocks']));
        self::assertArrayNotHasKey('styles', $content['blocks']['row1']);
    }

    private function command(string $name): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel not booted'));

        return new CommandTester($application->find($name));
    }
}
