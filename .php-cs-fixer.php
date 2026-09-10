<?php

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->append([
        __DIR__ . '/LSTelegramNotify.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setIndent("\t")
    ->setLineEnding("\n")
    ->setRules([
        'blank_line_after_opening_tag' => true,
        'no_closing_tag' => true,
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder($finder);
