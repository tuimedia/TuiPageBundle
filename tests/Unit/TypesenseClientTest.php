<?php

namespace Tui\PageBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Search\TransformerInterface;
use Tui\PageBundle\Search\TypesenseClient;
use Tui\PageBundle\Tests\App\Entity\Page;
use Tui\PageBundle\Tests\App\Entity\PageData;

class TypesenseClientTest extends TestCase
{
    public function testConstructingWithoutHostsDoesNotBuildAClient(): void
    {
        $client = new TypesenseClient(new NullLogger(), 'prefix', null, []);

        self::assertSame('prefix_en_GB', $client->getCollectionNameForLanguage('en_GB'));
    }

    public function testSearchDocumentsGoThroughEveryTransformer(): void
    {
        $page = (new Page())->setSlug('hello')->setState('live')->setPageData((new PageData())->setRevision('rev-1'));

        $addsText = new class implements TransformerInterface {
            public function transformDocument(array $translatedPage, PageInterface $page, string $language): array
            {
                $translatedPage['searchableText'][] = 'some text';
                $translatedPage['searchableText'][] = '';
                $translatedPage['searchableText'][] = null;

                return $translatedPage;
            }

            public function transformSchema(array $config): array
            {
                return $config;
            }
        };
        $addsLanguage = new class implements TransformerInterface {
            public function transformDocument(array $translatedPage, PageInterface $page, string $language): array
            {
                return $translatedPage + ['language' => $language];
            }

            public function transformSchema(array $config): array
            {
                return $config;
            }
        };

        $client = new TypesenseClient(new NullLogger(), 'prefix', null, [], [$addsText]);
        $client->setTransformers([$addsText, $addsLanguage]);

        self::assertSame([
            'id' => '',
            'revision' => 'rev-1',
            'state' => 'live',
            'slug' => 'hello',
            'searchableText' => ['some text'],
            'language' => 'fr',
        ], $client->createSearchDocument($page, 'fr'));
    }

    public function testSearchWithoutAQueryDoesNotCallTypesense(): void
    {
        $client = new TypesenseClient(new NullLogger(), 'prefix', null, []);

        self::assertNull($client->search('prefix_en_GB', ['filter_by' => 'state:live']));
    }
}
