<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__ . '/../themes')
    ->name('*.phtml');

$rules = [
    'align_multiline_comment' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => [
        'default' => 'single_space',
    ],
    'blank_line_after_namespace' => true,
    //'braces' => true, // disabled because we don't want to create inconsistent indentation, but useful to normalize control structure spacing
    'cast_spaces' => ['space' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'elseif' => true,
    'encoding' => true,
    'ereg_to_preg' => true,
    'full_opening_tag' => true,
    'function_declaration' => true,
    'function_typehint_space' => true,
    'indentation_type' => true,
    'is_null' => true,
    'line_ending' => true,
    'lowercase_cast' => true,
    'lowercase_constants' => true,
    'lowercase_keywords' => true,
    'magic_constant_casing' => true,
    'method_argument_space' => true,
    'method_separation' => true,
    'native_function_casing' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_blank_lines_before_namespace' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_consecutive_blank_lines' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_after_function_name' => true,
    'no_spaces_around_offset' => true,
    'no_spaces_inside_parenthesis' => true,
    'no_trailing_whitespace' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unneeded_curly_braces' => true,
    'no_unused_imports' => true,
    'no_useless_return' => true,
    'no_whitespace_before_comma_in_array' => true,
    'no_whitespace_in_blank_line' => true,
    'non_printable_character' => true,
    'ordered_imports' => true,
    'phpdoc_no_access' => true,
    'single_blank_line_at_eof' => true,
    'single_class_element_per_statement' => true,
    'single_import_per_statement' => true,
    'single_line_after_imports' => true,
    'short_scalar_cast' => true,
    'standardize_not_equals' => true,
    'switch_case_semicolon_to_colon' => true,
    'switch_case_space' => true,
    //'ternary_operator_spaces' => true, // disabled due to bug in php-cs-fixer 2.7.1
    'ternary_to_null_coalescing' => true,
    'visibility_required' => true,
];

$cacheDir = __DIR__ . '/../.php_cs_cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}

return PhpCsFixer\Config::create()
    ->setCacheFile($cacheDir . '/.template.cache')
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder);
