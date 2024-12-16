<?php

$finder = new PhpCsFixer\Finder();
$finder->in(__DIR__ . '/../themes')
    ->name('*.phtml');

$rules = [
    '@PHP81Migration' => true,
    '@PSR12' => true,
    'align_multiline_comment' => true,
    'binary_operator_spaces' => [
        'default' => 'single_space',
    ],
    'blank_line_after_opening_tag' => false,
    'cast_spaces' => ['space' => 'none'],
    'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one']],
    'concat_space' => ['spacing' => 'one'],
    'ereg_to_preg' => true,
    'get_class_to_class_keyword' => true,
    'global_namespace_import' => [
        'import_functions' => true,
        'import_classes' => null,
    ],
    'is_null' => true,
    'lowercase_cast' => true,
    'magic_constant_casing' => true,
    'modernize_strpos' => true,
    'native_function_casing' => true,
    'native_function_invocation' => [
        'strict' => true,
        'scope' => 'namespaced',
    ],
    'no_alias_functions' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => true,
    'no_php4_constructor' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_around_offset' => true,
    'no_trailing_whitespace_in_comment' => false, // disabled for readability; we want < ?php // comment ? > not < ?php //comment? >
    'no_unneeded_braces' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unneeded_final_method' => true,
    'no_unreachable_default_argument_value' => true,
    'no_unused_imports' => true,
    'no_useless_return' => true,
    'no_whitespace_in_blank_line' => true,
    'non_printable_character' => true,
    'ordered_imports' => [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'alpha',
    ],
    'phpdoc_no_access' => true,
    'pow_to_exponentiation' => true,
    'single_line_after_imports' => true,
    'single_quote' => true,
    'standardize_not_equals' => true,
    'statement_indentation' => false, // disabled because it messes up indentation of php tags
    'ternary_operator_spaces' => true,
    'type_declaration_spaces' => true,
];

$cacheDir = __DIR__ . '/../.php_cs_cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}

$config = new PhpCsFixer\Config();
return $config->setCacheFile($cacheDir . '/.template.cache')
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder)
    ->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
