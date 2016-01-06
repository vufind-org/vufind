--TEST--
#8629, validate::email: fails with numbers at start of hostname.
--FILE--
<?php
require_once 'Validate.php';
if (validate::email("test@123host.tld")) {
	echo "Ok\n";
}
?>
--EXPECT--
Ok
