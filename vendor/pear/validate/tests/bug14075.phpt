--TEST--
Validate::uri   Tag URIs
--FILE--
<?php
require_once 'Validate.php';
var_dump(Validate::uri('tag:sandro@hawke.org,2001-06-05:Taiko',
    array('allowed_schemes' => array("tag"))));
?>
--EXPECT--
bool(true)
