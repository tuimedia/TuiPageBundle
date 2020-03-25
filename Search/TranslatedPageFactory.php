<?php
namespace Tui\PageBundle\Search;

use Tui\PageBundle\Entity\PageInterface;
use Tui\PageBundle\Search\TransformerInterface;

class TranslatedPageFactory
{
    /** @var TransformerInterface[] */
    private $transformers = [];

    public function __construct(array $componentTransformers = null)
    {
        if ($componentTransformers) {
            $this->transformers = $componentTransformers;
        }
    }

    public function setTransformers(array $componentTransformers): void
    {
        $this->transformers = $componentTransformers;
    }

    public function createFromPage(PageInterface $page, string $language): TranslatedPage
    {
        $translatedPage = new TranslatedPage;
        $translatedPage->id = (string) $page->getId();
        $translatedPage->state = (string) $page->getState();
        $translatedPage->slug = (string) $page->getSlug();

        // Build translated metadata
        $pageData = $page->getPageData();
        $content = $pageData->getContent();
        $defaultLanguage = $pageData->getDefaultLanguage();

        $metadata = $pageData->getMetadata();
        if (isset($content['langData'][$defaultLanguage]['metadata']) && is_array($content['langData'][$defaultLanguage]['metadata'])) {
            $metadata = array_replace_recursive($metadata, $content['langData'][$defaultLanguage]['metadata']);
            if (!is_array($metadata)) {
                throw new \RuntimeException('Failed to merge metadata');
            }
        }
        if (isset($content['langData'][$language]['metadata']) && is_array($content['langData'][$language]['metadata'])) {
            $metadata = array_replace_recursive($metadata, $content['langData'][$language]['metadata']);
            if (!is_array($metadata)) {
                throw new \RuntimeException('Failed to merge metadata');
            }
        }

        $translatedPage->metadata = $metadata;

        // Build translated blocks
        foreach ($content['blocks'] as $id => $blockData) {
            $translatedBlock = $blockData;
            if (isset($content['langData'][$defaultLanguage][$id])) {
                $translatedBlock = array_replace_recursive($translatedBlock, $content['langData'][$defaultLanguage][$id]);
            }
            if (isset($content['langData'][$language][$id])) {
                $translatedBlock = array_replace_recursive($translatedBlock, $content['langData'][$language][$id]);
            }

            if (!isset($translatedPage->types[$translatedBlock['component']])) {
                $translatedPage->types[$translatedBlock['component']] = [];
            }

            $translatedPage->types[$translatedBlock['component']][] = $translatedBlock;
        }

        foreach ($this->transformers as $transformer) {
            $translatedPage = $transformer->transform($translatedPage, $page);
        }

        return $translatedPage;
    }
}
