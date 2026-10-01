<?php

namespace Tui\PageBundle;

/**
 * @internal
 */
final class InputFilter
{
    /**
     * Strip tags and HTML-encode quotes, matching the deprecated FILTER_SANITIZE_STRING filter.
     */
    public static function string(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return str_replace(["'", '"'], ['&#39;', '&#34;'], strip_tags((string) $value));
    }
}
