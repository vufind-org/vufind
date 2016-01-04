--TEST--
#7038, validate::email: accentued characters are not allowed
--FILE--
<?php
require_once 'Validate.php';
if (validate::email("test@example.com")) {
	echo "Ok\n";
}
if (!validate::email("testü@example.com")) {
	echo "Ok\n";
}
if (!validate::email("müller@example.com")) {
	echo "Ok\n";
}
?>
--EXPECT--
Ok
Ok
Ok
