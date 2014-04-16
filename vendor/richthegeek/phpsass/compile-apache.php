<?php

/**
 * This file is intended to be used in conjunction with Apache2's mod_actions,
 * wherein you can have a .htaccess file like so for automatic compilation:
 *     Action compile-sass /git/phpsass/compile-apache.php
 *     AddHandler compile-sass .sass .scss
 */

header('Content-type: text/css');

require_once './SassParser.php';

function warn($text, $context) {
	print "/** WARN: $text, on line {$context->node->token->line} of {$context->node->token->filename} **/\n";
}
function debug($text, $context) {
	print "/** DEBUG: $text, on line {$context->node->token->line} of {$context->node->token->filename} **/\n";
}


$file = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['PATH_INFO'];
$syntax = substr($file, -4, 4);

$options = array(
	'style' => 'expanded',
	'cache' => FALSE,
	'syntax' => $syntax,
	'debug' => FALSE,
	'callbacks' => array(
		'warn' => 'warn',
		'debug' => 'debug'
	),
);

// Execute the compiler.
$parser = new SassParser($options);
try {
	print "\n\n" . $parser->toCss($file);
} catch (Exception $e) {
	print $e->getMessage();	
}