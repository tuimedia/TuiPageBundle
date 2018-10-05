<?php
namespace Tui\PageBundle\Search;

use Tui\PageBundle\Entity\PageInterface;

class TranslatedPage
{
    public $id;
    public $state;
    public $metadata;
    public $types;

    public static function fromPage(PageInterface $page, string $language): self
    {
        $translatedPage = new self;
        $translatedPage->id = $page->getId();
        $translatedPage->state = $page->getState();

        // Build translated metadata
        $content = $page->getPageData()->getContent();
        $defaultLanguage = $page->getPageData()->getDefaultLanguage();

        $metadata = $page->getPageData()->getMetadata();
        if (isset($content['langData'][$defaultLanguage]['metadata'])) {
            $metadata = array_replace($metadata, $content['langData'][$defaultLanguage]['metadata']);
        }
        if (isset($content['langData'][$language]['metadata'])) {
            $metadata = array_replace($metadata, $content['langData'][$language]['metadata']);
        }
        $translatedPage->metadata = $metadata;

        // Build translated blocks
        foreach ($content['blocks'] as $id => $blockData) {
            $translatedBlock = $blockData;
            if (isset($content['langData'][$defaultLanguage][$id])) {
                $translatedBlock = array_replace($translatedBlock, $content['langData'][$defaultLanguage][$id]);
            }
            if (isset($content['langData'][$language][$id])) {
                $translatedBlock = array_replace($translatedBlock, $content['langData'][$language][$id]);
            }

            if (!isset($translatedPage->types[$translatedBlock['component']])) {
                $translatedPage->types[$translatedBlock['component']] = [];
            }
            $translatedPage->types[$translatedBlock['component']][] = $translatedBlock;
        }

        return $translatedPage;
    }
}