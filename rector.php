<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Dry-run only (vendor/bin/rector process --dry-run) and cherry-pick: the composer-based sets
// follow whatever versions are installed, which may be newer than the lowest ones we support.
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withImportNames(importShortClasses: false)
    ->withPhpSets(php81: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withComposerBased(doctrine: true, symfony: true);
