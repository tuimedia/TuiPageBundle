<?php

use Doctrine\Deprecations\Deprecation;
use Symfony\Component\Filesystem\Filesystem;
use Tui\PageBundle\Tests\App\TestKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

// Doctrine only reports deprecations when asked to
if (class_exists(Deprecation::class)) {
    Deprecation::enableWithTriggerError();
}

// Start each run with freshly compiled containers, so ContainerDeprecationsTest sees every
// deprecation raised while building them
(new Filesystem())->remove(TestKernel::varDir());
