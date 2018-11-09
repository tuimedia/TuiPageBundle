<?php
namespace Tui\PageBundle\Search;

use Tui\PageBundle\Entity\PageInterface;

interface TransformerInterface
{
    public function transform(TranslatedPage $translatedPage, PageInterface $page): TranslatedPage;
}
