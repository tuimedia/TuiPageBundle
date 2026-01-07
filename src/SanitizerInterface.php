<?php

namespace Tui\PageBundle;

interface SanitizerInterface
{
    /** Sanitize a string value */
    public function sanitize(string $value): string;

    /** Check if this sanitizer supports the given media type */
    public function supports(string $mediaType): bool;

    /** Return the media type this sanitizer handles (for schema validation) */
    public function getMediaType(): string;
}
