<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PER-CS2.0' => true,
        '@PhpCsFixer' => true,
        'static_lambda' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
