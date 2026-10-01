<?php

namespace Tui\PageBundle\Tests\Functional;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

class PageApiTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->bootApp();
    }

    public function testCreateRetrieveListAndDelete(): void
    {
        $created = $this->createPage(self::fixture('page.json'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $created['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $created['pageData']['revision']);
        self::assertSame(['en_GB'], $created['pageData']['availableLanguages']);

        [$status, $page] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(200, $status);
        self::assertSame($created['id'], $page['id']);
        self::assertIsString($page['pageData']['created']);

        [$status, $list] = $this->request('GET', '/api/pages?state=live');
        self::assertSame(200, $status);
        self::assertCount(1, $list);
        self::assertSame('hello-world', $list[0]['slug']);

        [$status, $list] = $this->request('GET', '/api/pages?state=draft');
        self::assertSame([], $list);

        [$status] = $this->request('DELETE', '/api/pages/hello-world?state=live');
        self::assertSame(204, $status);

        [$status] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(404, $status);
    }

    public function testMissingPageIsNotFound(): void
    {
        [$status] = $this->request('GET', '/api/pages/nope?state=live');
        self::assertSame(404, $status);

        [$status] = $this->request('PUT', '/api/pages/nope?state=live', self::fixture('page.json'));
        self::assertSame(404, $status);

        [$status] = $this->request('DELETE', '/api/pages/nope?state=live');
        self::assertSame(404, $status);
    }

    public function testCreateSanitisesContentAndMetadata(): void
    {
        $page = $this->createPage(self::fixture('page.json'));
        $langData = $page['pageData']['content']['langData']['en_GB'];

        // Plain strings lose their markup
        self::assertSame('Row title x', $langData['row1']['title']);

        // text/html strings keep safe markup and lose scripts
        self::assertStringContainsString('<strong>there</strong>', $langData['blk1']['copy']);
        self::assertStringNotContainsString('<script', $langData['blk1']['copy']);

        // Typed values are coerced to their schema type
        self::assertSame(3, $langData['blk1']['count']);

        // Metadata has no schema, so every string in it is stripped
        self::assertSame(['title' => 'Hello world', 'nested' => ['x' => 'y']], $page['pageData']['metadata']);
    }

    /**
     * Sanitisers are injected by tag through #[AutowireIterator]; if the attribute stops being
     * recognised they're silently skipped, so check they actually run.
     */
    public function testTaggedCustomSanitisersAreApplied(): void
    {
        $page = self::fixture('page.json');
        $page['pageData']['content']['langData']['en_GB']['blk1']['shout'] = 'quiet <em>please</em>';
        $page['pageData']['metadata']['internalNote'] = 'do not publish';
        $page['pageData']['content']['langData']['en_GB']['metadata']['internalNote'] = 'nor this';

        $created = $this->createPage($page);

        self::assertSame('QUIET PLEASE', $created['pageData']['content']['langData']['en_GB']['blk1']['shout']);
        self::assertSame('[redacted]', $created['pageData']['metadata']['internalNote']);
        self::assertSame('[redacted]', $created['pageData']['content']['langData']['en_GB']['metadata']['internalNote']);
    }

    public function testInvalidPagesAreRejected(): void
    {
        $page = self::fixture('page.json');
        unset($page['slug']);
        [$status, $body] = $this->request('POST', '/api/pages', $page);
        self::assertSame(422, $status);
        self::assertSame('Validation failed', $body['title']);

        $page = self::fixture('page.json');
        $page['pageData']['content']['blocks']['blk1']['component'] = 'NoSuchComponent';
        [$status, $body] = $this->request('POST', '/api/pages', $page);
        self::assertSame(422, $status);
        self::assertStringContainsString('No schema configured for component "NoSuchComponent"', $body['detail']);

        $page = self::fixture('page.json');
        $page['pageData']['content']['langData']['en_GB']['blk1']['title'] = str_repeat('x', 1025);
        [$status, $body] = $this->request('POST', '/api/pages', $page);
        self::assertSame(422, $status, 'Component schemas are applied to block langData');
        self::assertSame('blk1', $body['component']['id']);
        self::assertSame('title', $body['errors'][0]['path']);
    }

    public function testComponentsSharingASchemaIdAreCheckedAgainstTheirOwnSchema(): void
    {
        $page = self::fixture('page.json');
        $page['pageData']['content']['blocks']['blk1']['component'] = 'Quote';
        $page['pageData']['content']['langData']['en_GB']['blk1'] = ['title' => str_repeat('x', 21)];

        [$status, $body] = $this->request('POST', '/api/pages', $page);
        self::assertSame(422, $status, 'Quote.schema.json has the same $id as the Text schema row1 used first');
        self::assertSame('blk1', $body['component']['id']);
        self::assertSame(['path' => 'title', 'keyword' => 'maxLength', 'keywordArgs' => ['max' => 20, 'length' => 21]], $body['errors'][0]);
    }

    public function testSlugMustBeUniqueWithinState(): void
    {
        $this->createPage(self::fixture('page.json'));

        [$status, $body] = $this->request('POST', '/api/pages', self::fixture('page.json'));
        self::assertSame(422, $status);
        self::assertStringContainsString('A page already exists with that URL path and state', self::describe($body));

        $page = self::fixture('page.json');
        $page['state'] = 'draft';
        $this->createPage($page);
    }

    public function testEditKeepsHistory(): void
    {
        $created = $this->createPage(self::fixture('page.json'));

        $edit = self::asUpdate($created);
        $edit['pageData']['availableLanguages'] = ['en_GB', 'fr'];
        $edit['pageData']['content']['langData']['en_GB']['blk1']['title'] = 'Edited title';
        [$status, $edited] = $this->request('PUT', '/api/pages/hello-world?state=live', $edit);
        self::assertSame(200, $status, self::describe($edited));
        self::assertSame('Edited title', $edited['pageData']['content']['langData']['en_GB']['blk1']['title']);
        self::assertSame(['en_GB', 'fr'], $edited['pageData']['availableLanguages']);
        self::assertSame($created['pageData']['revision'], $edited['pageData']['previousRevision']);

        [, $reloaded] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(['en_GB', 'fr'], $reloaded['pageData']['availableLanguages']);

        [$status, $history] = $this->request('GET', '/api/pages/hello-world/history?state=live');
        self::assertSame(200, $status);
        // Revisions are ordered by creation time, which has one-second precision
        $revisions = array_column($history, 'revision');
        sort($revisions);
        $expected = [$created['pageData']['revision'], $reloaded['pageData']['revision']];
        sort($expected);
        self::assertSame($expected, $revisions);
    }

    /**
     * A real-world page, anonymised, from an app that extends the entities (tagData). Its API
     * output must match what 0.11.5 produced for the same input, byte for byte.
     */
    public function testExtendedPageRoundTripsUnchanged(): void
    {
        $fixture = self::fixture('extended-page.json');
        $fixture['id'] = null;
        $this->createPage($fixture);

        [$status, $page] = $this->request('GET', '/api/pages/extended-example-page?state=live');
        self::assertSame(200, $status);
        $json = $this->rawResponse();

        // assertEquals, not assertSame: MySQL's JSON type reorders object keys
        self::assertEquals($fixture['tagData'], $page['tagData']);
        self::assertEquals($fixture['pageData']['content'], $page['pageData']['content']);
        self::assertStringContainsString('"styles":{}', $json, 'Empty styles must serialise as objects, not arrays');

        $expected = trim((string) file_get_contents(__DIR__ . '/../fixtures/extended-page.response.json'));
        $actual = self::normalise($json);
        self::assertSame(self::canonical($expected), self::canonical($actual));
        if (!$this->entityManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::assertSame($expected, $actual, 'Byte-for-byte, key order included');
        }

        [$status, $body] = $this->request('PUT', '/api/pages/extended-example-page?state=live', self::asUpdate($page));
        self::assertSame(200, $status, self::describe($body));
    }

    public function testSearchIsEmptyWhenDisabled(): void
    {
        $this->createPage(self::fixture('page.json'));

        [$status, $body] = $this->request('GET', '/api/search?q=hello');
        self::assertSame(200, $status);
        self::assertSame(['results' => [], 'total' => 0], $body);
    }

    /**
     * JSON with every object's keys sorted, keeping {} and [] distinct.
     */
    private static function canonical(string $json): string
    {
        $sort = static function (mixed $value) use (&$sort): mixed {
            if ($value instanceof \stdClass) {
                $properties = get_object_vars($value);
                ksort($properties);

                return (object) array_map($sort, $properties);
            }

            return is_array($value) ? array_map($sort, $value) : $value;
        };

        return (string) json_encode($sort(json_decode($json, false, 512, JSON_THROW_ON_ERROR)), JSON_PRESERVE_ZERO_FRACTION);
    }
}
