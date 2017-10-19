<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__ . '/../module');

$rules = [
    'align_multiline_comment' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => [
        'default' => 'single_space',
        'operators' => ['=' => null, '=>' => null],
    ],
    'blank_line_after_namespace' => true,
    'braces' => true,
    'cast_spaces' => ['space' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'elseif' => true,
    'encoding' => true,
    'full_opening_tag' => true,
    'function_declaration' => true,
    'function_typehint_space' => true,
    'indentation_type' => true,
    'line_ending' => true,
    'linebreak_after_opening_tag' => true,
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
    'no_closing_tag' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_consecutive_blank_lines' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_after_function_name' => true,
    'no_spaces_inside_parenthesis' => true,
    'no_trailing_whitespace' => true,
    'no_unused_imports' => true,
    'phpdoc_no_access' => true,
    'single_blank_line_at_eof' => true,
    'ternary_operator_spaces' => true,
    'visibility_required' => true,
];

return PhpCsFixer\Config::create()
    ->setRules($rules)
    ->setFinder($finder);
