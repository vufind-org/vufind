--TEST--
#8851, validate::date: date-validation allows letters in time.
--FILE--
<?php
require_once 'Validate.php';
$validate = & new Validate();
$time = "aa:aa";
if (!$validate->date($time,array("format"=>"%h:%i"))) {
    echo "Ok";
}
?>
--EXPECT--
Ok
