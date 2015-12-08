--TEST--
Validate::email
--FILE--
<?php
require_once dirname(dirname(__FILE__)) . '/Validate.php';
$validate = new Validate();
$wrongEmail = 'asdf@ere.';
$goodEmail  = 'davidc@php.net';
var_dump($validate->email($wrongEmail));
var_dump($validate->email($goodEmail));
?>
--EXPECT--
bool(false)
bool(true)
