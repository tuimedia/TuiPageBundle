<?php
namespace Tui\PageBundle\Search;

class TranslatedPageIndex extends \ElasticSearcher\Abstracts\AbstractIndex
{
    private $indexName;
    private $language;
    private $componentMappings;

    public function __construct(string $indexName, string $language, array $componentMappings)
    {
        $this->indexName = $indexName;
        $this->language = $language;
        $this->componentMappings = $componentMappings;
        parent::__construct();
    }

    public function getName()
    {
        return strtolower(vsprintf('%s.%s', [
            $this->indexName,
            $this->language,
        ]));
    }

    public function setup()
    {
        $this->setTypes([
            'pages' => [
                'properties' => [
                    'metadata' => ['type' => 'nested'],
                    'state' => ['type' => 'keyword'],
                    'types' => [
                        'type' => 'nested',
                        // Custom mappings from registered components
                        'properties' => array_filter($this->componentMappings, function ($mapping) {
                            return !!count($mapping);
                        }),
                    ],
                ],
            ],
        ]);
    }
}