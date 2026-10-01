<?php

namespace Tui\PageBundle\Tests\Functional;

class TranslationApiTest extends FunctionalTestCase
{
    public function testExportProducesXliffForEveryTranslatableString(): void
    {
        $this->bootApp();
        $this->createPage(self::fixture('page.json'));

        $xliff = $this->exportXliff('fr');
        self::assertSame('application/x-xliff+xml', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=hello-world.live.fr.xliff', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $doc = simplexml_load_string($xliff);
        self::assertNotFalse($doc);
        $doc->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        self::assertSame('en-GB', (string) $doc->xpath('//x:file')[0]['source-language']);
        self::assertSame('fr', (string) $doc->xpath('//x:file')[0]['target-language']);

        // MySQL's JSON type reorders object keys, so compare without order
        $resnames = array_map(fn ($unit) => (string) $unit['resname'], $doc->xpath('//x:trans-unit'));
        sort($resnames);
        self::assertSame(['[blk1][copy]', '[blk1][title]', '[metadata][title]', '[row1][title]'], $resnames);
    }

    public function testImportIntoTheOriginalPageAddsTheLanguage(): void
    {
        $this->bootApp();
        $original = $this->createPage(self::fixture('page.json'));

        [$status, $page] = $this->request('PUT', '/api/translations/hello-world?state=live&destination=original', self::translate($this->exportXliff('fr')));
        self::assertSame(201, $status, self::describe($page));
        self::assertSame(['en_GB', 'fr'], $page['pageData']['availableLanguages']);
        self::assertSame('Traduit', $page['pageData']['content']['langData']['fr']['blk1']['title']);
        self::assertSame(['en_GB', 'fr'], $page['pageData']['content']['blocks']['blk1']['languages']);

        [, $reloaded] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame('Traduit', $reloaded['pageData']['content']['langData']['fr']['blk1']['title']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $reloaded['pageData']['revision']);
        self::assertNotSame('ffffffff-ffff-ffff-ffff-ffffffffffff', $reloaded['pageData']['revision'], 'The placeholder revision used during validation is not stored');
        self::assertSame($reloaded['pageData']['revision'], $page['pageData']['revision'], 'The import response describes the saved revision');
        self::assertSame($original['pageData']['revision'], $page['pageData']['previousRevision']);
    }

    public function testImportIntoANewState(): void
    {
        $this->bootApp();
        $this->createPage(self::fixture('page.json'));

        [$status, $body] = $this->request('PUT', '/api/translations/hello-world?state=live&destination=new&destinationState=draft', self::translate($this->exportXliff('fr')));
        self::assertSame(201, $status, self::describe($body));

        [$status, $draft] = $this->request('GET', '/api/pages/hello-world?state=draft');
        self::assertSame(200, $status);
        self::assertSame('Traduit', $draft['pageData']['content']['langData']['fr']['blk1']['title']);
        self::assertSame($draft['pageData']['revision'], $body['pageData']['revision'], 'The import response describes the saved revision');
        self::assertSame('draft', $body['state']);

        [, $live] = $this->request('GET', '/api/pages/hello-world?state=live');
        self::assertSame(['en_GB'], $live['pageData']['availableLanguages'], 'The original page is untouched');

        [$status] = $this->request('PUT', '/api/translations/hello-world?state=live&destination=new&destinationState=draft', self::translate($this->exportXliff('fr')));
        self::assertSame(422, $status, 'Importing into a page that already exists is refused');
    }

    public function testImportRejectsAFileForADifferentPage(): void
    {
        $this->bootApp();
        $this->createPage(self::fixture('page.json'));
        $other = self::fixture('page.json');
        $other['slug'] = 'another-page';
        $this->createPage($other);

        [$status, $body] = $this->request('PUT', '/api/translations/another-page?state=live&destination=original', self::translate($this->exportXliff('fr')));
        self::assertSame(422, $status);
        self::assertStringContainsString('different page', $body['detail']);
    }

    public function testEveryLanguageIsAllowedWhenValidLanguagesIsUnset(): void
    {
        $this->bootApp();
        $this->createPage(self::fixture('page.json'));

        $this->exportXliff('pt_BR');
    }

    public function testValidLanguagesRestrictsTargets(): void
    {
        $this->bootApp(['valid_languages' => ['en_GB', 'fr']]);
        $this->createPage(self::fixture('page.json'));

        $this->exportXliff('fr');

        $this->client->catchExceptions(false);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unsupported target language de (valid: en_GB, fr)');
        $this->client->request('GET', '/api/translations/hello-world/de?state=live');
    }

    private function exportXliff(string $language): string
    {
        [$status, $body] = $this->request('GET', '/api/translations/hello-world/' . $language . '?state=live');
        self::assertSame(201, $status, self::describe($body));

        return $this->rawResponse();
    }

    /**
     * Fill in every target, the way a translator would.
     */
    private static function translate(string $xliff): string
    {
        return (string) preg_replace('#<target(?:\s[^>]*)?(?:/>|>.*?</target>)#s', '<target>Traduit</target>', $xliff);
    }
}
