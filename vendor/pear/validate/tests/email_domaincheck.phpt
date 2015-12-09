--TEST--
domaincheck.phpt: Unit tests for email validation with dns checks
--SKIPIF--
<?php
if (!function_exists('checkdnsrr') || !checkdnsrr('php.net', 'A')) {
    echo 'skip Missing checkdnsrr()';
}
?>
--FILE--
<?php
// $Id: email.phpt 276490 2009-02-26 09:32:16Z amir $
// Validate test script
$noYes = array('NO', 'YES');
require 'Validate.php';

echo "Test Validate_Email\n";

$emails = array(
        // Try dns lookup
        array('pear-general@lists.php.net', true), // OK
        array('example@fluffffffrefrffrfrfrfrfrfr.is', true) // OK
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
pear-general@lists.php.net: with domain check : YES
example@fluffffffrefrffrfrfrfrfrfr.is: with domain check : NO