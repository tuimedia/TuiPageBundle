<?php

$finder = PhpCsFixer\Finder::create()
    // ->exclude('somedir')
    // ->notPath('src/Symfony/Component/Translation/Tests/fixtures/resources.php')
    ->in(__DIR__.'/src')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        '@DoctrineAnnotation' => true,
        '@Symfony' => true,
        'yoda_style' => false,
        'blank_line_after_opening_tag' => false,
        'single_blank_line_before_namespace' => false,
        'concat_space' => ['spacing' => 'one'],
        'fully_qualified_strict_types' => true,
    ])
    ->setFinder($finder)
;
