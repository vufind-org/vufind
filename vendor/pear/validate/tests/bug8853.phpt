--TEST--
#8853, validate::email: E-Mail validation allows space before TLD
--FILE--
<?php
require_once 'Validate.php';
if (!validate::email("john@doe. com")) {
	echo "Ok\n";
}
?>
--EXPECT--
Ok
