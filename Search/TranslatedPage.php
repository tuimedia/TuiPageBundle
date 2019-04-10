<?php
namespace Tui\PageBundle\Search;

class TranslatedPage
{
    /** @var string Page id */
    public $id;

    /** @var string Page URL slug */
    public $slug;

    /** @var string Page namespace*/
    public $state;

    /** @var string[] */
    public $metadata;

    /** @var mixed[] Component types and their translated content */
    public $types = [];
}
