<?php

$finder = PhpCsFixer\Finder::create()->in(__DIR__ . '/../module');

$rules = [
    'array_syntax' => ['syntax' => 'short'],
    //'binary_operator_spaces' => true,
    'concat_space' => ['spacing' => 'one'],
    'encoding' => true,
    'lowercase_keywords' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_blank_lines_before_namespace' => true,
    'no_closing_tag' => true,
    'no_empty_statement' => true,
    'no_extra_consecutive_blank_lines' => true,
    'no_leading_import_slash' => true,
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
