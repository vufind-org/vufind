<?php
$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in('library')
    ->in('tests')
    ->notPath('_files');
$config = Symfony\CS\Config\Config::create();
$config->level(null);
$config->fixers(
    array(
        'braces',
        'duplicate_semicolon',
        'elseif',
        'empty_return',
        'encoding',
        'eof_ending',
        'function_call_space',
        'function_declaration',
        'indentation',
        'join_function',
        'line_after_namespace',
        'linefeed',
        'lowercase_keywords',
        'parenthesis',
        'multiple_use',
        'method_argument_space',
        'object_operator',
        'php_closing_tag',
        'remove_lines_between_uses',
        'short_tag',
        'standardize_not_equal',
        'trailing_spaces',
        'unused_use',
        'visibility',
        'whitespacy_lines',
    )
);
$config->finder($finder);
return $config;
