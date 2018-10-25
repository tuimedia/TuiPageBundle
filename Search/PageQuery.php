<?php
namespace Tui\PageBundle\Search;

use ElasticSearcher\Abstracts\AbstractQuery;
use ElasticSearcher\ElasticSearcher;
use ElasticSearcher\Fragments\Traits\PaginatedTrait;
use Tui\PageBundle\Sanitizer;

class PageQuery extends AbstractQuery
{
    use PaginatedTrait;

    private $sanitizer;

    public function __construct(ElasticSearcher $searcher, Sanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
        parent::__construct($searcher);
    }

    public function setup()
    {
        $this->searchIn($this->getData('index'), 'pages');

        // Clean the search terms
        $q = mb_strtolower($this->getData('q'));
        $q = trim($this->sanitizer->cleanQuery($q));

        // Make a query that values the document's content over it's category
        // NOTE: setBody() overrides all previous changes (like pagination) so run it first
        $this->setBody([
            'query' => [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'match' => [
                                '_all' => [
                                    'query' => $q,
                                    // Be more accommodating to typos:
                                    // The edit distance is only 20% of the term
                                    // eg 10 characters? You get 2 typos.
                                    // @see https://www.elastic.co/guide/en/elasticsearch/reference/1.7/common-options.html#_string_fields
                                    'fuzziness' => 0.8,
                                    'boost' => 2,
                                    'operator' => 'and',
                                ],
                            ],
                        ],
                        // 'Or' query on individual terms with minimum_should_match
                        // Allows queries like "value selling online" where "value selling"
                        // matches but "value selling online" does not.
                        //
                        // Using minimum_should_match with high_freq *should* make it so that
                        // adding random words won't increase the number of search results.
                        // It'll only work with high frequency words...
                        // @see https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-common-terms-query.html
                        [
                            'common' => [
                                '_all' => [
                                    'query' => $q,
                                    'cutoff_frequency' => 0.001,
                                    'low_freq_operator' => 'and',
                                    'minimum_should_match' => [
                                        "high_freq" => '1',
                                    ],
                                    'boost' => 0.5,
                                ],
                            ],
                        ],
                    ],
                    'must' => [
                        ['term' => ['state' => $this->getData('state')]],
                    ],
                ],
            ],
        ]);

        $this->paginate($this->getData('page'), $this->getData('size'));
    }
}