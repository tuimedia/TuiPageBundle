<?php

namespace Tui\PageBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A realistic custom component, ResourceList (tests/App/schemas/ResourceList.schema.json),
 * that touches every path through the schema-driven validator and sanitiser.
 */
class CustomSchemaTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->bootApp();
    }

    public function testEveryPropertyIsSanitisedByItsSchema(): void
    {
        $created = $this->createPage(self::fixture('resource-list-page.json'));
        [, $page] = $this->request('GET', '/api/pages/annual-reports?state=live');
        self::assertSame($created['pageData']['content'], $page['pageData']['content'], 'What was saved is what was returned');

        $block = $page['pageData']['content']['blocks']['res1'];
        $en = $page['pageData']['content']['langData']['en_GB']['res1'];
        $fr = $page['pageData']['content']['langData']['fr']['res1'];

        // Block-level properties go through the same schema as langData
        self::assertSame('grid', $block['variant']);
        self::assertSame(3, $block['columns']);
        self::assertTrue($block['featured']);
        self::assertSame(4.5, $block['rating']);

        // Properties the schema doesn't describe are left alone
        self::assertSame('<b>untouched</b>', $block['trackingCode']);

        // Plain strings lose their markup
        self::assertSame('Annual reports', $en['title']);

        // HTML keeps safe markup and loses scripts and event handlers
        self::assertStringContainsString('<a href="https://example.com/about"', $en['intro']);
        self::assertStringContainsString('about us</a> first.', $en['intro']);
        self::assertStringNotContainsString('onclick', $en['intro']);
        self::assertStringNotContainsString('<script', $en['intro']);

        // A custom media type goes to the tagged sanitiser that supports it
        self::assertSame('DOWNLOAD NOW', $en['strapline']);

        // Nullable strings: null stays null, a string is cleaned
        self::assertNull($en['subtitle']);
        self::assertSame('Sous-titre', $fr['subtitle']);
        self::assertSame('Rapports annuels', $fr['title']);

        // $ref definitions are resolved before cleaning
        self::assertSame(['label' => 'Get them all', 'url' => 'https://example.com/all.zip', 'newWindow' => true], $en['cta']);

        // An object with properties but no type is still treated as an object
        self::assertSame(['src' => '/images/chart.png', 'alt' => 'Bar chart'], $en['image']);

        // Arrays are cleaned item by item, whether the items are strings or objects
        self::assertSame(['finance', '2025'], $en['tags']);
        self::assertSame('Report 2025', $en['resources'][0]['title']);
        self::assertSame(1048576, $en['resources'][0]['fileSize']);
        self::assertSame('PDF download', $en['resources'][0]['link']['label']);
        self::assertStringContainsString('<strong>year</strong>', $en['resources'][0]['description']);
        self::assertStringNotContainsString('onerror', $en['resources'][0]['description']);
        self::assertSame(['url' => 'https://example.com/2024.pdf'], $en['resources'][1]['link']);

        // Every property matching a patternProperties schema is cleaned, not just the first
        self::assertSame(['downloadLabel' => 'Download file', 'sizeLabel' => 'Size on disk'], $en['labels']);
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function invalidBlocks(): iterable
    {
        yield 'enum' => [static function (array $page) {
            $page['pageData']['content']['blocks']['res1']['variant'] = 'carousel';

            return $page;
        }, 'variant'];

        yield 'integer range' => [static function (array $page) {
            $page['pageData']['content']['blocks']['res1']['columns'] = 9;

            return $page;
        }, 'columns'];

        yield 'wrong type' => [static function (array $page) {
            $page['pageData']['content']['langData']['en_GB']['res1']['resources'][0]['fileSize'] = 'big';

            return $page;
        }, 'resources.0.fileSize'];

        yield 'required property of an array item' => [static function (array $page) {
            unset($page['pageData']['content']['langData']['en_GB']['res1']['resources'][1]['link']);

            return $page;
        }, 'resources.1'];

        yield 'required property inside a $ref' => [static function (array $page) {
            unset($page['pageData']['content']['langData']['en_GB']['res1']['cta']['url']);

            return $page;
        }, 'cta'];

        yield 'property not matching patternProperties' => [static function (array $page) {
            $page['pageData']['content']['langData']['en_GB']['res1']['labels']['colour'] = 'red';

            return $page;
        }, 'labels'];

        yield 'only the French translation is invalid' => [static function (array $page) {
            $page['pageData']['content']['langData']['fr']['res1']['title'] = str_repeat('x', 201);

            return $page;
        }, 'title'];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $break
     */
    #[DataProvider('invalidBlocks')]
    public function testInvalidBlocksAreRejected(callable $break, string $path): void
    {
        [$status, $body] = $this->request('POST', '/api/pages', $break(self::fixture('resource-list-page.json')));

        self::assertSame(422, $status, self::describe($body));
        self::assertSame('res1', $body['component']['id']);
        self::assertSame($path, $body['errors'][0]['path'], self::describe($body));
    }

    public function testFrenchErrorsNameTheLanguage(): void
    {
        $page = self::fixture('resource-list-page.json');
        $page['pageData']['content']['langData']['fr']['res1']['title'] = str_repeat('x', 201);

        [, $body] = $this->request('POST', '/api/pages', $page);

        self::assertStringContainsString('Component res1 in language fr', $body['detail']);
    }
}
