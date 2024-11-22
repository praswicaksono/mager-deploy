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
    ])
    ->setFinder($finder)
;
