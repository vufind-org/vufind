--TEST--
multicard.phpt: Unit tests for 'Validate.php' : mutltiple() with credit card
This test needs Validate_Finance_CreditCard installed to be enabled
--SKIPIF--
<?php
// $Id$
if (!@include_once 'Validate/Finance/CreditCard.php') {
    echo ('skip Test skipped as Validate_Finance_CreditCard not installed');
}
?>
--FILE--
<?php
// Validate test script
$noYes = array('NO', 'YES');

require_once 'Validate.php';

$types = array(
    'myemail'    => array('type' => 'email'),
    'myemail1'   => array('type' => 'email'),
    'no'         => array('type' => 'number', array('min' => -8, 'max' => -7)),
    'teststring' => array('type' => 'string', array('format' => VALIDATE_ALPHA)),
    'date'       => array('type' => 'date',   array('format' => '%d%m%Y')),
    'cc_no'      => array('type' => 'Finance_CreditCard_number')
);

$data  = array(
    array(
    'myemail' => 'webmaster@google.com', // OK
    'myemail1' => 'webmaster.@google.com', // NOK
    'no' => '-8', // OK
    'teststring' => 'PEARrocks', // OK
    'date' => '12121996', // OK
    'cc_no' => '6762 1955 1506 1813' // OK
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
date: 12121996 =>YES
cc_no: 6762 1955 1506 1813 =>YES
*****************************************

