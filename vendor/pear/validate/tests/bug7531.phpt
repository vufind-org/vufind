--TEST--
#7531, validate::email: Email-checks need to be more RFC-compliant
--FILE--
<?php
require_once 'Validate.php';
if (!validate::email("müller@example.com", array(
                                                    'use_rfc822' => true
                                                ))) {
    echo "Ok\n";
}
?>
--EXPECT--
Ok
