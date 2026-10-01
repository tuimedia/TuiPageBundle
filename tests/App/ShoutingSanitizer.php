<?php

namespace Tui\PageBundle\Tests\App;

use Tui\PageBundle\SanitizerInterface;

/**
 * A custom sanitiser for a made-up media type, registered with the tui_page.sanitizer tag.
 */
class ShoutingSanitizer implements SanitizerInterface
{
    public function sanitize(string $value): string
    {
        return strtoupper(strip_tags($value));
    }

    public function supports(string $mediaType): bool
    {
        return $mediaType === 'text/x-shout';
    }

    public function getMediaType(): string
    {
        return 'text/x-shout';
    }
}
