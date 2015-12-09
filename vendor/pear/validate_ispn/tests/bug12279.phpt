--TEST--
Bug #12279: Validate_ISPN Incorrectly Validates 10 digit Isbn Numbers
--FILE--
<?php
require_once dirname(dirname(__FILE__)) . '/Validate/ISPN.php';
var_dump(Validate_ISPN::isbn('123456789X'));
var_dump(Validate_ISPN::isbn('1234567890'));
?>
--EXPECT--
bool(true)
bool(false)
