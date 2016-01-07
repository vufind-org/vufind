--TEST--
testdate.phpt: Unit tests for 'Validate.php'
--INI--
date.timezone=UTC
--FILE--
<?php
// $Id$
require_once 'Validate.php';

// Validate test script
$noYes = array('NO', 'YES');

echo "Test Validate_Date\n";
echo "******************\n";

$dates = array(
    array('121202', 'format'=>'%d%m%y'), // OK
    array('21202', 'format'=>'%d%m%y'), // NOK
    array('02122', 'format'=>'%y%m%d'), // NOK
    array('02229', 'format'=>'%y%d%m'), // NOK
    array('121402', 'format'=>'%d%m%y'), // NOK
    array('12120001', 'format'=>'%d%m%Y'), // OK

    /* Ambiguous date >> false
        * They should be still valid. Maybe by changing the loop
        * 1st check for the Y (4digits), and then m (2digits)
        * if you got the idea ;)
        */
    array('220001', 'format'=>'%j%n%Y'), // NOK
    array('2299', 'format'=>'%j%n%y'), // NOK
    array('2120001', 'format'=>'%j%m%Y'), // NOK
    /* End */

    array('12121999', 'format'=>'%d%m%Y', 'min'=>array('01','01','1995')), // OK
    array('12121996', 'format'=>'%d%m%Y', 'min'=>array('01','01','1995'),
                                          'max'=>array('01','01','1997')), // OK
    array('29022002', 'format'=>'%d%m%Y'), // NOK
    array('12.12.1902', 'format'=>'%d.%m.%Y'), // OK
    array('12/12/1902', 'format'=>'%d/%m/%Y'), // OK
    array('12/12/1902', 'format'=>'%d/%m/%Y'), // OK
    array('12:12:1902', 'format'=>'%d:%m:%Y'), // OK
    array('12', 'format'=>'%g'), // OK
    array('12', 'format'=>'%G'), // OK
    array('13:00', 'format'=>'%g:%i'), // NOK
    array('24:59', 'format'=>'%G:%i'), // OK
    array('25:00', 'format'=>'%G:%i'), // NOK
    array('25:00', 'format'=>'%G:%i:%s'), // NOK
    array('121902', 'format'=>'%m%Y'), // OK
    array('13120001', 'format'=>'%d%m%Y') // OK
);

echo "\nTest dates\n";
foreach ($dates as $data){
    $date = array_shift($data);
    echo "{$date} (";
    foreach ($data as $key=>$val) {
        if (is_array($val)) {
            echo "{$key}=>[ ";
            foreach($val as $elt) {
                echo "{$elt} ";
            }
            echo "] ";
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

Test dates
121202 (format=>%d%m%y ): YES
21202 (format=>%d%m%y ): NO
02122 (format=>%y%m%d ): NO
02229 (format=>%y%d%m ): NO
121402 (format=>%d%m%y ): NO
12120001 (format=>%d%m%Y ): YES
220001 (format=>%j%n%Y ): NO
2299 (format=>%j%n%y ): NO
2120001 (format=>%j%m%Y ): NO
12121999 (format=>%d%m%Y min=>[ 01 01 1995 ] ): YES
12121996 (format=>%d%m%Y min=>[ 01 01 1995 ] max=>[ 01 01 1997 ] ): YES
29022002 (format=>%d%m%Y ): NO
12.12.1902 (format=>%d.%m.%Y ): YES
12/12/1902 (format=>%d/%m/%Y ): YES
12/12/1902 (format=>%d/%m/%Y ): YES
12:12:1902 (format=>%d:%m:%Y ): YES
12 (format=>%g ): YES
12 (format=>%G ): YES
13:00 (format=>%g:%i ): NO
24:59 (format=>%G:%i ): YES
25:00 (format=>%G:%i ): NO
25:00 (format=>%G:%i:%s ): NO
121902 (format=>%m%Y ): YES
13120001 (format=>%d%m%Y ): YES
