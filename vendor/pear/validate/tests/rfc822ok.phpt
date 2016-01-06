--TEST--
Tests for rfc822 emails (well-formed)
$Id$
--ARGS--
2>&1 <<VALIDS
abigail@example.com
abigail@example.com 
 abigail@example.com
abigail @example.com
*@example.net
"\""@foo.bar
fred&barny@example.com
---@example.com
foo-bar@example.net
"127.0.0.1"@[127.0.0.1]
Abigail <abigail@example.com>
Abigail<abigail@example.com>
Abigail<@a,@b,@c:abigail@example.com>
"This is a phrase"<abigail@example.com>
"Abigail "<abigail@example.com>
"Joe & J. Harvey" <example @Org>
Abigail <abigail @ example.com>
Abigail made this <  abigail   @   example  .    com    >
Abigail(the bitch)@example.com
Abigail <abigail @ example . (bar) com >
Abigail < (one)  abigail (two) @(three)example . (bar) com (quz) >
Abigail (foo) (((baz)(nested) (comment)) ! ) < (one)  abigail (two) @(three)example . (bar) com (quz) >
Abigail <abigail(fo\(o)@example.com>
Abigail <abigail(fo\)o)@example.com>
(foo) abigail@example.com
abigail@example.com (foo)
"Abi\"gail" <abigail@example.com>
abigail@[example.com]
abigail@[exa\[ple.com]
abigail@[exa\]ple.com]
":sysmail"@  Some-Group. Some-Org
Muhammed.(I am  the greatest) Ali @(the)Vegas.WBA
mailbox.sub1.sub2@this-domain
sub-net.mailbox@sub-domain.domain
name:;
':;
name:   ;
Alfred Neuman <Neuman@BBN-TENEXA>
Neuman@BBN-TENEXA
"George, Ted" <Shared@Group.Arpanet>
Wilt . (the  Stilt) Chamberlain@NBA.US
Cruisers:  Port@Portugal, Jones@SEA;
\$@[]
*()@[]
"quoted ( brackets" ( a comment )@example.com
VALIDS
--FILE--
<?php
require 'Validate.php';
$stdin = fopen('php://stdin', 'r');
while (!feof($stdin)) {
    $email = rtrim(fgets($stdin, 4096));
    if ($email && !validate::email($email, array('use_rfc822' => true))) {
    	echo $email . " failed\n";
    }
}
?>
--EXPECT--
