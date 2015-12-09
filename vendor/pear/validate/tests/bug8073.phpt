--TEST--
#8073, validate::email: a@a is valid email
--FILE--
<?php
require_once 'Validate.php';
if (!validate::email("a@a")) {
	echo "Ok\n";
}
if (validate::email("a@a", array(
                                    'use_rfc822' => true
                                ))) {
    echo "Ok\n";
}
?>
--EXPECT--
Ok
Ok
