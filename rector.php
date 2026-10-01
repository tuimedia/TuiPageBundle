<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

// CI runs this as a dry run and fails if anything would change. The composer-based sets
// follow whatever versions are installed, which may be newer than the lowest ones we support,
// so check any new suggestion works on those before applying it.
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withImportNames(importShortClasses: false)
    ->withPhpSets(php82: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withComposerBased(doctrine: true, symfony: true)
    ->withSkip([
        // Promoting would rename the $searchEnabled argument, which services.yaml binds by name
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Search/SearchSubscriber.php',
        ],
    ]);
