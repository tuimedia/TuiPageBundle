<?php
namespace Tui\PageBundle\Search;

class TranslatedPageIndex extends \ElasticSearcher\Abstracts\AbstractIndex
{
    private $indexName;
    private $language;

    public function __construct($indexName, $language)
    {
        $this->indexName = $indexName;
        $this->language = $language;
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
                        'properties' => [
                            // from registered components
                            // TODO: provide a listener based extension mechanism for custom component mapping?
                            // TODO: maybe use the registered JSON Schemas here too!? That'd be neat
                            // That means making a factory that returns these, so it can inject the component schemas
                        ]
                    ],
                ],
            ],
        ]);
    }
}