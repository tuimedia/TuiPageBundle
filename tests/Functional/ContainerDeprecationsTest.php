<?php

namespace Tui\PageBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Deprecations raised while the container compiles: bundle config, service definitions, and
 * attributes like #[AutowireIterator] that the DI component reads.
 *
 * PHPUnit sees these, but files them as indirect (raised by one library for another) and the
 * suite ignores indirect deprecations, so this test records them itself. They only say which
 * library frame raised them, not who caused them, so every deprecation fails the test unless
 * it's on the allowlist below. The test app's own config is written to be deprecation-free on
 * every supported version.
 */
class ContainerDeprecationsTest extends FunctionalTestCase
{
    /**
     * Raised between libraries, whatever the bundle does.
     */
    private const ALLOWED = [
        // Doctrine's proxies on Symfony 7.3+ when native lazy objects aren't available (PHP < 8.4 or ORM < 3.4)
        'The "Symfony\Component\VarExporter\LazyGhostTrait" trait is deprecated',
        'Using ProxyHelper::generateLazyGhost() is deprecated',
    ];

    /**
     * @return iterable<string, array{array{search?: bool, valid_languages?: string[]}}>
     */
    public static function kernelOptions(): iterable
    {
        yield 'search disabled' => [[]];
        yield 'search enabled' => [['search' => true]];
        yield 'languages restricted' => [['valid_languages' => ['en_GB', 'fr']]];
    }

    /**
     * @param array{search?: bool, valid_languages?: string[]} $options
     */
    #[DataProvider('kernelOptions')]
    public function testCompilingTheContainerRaisesNoDeprecations(array $options): void
    {
        $deprecations = [];
        set_error_handler(static function (int $type, string $message, string $file, int $line) use (&$deprecations): bool {
            $deprecations[] = sprintf('%s (%s:%d)', $message, $file, $line);

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            static::bootKernel(['tui' => $options + ['cache_salt' => bin2hex(random_bytes(8))]]);
        } finally {
            restore_error_handler();
        }

        $unexpected = array_values(array_filter($deprecations, static function (string $deprecation): bool {
            foreach (self::ALLOWED as $allowed) {
                if (str_contains($deprecation, $allowed)) {
                    return false;
                }
            }

            return true;
        }));

        self::assertSame([], $unexpected, "Deprecations raised while compiling the container:\n" . implode("\n", $unexpected));
    }
}
