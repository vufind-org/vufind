--TEST--
Tests for rfc822 emails (malformed)
$Id$
--ARGS--
2>&1 <<INVALIDS
Just a string
string
(comment)
()@example.com
fred(&)barny@example.com
fred\ barny@example.com
Abigail <abi gail @ example.com>

# left over as for 0.6.3, this "(fo(o)" is considered ok in our parser
# but should certainly not according to rfc822 well formed comments (missing closing)
Abigail <abigail(fo(o)@example.com>

Abigail <abigail(fo)o)@example.com>
"Abi"gail" <abigail@example.com>
abigail@[exa]ple.com]
abigail@[exa[ple.com]
abigail@[exaple].com]
abigail@
@example.com
phrase: abigail@example.com abigail@example.com ;
invalid£char@example.com
INVALIDS
user@yahoo..com
--FILE--
<?php
require 'Validate.php';
$stdin = fopen('php://stdin', 'r');
while (!feof($stdin)) {
    $email = rtrim(fgets($stdin, 4096));
    if ($email && validate::email($email, array('use_rfc822' => true))) {
    	echo $email . " failed\n";
    }
}
?>
--EXPECT--
