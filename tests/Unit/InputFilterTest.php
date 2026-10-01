<?php

namespace Tui\PageBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tui\PageBundle\InputFilter;

class InputFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function values(): iterable
    {
        yield 'plain slug' => ['hello-world', 'hello-world'];
        yield 'tags stripped' => ['a<b>bold</b>c', 'aboldc'];
        yield 'script stripped' => ['<script>alert(1)</script>live', 'alert(1)live'];
        yield 'quotes encoded' => ['it\'s "quoted"', 'it&#39;s &#34;quoted&#34;'];
        yield 'unicode kept' => ['pt_BR é ü', 'pt_BR é ü'];
        yield 'ampersand kept' => ['a&b', 'a&b'];
        yield 'null' => [null, ''];
        yield 'integer' => [12, '12'];
        yield 'true' => [true, '1'];
        yield 'array' => [['x'], ''];
    }

    #[DataProvider('values')]
    public function testFiltersLikeFilterSanitizeString(mixed $input, string $expected): void
    {
        self::assertSame($expected, InputFilter::string($input));
    }
}
