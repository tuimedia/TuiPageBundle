<?php

namespace Tui\PageBundle;

interface MetadataSanitizerInterface
{
    /**
     * Sanitize a metadata object.
     */
    public function sanitize(object $metadata): object;
}
