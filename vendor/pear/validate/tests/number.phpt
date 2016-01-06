--TEST--
number.phpt: Unit tests for 'Validate.php'
--FILE--
<?php
// $Id$
require_once 'Validate.php';
// Validate test script
$noYes = array('NO', 'YES');

echo "Test Validate_Number\n";
echo "********************\n";
$numbers = array(
        array(8), // OK
        array('-8'), // OK
        array(-8), // OK
        array('-8,', 'decimal'=>','), // NOK
        array('-8.0', 'decimal'=>','), // NOK
        array('-8,0', 'decimal'=>',', 'dec_prec'=>2), // OK
        array(8.0004, 'decimal'=>'.', 'dec_prec'=>3), // NOK
        array(8.0004, 'decimal'=>'.', 'dec_prec'=>4), // OK
        array('-8', 'min'=>1, 'max'=>9), // NOK
        array('-8', 'min'=>-8, 'max'=>-7), // OK
        array('-8.02', 'decimal'=>'.', 'min'=>-8, 'max'=>-7), // NOK
        array('-8.02', 'decimal'=>'.', 'min'=>-9, 'max'=>-7), // OK
        array('-8.02', 'decimal'=>'.,','min'=>-9, 'max'=>-7) // OK
);

foreach($numbers as $data) {
    $number = array_shift($data);
    echo "{$number} (";
    foreach ($data as $key=>$val) {
        echo "{$key}=>{$val} ";
    }
    echo "): ".$noYes[Validate::number($number,$data)]."\n";
}
?>
--EXPECT--
Test Validate_Number
********************
8 (): YES
-8 (): YES
-8 (): YES
-8, (decimal=>, ): NO
-8.0 (decimal=>, ): NO
-8,0 (decimal=>, dec_prec=>2 ): YES
8.0004 (decimal=>. dec_prec=>3 ): NO
8.0004 (decimal=>. dec_prec=>4 ): YES
-8 (min=>1 max=>9 ): NO
-8 (min=>-8 max=>-7 ): YES
-8.02 (decimal=>. min=>-8 max=>-7 ): NO
-8.02 (decimal=>. min=>-9 max=>-7 ): YES
-8.02 (decimal=>., min=>-9 max=>-7 ): YES
