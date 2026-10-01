<?php

namespace Tui\PageBundle\Tests\App;

use Tui\PageBundle\MetadataSanitizerInterface;

/**
 * A custom metadata sanitiser, registered with the tui_page.metadata_sanitizer tag.
 */
class RedactingMetadataSanitizer implements MetadataSanitizerInterface
{
    public function sanitize(object $metadata): object
    {
        if (isset($metadata->internalNote)) {
            $metadata->internalNote = '[redacted]';
        }

        return $metadata;
    }
}
