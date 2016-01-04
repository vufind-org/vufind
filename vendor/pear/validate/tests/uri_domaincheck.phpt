--TEST--
domaincheck.phpt: Unit tests for uri validation with dns check
--SKIPIF--
<?php
if (!function_exists('checkdnsrr') || !checkdnsrr('php.net', 'A')) {
    echo 'skip Missing checkdnsrr()';
}
?>
--FILE--
<?php
// $Id: $
// Validate test script
$noYes = array('NO', 'YES');
require 'Validate.php';

echo "Test Validate::uri()\n";

$uris = array(
        // Try dns lookup
        array('//php.net', 'domain_check' => true), // OK
        array('//example.gor', 'domain_check' => true), // NOK
        // Try schemes lookup
        array('http://php.net', 'allowed_schemes' => array('ftp', 'http'),
                                    'domain_check' => true) // OK
    );

foreach ($uris as $uri) {
    if (is_array($uri)) {
        $options = $uri;
        unset($options[0]);
        echo "{$uri[0]}: schemes(" .
            (isset($options['allowed_schemes']) ?
                implode(',', $options['allowed_schemes']) : '') .") with".
            (isset($options['domain_check']) && $options['domain_check'] ?
                             '' : 'out') . ' domain check : '.
            (isset($options['strict']) ? "(strict : {$options['strict']}) " : '') .
            $noYes[Validate::uri($uri[0], $options )]."\n";
    } else {
        echo "{$uri}: ".
            $noYes[Validate::uri($uri)]."\n";
    }
}
?>
--EXPECT--
Test Validate::uri()
//php.net: schemes() with domain check : YES
//example.gor: schemes() with domain check : NO
http://php.net: schemes(ftp,http) with domain check : YES
