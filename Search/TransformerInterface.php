<?php
namespace Tui\PageBundle\Search;

interface TransformerInterface
{
    public function transform(TranslatedPage $page): TranslatedPage;
}
