--TEST--
multiple.phpt: Unit tests for 'Validate.php' without extension (credit card)
--FILE--
<?php
// $Id$
// Validate test script
$noYes = array('NO', 'YES');

require_once 'Validate.php';

$types = array(
    'myemail'    => array('type' => 'email'),
    'myemail1'   => array('type' => 'email'),
    'no'         => array('type' => 'number', array('min' => -8, 'max' => -7)),
    'teststring' => array('type' => 'string', array('format' => VALIDATE_ALPHA)),
    'test10844'  => array('type' => 'string', 'format' => '0-9'),
    'date'       => array('type' => 'date',   array('format' => '%d%m%Y'))
);

$data  = array(
    array(
    'myemail' => 'webmaster@google.com', // OK
    'myemail1' => 'webmaster.@google.com', // NOK
    'no' => '-8', // OK
    'teststring' => 'PEARrocks', // OK
    'test10844' => 'dsfasdf', // NOK
    'date' => '12121996' // OK
    )
);

echo "Test Validate_Multiple\n";
echo "**********************\n\n";
foreach ($data as $value) {
    $res = Validate::multiple($value, $types);
    foreach ($value as $fld=>$val) {
        echo "{$fld}: {$val} =>".(isset($res[$fld])? $noYes[$res[$fld]]: 'null')."\n";
    }
    echo "*****************************************\n\n";
}

?>
--EXPECT--
Test Validate_Multiple
**********************

myemail: webmaster@google.com =>YES
myemail1: webmaster.@google.com =>NO
no: -8 =>YES
teststring: PEARrocks =>YES
test10844: dsfasdf =>NO
date: 12121996 =>YES
*****************************************

