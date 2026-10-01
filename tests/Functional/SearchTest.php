<?php

namespace Tui\PageBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tui\PageBundle\Search\TypesenseClient;

/**
 * Indexing and search against a real Typesense server. Set TYPESENSE_URL (and TYPESENSE_API_KEY
 * if it isn't `tui-page-test`) to run these; see CONTRIBUTING.md.
 */
class SearchTest extends FunctionalTestCase
{
    private string $prefix;

    protected function setUp(): void
    {
        if (!getenv('TYPESENSE_URL')) {
            self::markTestSkipped('Set TYPESENSE_URL to run the search tests against a Typesense server');
        }

        // Collections are named <prefix>_<language>, so each test gets its own
        $this->prefix = 'tuitest' . bin2hex(random_bytes(4));
        $this->bootApp(['search' => true, 'search_index' => $this->prefix]);
    }

    protected function tearDown(): void
    {
        if (isset($this->prefix) && self::$booted) {
            $client = $this->typesense();
            foreach ($client->listCollections() as $collection) {
                if (str_starts_with($collection['name'], $this->prefix . '_')) {
                    $client->deleteCollection($collection['name']);
                }
            }
        }
        parent::tearDown();
    }

    public function testSavingAPageIndexesEveryLanguage(): void
    {
        $page = self::fixture('page.json');
        $page['pageData']['availableLanguages'] = ['en_GB', 'fr'];
        $page['pageData']['content']['langData']['fr'] = ['blk1' => ['title' => 'Titre du bloc']];
        $created = $this->createPage($page);

        self::assertSame([$this->prefix . '_en_GB', $this->prefix . '_fr'], $this->collectionNames());

        $results = $this->search('block', 'en_GB');
        self::assertSame(1, $results['total']);
        self::assertSame($created['id'], $results['results'][0]['id']);
        self::assertSame('hello-world', $results['results'][0]['slug']);

        self::assertSame(1, $this->search('titre', 'fr')['total']);
        self::assertSame(0, $this->search('block', 'en_GB', 'draft')['total'], 'Results are filtered by state');
    }

    public function testCollectionsUseTheTransformerSchema(): void
    {
        $this->createPage(self::fixture('page.json'));

        $collection = $this->typesense()->getClient()->collections[$this->prefix . '_en_GB']->retrieve();
        self::assertContains('blockCount', array_column($collection['fields'], 'name'));
    }

    public function testEditsAreReindexed(): void
    {
        $created = $this->createPage(self::fixture('page.json'));

        $edit = self::asUpdate($created);
        $edit['pageData']['content']['langData']['en_GB']['blk1']['title'] = 'Completely different wording';
        [$status, $body] = $this->request('PUT', '/api/pages/hello-world?state=live', $edit);
        self::assertSame(200, $status, self::describe($body));

        self::assertSame(1, $this->search('wording', 'en_GB')['total']);
        self::assertSame(0, $this->search('block', 'en_GB')['total']);
    }

    public function testDroppingALanguageUnindexesThatTranslation(): void
    {
        $page = self::fixture('page.json');
        $page['pageData']['availableLanguages'] = ['en_GB', 'fr'];
        $page['pageData']['content']['langData']['fr'] = ['blk1' => ['title' => 'Titre du bloc']];
        $created = $this->createPage($page);
        self::assertSame(1, $this->search('titre', 'fr')['total']);

        $edit = self::asUpdate($created);
        $edit['pageData']['availableLanguages'] = ['en_GB'];
        [$status, $body] = $this->request('PUT', '/api/pages/hello-world?state=live', $edit);
        self::assertSame(200, $status, self::describe($body));

        self::assertSame(0, $this->search('titre', 'fr')['total']);
        self::assertSame(1, $this->search('block', 'en_GB')['total']);
    }

    public function testDeletingAPageUnindexesIt(): void
    {
        $this->createPage(self::fixture('page.json'));
        self::assertSame(1, $this->search('block', 'en_GB')['total']);

        [$status] = $this->request('DELETE', '/api/pages/hello-world?state=live');
        self::assertSame(204, $status);

        self::assertSame(0, $this->search('block', 'en_GB')['total']);
    }

    public function testReindexRebuildsTheCollections(): void
    {
        $this->createPage(self::fixture('page.json'));
        $second = self::fixture('page.json');
        $second['slug'] = 'second-page';
        $this->createPage($second);
        $extended = self::fixture('extended-page.json');
        $extended['id'] = null;
        $this->createPage($extended);

        $this->typesense()->deleteCollection($this->prefix . '_en_GB');
        self::assertSame([], $this->collectionNames());

        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel not booted'));
        $reindex = new CommandTester($application->find('pages:reindex'));
        self::assertSame(0, $reindex->execute([]), $reindex->getDisplay());

        self::assertSame([$this->prefix . '_en_GB'], $this->collectionNames());
        $results = $this->search('block', 'en_GB');
        self::assertSame(2, $results['total']);
        $slugs = array_column($results['results'], 'slug');
        sort($slugs);
        self::assertSame(['hello-world', 'second-page'], $slugs);
    }

    /**
     * @return array{results: list<array<string, mixed>>, total: int}
     */
    private function search(string $terms, string $language, string $state = 'live'): array
    {
        [$status, $body] = $this->request('GET', '/api/search?' . http_build_query(['q' => $terms, 'language' => $language, 'state' => $state]));
        self::assertSame(200, $status, self::describe($body));

        return $body;
    }

    /**
     * @return list<string>
     */
    private function collectionNames(): array
    {
        $names = array_values(array_filter(
            array_column($this->typesense()->listCollections(), 'name'),
            fn (string $name) => str_starts_with($name, $this->prefix . '_'),
        ));
        sort($names);

        return $names;
    }

    private function typesense(): TypesenseClient
    {
        $client = static::getContainer()->get(TypesenseClient::class);
        self::assertInstanceOf(TypesenseClient::class, $client);

        return $client;
    }
}
