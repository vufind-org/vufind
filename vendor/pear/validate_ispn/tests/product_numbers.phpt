--TEST--
product_numbers.phpt: Unit tests for
--FILE--
<?php
// $Id$
// Validate test script
$noYes = array('NO', 'YES');

require 'Validate/ISPN.php';

echo "Test Validate_ISPN\n";
echo "******************\n";

$ucc12s = array(
    '614141210220', // OK
    '614141210221', // NOK
);

$ean8s = array(
    '43210121', // OK
    '43210128', // NOK
);

$ean13s = array(
    '1014541210223', // OK
    '1014541210228', // NOK
);

$ean14s = array(
    '91014541210226', // OK
    '91014541210221', // NOK
);

$ssccs = array(
    '106141411928374657', // OK
    '106141411928374651', // NOK
);

$issns = array(
    '0366-3590', // OK
    '03663590', // OK
    '0004-6620', // OK
    '0394-6320', // OK
    '0395-7500', // OK
    '8675-4548', // OK
    '4342-7677', // OK
    '4545-6569', // OK
    '3434-6872', // OK
    '1044-789X', // OK
    '1044-7890', // NOK
    '1044-789', // NOK
    '9685-5656', // NOK
    '8768-4564', // NOK
    '4564-7786', // NOK
    '2317-8472', // NOK
    '8675-4543', // NOK
    '4342-7675', // NOK
    '1044-789X', // OK
    '1044-7890', // NOK
);

$isbn10 = array(
    '0-06-064831-7', // OK
    '0-440-34319-4', // OK
    'ISBN 0-8436-1072-7', // OK
    'ISBN 0-7357-1410-X', // OK
    'ISBN 0-7357-1410-0', // NOK
    'ISBN 0-312-33177-0', // OK
    '0-312-33177-0', // OK
    'ISBN 0-201-63361-2', // OK
    'ISBN 0-201-63361-3', // NOK
    '1-873671-00-4', // NOK
    '1873671003',    // NOK
    '1-56619-909-2', // NOK
    '1566199091',    // NOK
    '0735714100',    // NOK
    '013147149X',    // OK
    '1590593804',    // OK
    'ISBN 0-672-32704-X', // OK
);

$isbn13 = array(
    '978-1-873671-00-9', // OK
    '9781873671009',     // OK
    '978-1-56619-909-4', // OK
    '9781566199094',     // OK
    '978-0131471498',    // OK
    '978-1590593806',    // OK
    '978-1590593803',    // NO
    '978-0672327049', // OK
);

$ismns = array(
    'M-345-24680-5', // OK
    '2-345-24680-5', // NOK
    'M-345-24680-4', // NOK
    'M-2306-7118-7', // OK
);

$isrcs = array(
    'FR-Z03-98-00212', // OK
    'ISRC FR-Z03-98-00212', // OK
    'ISRC FR - Z03 - 98 - 00212', // OK
    'FR-Z03-91-01231', // OK
    'FR-Z03-91-01232', // OK
    'US-G34-04-25384', // OK
    'US-MR1-63-10018', // OK
    '34-234-34-12312', // NOK
    'US-MR1-HE-ASDFG', // NOK
);

echo "\nTest UCC12\n";
foreach ($ucc12s as $ucc12) {
    echo "{$ucc12} : ".$noYes[Validate_ISPN::ucc12($ucc12)]."\n";
}

echo "\nTest EAN8\n";
foreach ($ean8s as $ean8) {
    echo "{$ean8} : ".$noYes[Validate_ISPN::ean8($ean8)]."\n";
}

echo "\nTest EAN13\n";
foreach ($ean13s as $ean13) {
    echo "{$ean13} : ".$noYes[Validate_ISPN::ean13($ean13)]."\n";
}

echo "\nTest EAN14\n";
foreach ($ean14s as $ean14) {
    echo "{$ean14} : ".$noYes[Validate_ISPN::ean14($ean14)]."\n";
}

echo "\nTest SSCC\n";
foreach ($ssccs as $sscc) {
    echo "{$sscc} : ".$noYes[Validate_ISPN::sscc($sscc)]."\n";
}

echo "\nTest ISSN\n";
foreach ($issns as $issn) {
    echo "{$issn} : ".$noYes[Validate_ISPN::issn($issn)]."\n";
}

echo "\nTest ISBN10\n";
foreach ($isbn10 as $isbn) {
    echo "{$isbn} : ".$noYes[Validate_ISPN::isbn10($isbn)]."\n";
}

echo "\nTest ISBN13\n";
foreach ($isbn13 as $isbn) {
    echo "{$isbn} : ".$noYes[Validate_ISPN::isbn13($isbn)]."\n";
}

echo "\nTest ISMN\n";
foreach ($ismns as $ismn) {
    echo "{$ismn} : ".$noYes[Validate_ISPN::ismn($ismn)]."\n";
}

echo "\nTest ISRC\n";
foreach ($isrcs as $isrc) {
    echo "{$isrc} : ".$noYes[Validate_ISPN::isrc($isrc)]."\n";
}
?>
--EXPECT--
Test Validate_ISPN
******************

Test UCC12
614141210220 : YES
614141210221 : NO

Test EAN8
43210121 : YES
43210128 : NO

Test EAN13
1014541210223 : YES
1014541210228 : NO

Test EAN14
91014541210226 : YES
91014541210221 : NO

Test SSCC
106141411928374657 : YES
106141411928374651 : NO

Test ISSN
0366-3590 : YES
03663590 : YES
0004-6620 : YES
0394-6320 : YES
0395-7500 : YES
8675-4548 : YES
4342-7677 : YES
4545-6569 : YES
3434-6872 : YES
1044-789X : YES
1044-7890 : NO
1044-789 : NO
9685-5656 : NO
8768-4564 : NO
4564-7786 : NO
2317-8472 : NO
8675-4543 : NO
4342-7675 : NO
1044-789X : YES
1044-7890 : NO

Test ISBN10
0-06-064831-7 : YES
0-440-34319-4 : YES
ISBN 0-8436-1072-7 : YES
ISBN 0-7357-1410-X : YES
ISBN 0-7357-1410-0 : NO
ISBN 0-312-33177-0 : YES
0-312-33177-0 : YES
ISBN 0-201-63361-2 : YES
ISBN 0-201-63361-3 : NO
1-873671-00-4 : NO
1873671003 : NO
1-56619-909-2 : NO
1566199091 : NO
0735714100 : NO
013147149X : YES
1590593804 : YES
ISBN 0-672-32704-X : YES

Test ISBN13
978-1-873671-00-9 : YES
9781873671009 : YES
978-1-56619-909-4 : YES
9781566199094 : YES
978-0131471498 : YES
978-1590593806 : YES
978-1590593803 : NO
978-0672327049 : YES

Test ISMN
M-345-24680-5 : YES
2-345-24680-5 : NO
M-345-24680-4 : NO
M-2306-7118-7 : YES

Test ISRC
FR-Z03-98-00212 : YES
ISRC FR-Z03-98-00212 : YES
ISRC FR - Z03 - 98 - 00212 : YES
FR-Z03-91-01231 : YES
FR-Z03-91-01232 : YES
US-G34-04-25384 : YES
US-MR1-63-10018 : YES
34-234-34-12312 : NO
US-MR1-HE-ASDFG : NO