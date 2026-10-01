<?php

namespace Tui\PageBundle\Tests\App;

use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Search\TransformerInterface;

/**
 * Indexes every block's title and copy, and adds a faceted field, like the README example.
 */
class SearchTransformer implements TransformerInterface
{
    public function transformSchema(array $config): array
    {
        $config['fields'][] = ['name' => 'blockCount', 'type' => 'int32', 'facet' => true];

        return $config;
    }

    public function transformDocument(array $translatedPage, PageInterface $page, string $language): array
    {
        $content = $page->getPageData()->getContent();
        $langData = $content['langData'][$language] ?? [];

        foreach (array_keys($content['blocks'] ?? []) as $blockId) {
            $translatedPage['searchableText'][] = $langData[$blockId]['title'] ?? null;
            $translatedPage['searchableText'][] = strip_tags((string) ($langData[$blockId]['copy'] ?? ''));
        }
        $translatedPage['blockCount'] = count($content['blocks'] ?? []);

        return $translatedPage;
    }
}
