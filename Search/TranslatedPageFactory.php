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

    public function createFromPage(PageInterface $page, string $language): array
    {
        $translatedPage = [
            'id' => (string) $page->getId(),
            'revision' => (string) $page->getPageData()->getRevision(),
            'state' => (string) $page->getState(),
            'slug' => (string) $page->getSlug(),
            'searchableText' => [],
        ];

        foreach ($this->transformers as $transformer) {
            $translatedPage = $transformer->transformDocument($translatedPage, $page, $language);
        }

        $translatedPage['searchableText'] = array_values(array_filter($translatedPage['searchableText']));

        return $translatedPage;
    }
}
