--TEST--
email.phpt: Unit tests for email validation
--FILE--
<?php
// $Id$
// Validate test script
$noYes = array('NO', 'YES');
require 'Validate.php';

echo "Test Validate_Email\n";

$emails = array(
        // with out the dns lookup
        'example@fluffffffrefrffrfrfrfrfrfr.is', // OK

        array('davidc@php.net', array('fullTLDValidation' => true, 'VALIDATE_GTLD_EMAILS' => true)),
        array('example (though bad)@example.com', array('use_rfc822' => true)), // OK
        'bugme@not./com', // OK

        // Some none english chars, those should fail until we fix the IDN stuff
        'hæjjæ@homms.com', // NOK
        'þæöð@example.com', // NOK
        'postmaster@tüv.de', // NOK

        // Test for various ways with _
        'mark_@example.com', // OK
        '_mark@example.com', // OK
        'mark_foo@example.com', // OK

        // Test for various ways with -
        'mark-@example.com', // OK
        '-mark@example.com', // OK
        'mark-foo@example.com', // OK

        // Test for various ways with .
        'mark.@example.com', // NOK
        '.mark@example.com', // NOK
        'mark.foo@example.com', // OK

        // Test for various ways with ,
        'mark,@example.com', // NOK
        ',mark@example.com', // NOK
        'mark,foo@example.com', // NOK

        // Test for various ways with :
        'mark:@example.com', // NOK
        ':mark@example.com', // NOK
        'mark:foo@example.com', // NOK

        // Test for various ways with ;
        'mark;@example.com', // NOK
        ';mark@example.com', // NOK
        'mark;foo@example.com', // NOK

        // Test for various ways with |
        'mark|@example.com', // OK
        '|mark@example.com', // OK
        'mark|foo@example.com', // OK

        // Test for various ways with double @
        'mark@home@example.com', // NOK
        'mark@example.home@com', // NOK
        'mark@example.com@home', // NOK

        // Killers ' tests
        'ha"ho@example.com', // NOK
        '<ha la la>blah</ha>@example.com', // NOK
        '<hablahha>@example.com', // NOK
        '"<ha la la>blah</ha>"@example.com', // OK
        '" "@example.com', // NOK
        '@example.com', // NOK

        // Minus ' tests (#5804)
        'minus@example-minus.com', // OK
        'minus@example.co-m', // OK
        'mi-nus@example-minus.co-m', // OK
        'minus@example-.com', // NOK
        'minus@-example.com', // NOK
        'minus@-.com', // NOK
        'minus@example.-com', // NOK
        'minus@-example.com-', // NOK

        // IP domain
        'ip@127.0.0.1', // OK
        '"the ip"@[127.0.0.1]', // OK
        'ip@127.0.333.1', // NOK
        'ip@[277.0.0.1]', // NOK
        'ip@[127.0.0.1', // NOK
        'ip@127.0.0.1]' // NOK
    );

list($version) = explode(".", phpversion(), 2);
foreach ($emails as $email) {
    if (is_array($email)) {
        echo "{$email[0]}:";
        if (!is_array($email[1])) {
            echo " with". ($email[1] ? '' : 'out') . ' domain check :';
        }
        echo ' ' . $noYes[Validate::email($email[0], $email[1])]."\n";
    } else {
        echo "{$email}: ";
        if ((int)$version > 4) {
            try {
                echo $noYes[Validate::email($email)]."\n";
            } catch (Exception $e) {
                echo $e->getMessage()."\n";
            }
        } else {
            echo $noYes[Validate::email($email)]."\n";
        }
    }
}
?>
--EXPECT--
Test Validate_Email
example@fluffffffrefrffrfrfrfrfrfr.is: YES
davidc@php.net: YES
example (though bad)@example.com: YES
bugme@not./com: YES
hæjjæ@homms.com: NO
þæöð@example.com: NO
postmaster@tüv.de: NO
mark_@example.com: YES
_mark@example.com: YES
mark_foo@example.com: YES
mark-@example.com: YES
-mark@example.com: YES
mark-foo@example.com: YES
mark.@example.com: NO
.mark@example.com: NO
mark.foo@example.com: YES
mark,@example.com: NO
,mark@example.com: NO
mark,foo@example.com: NO
mark:@example.com: NO
:mark@example.com: NO
mark:foo@example.com: NO
mark;@example.com: NO
;mark@example.com: NO
mark;foo@example.com: NO
mark|@example.com: YES
|mark@example.com: YES
mark|foo@example.com: YES
mark@home@example.com: NO
mark@example.home@com: NO
mark@example.com@home: NO
ha"ho@example.com: NO
<ha la la>blah</ha>@example.com: NO
<hablahha>@example.com: NO
"<ha la la>blah</ha>"@example.com: YES
" "@example.com: NO
@example.com: NO
minus@example-minus.com: YES
minus@example.co-m: YES
mi-nus@example-minus.co-m: YES
minus@example-.com: NO
minus@-example.com: NO
minus@-.com: NO
minus@example.-com: NO
minus@-example.com-: NO
ip@127.0.0.1: YES
"the ip"@[127.0.0.1]: YES
ip@127.0.333.1: NO
ip@[277.0.0.1]: NO
ip@[127.0.0.1: NO
ip@127.0.0.1]: NO
