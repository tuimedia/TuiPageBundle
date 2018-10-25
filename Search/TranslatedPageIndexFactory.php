<?php
namespace Tui\PageBundle\Search;

class TranslatedPageIndexFactory
{
    private $componentMappings;
    private $indexName;

    public function __construct(array $componentMappings, string $indexName)
    {
        $this->componentMappings = $componentMappings;
        $this->indexName = $indexName;
    }

    public function createTranslatedPageIndex($language): TranslatedPageIndex
    {
        return new TranslatedPageIndex($this->indexName, $language, $this->componentMappings);
    }
}
