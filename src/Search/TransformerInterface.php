<?php
namespace Tui\PageBundle\Search;

use Tui\PageBundle\Entity\PageInterface;

interface TransformerInterface
{
    /** Modify a Typesense document before indexing */
    public function transformDocument(array $translatedPage, PageInterface $page, string $language): array;

    /** Modify a Typesense collection schema */
    public function transformSchema(array $config): array;
}
