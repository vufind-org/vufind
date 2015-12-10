--TEST--
#7648, validate::string, VALIDATE_EALPHA does not include the Å character
--FILE--
<?php
require_once 'Validate.php';

$names = array(
                'Stina',
                'Östen',
                'Måns',
                'Åsa'
            );

foreach ($names as $name) {
    if (validate::string($name,
                         array(
                                'format' => VALIDATE_EALPHA))) {
        echo "Ok\n";
    }
}
?>
--EXPECT--
Ok
Ok
Ok
Ok
