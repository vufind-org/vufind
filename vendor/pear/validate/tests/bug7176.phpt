--TEST--
#7176, email RFC 822 
--FILE--
<?php
require_once 'Validate.php';
if (validate::email('Alfred Neuman <Neuman@BBN-TENEXA>', array('use_rfc822' => true))) {
	echo "Ok 1\n";
}
if (validate::email('"George, Ted" <Shared@Group.Arpanet>', array('use_rfc822' => true))) {
	echo "Ok 2\n";
}
if (validate::email('Wilt . (the  Stilt) Chamberlain@NBA.US', array('use_rfc822' => true))) {
	echo "Ok 3\n";
}
if (validate::email('Some User <user@example.com>', array('use_rfc822' => true))) {
	echo "Ok 4\n";
}
?>
--EXPECT--
Ok 1
Ok 2
Ok 3
Ok 4
