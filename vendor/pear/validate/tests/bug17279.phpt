--TEST--
Validate::email
--FILE--
<?php
require_once dirname(dirname(__FILE__)) . '/Validate.php';
var_dump(Validate::email(
    'afanstudio@gmail.com',
    array(
        'fullTLDValidation' => true,
        'use_rfc822' => true, 
        'VALIDATE_CCTLD_EMAILS' => true
    )
));
var_dump(Validate::email(
    'afanstudio@gmail.com',
    array(
        'fullTLDValidation' => true,
        'use_rfc822' => true, 
    )
));
?>
--EXPECT--
bool(false)
bool(true)
