--TEST--
Unit tests for date() with min / max functionality
--INI--
date.timezone=UTC
--SKIPIF--
<?php
if (!@include 'Date.php') {
  echo 'skip Requires PEAR::Date';
}
?>
--FILE--
<?php
// $Id: testdate.phpt 304327 2010-10-11 23:49:39Z clockwerx $
require_once 'Validate.php';

// Validate test script
$noYes = array('NO', 'YES');
require_once 'Date.php';

echo "Test Validate_Date\n";
echo "******************\n";

$dateObjects = array(
    array('11111996', 'format'=>'%d%m%Y', 'min' => new Date('19950101')), // OK
    array('12121996', 'format'=>'%d%m%Y', 'min' => new Date('19970101')), // NOK
    array('10101994', 'format'=>'%d%m%Y', 'max' => new Date('2005-04-27 06:24:05')), // OK
    array('11111994', 'format'=>'%d%m%Y', 'max' => new Date('19920101')), // NOK
    array('12121996', 'format'=>'%d%m%Y',
                      'min' => new Date('19950101'), 'max' => new Date('2005-04-27 06:24:05')) // OK
);

echo "\nTest dates with min max object\n";
foreach ($dateObjects as $data){
    $date = array_shift($data);
    echo "{$date} (";
    foreach ($data as $key=>$val) {
        if (($key == 'min') or ($key == 'max')) {
            echo "{$key}=>".$val->getDate()." ";
        } else {
            echo "{$key}=>{$val} ";
        }
    }
    echo "): ".$noYes[Validate::date($date, $data)]."\n";
}
?>
--EXPECT--
Test Validate_Date
******************

Test dates with min max object
11111996 (format=>%d%m%Y min=>1995-01-01 00:00:00 ): YES
12121996 (format=>%d%m%Y min=>1997-01-01 00:00:00 ): NO
10101994 (format=>%d%m%Y max=>2005-04-27 06:24:05 ): YES
11111994 (format=>%d%m%Y max=>1992-01-01 00:00:00 ): NO
12121996 (format=>%d%m%Y min=>1995-01-01 00:00:00 max=>2005-04-27 06:24:05 ): YES
