<?php // $Id$

/**
 * this helper function can uniformize layout of testing.
 *
 * @param string $func_name
 * @param array $data
 * @param array $scndParam
 * @return boolean
 */

function test_func($func_name, $data ,$scndParam=NULL)
{

    global $noYes;
    $noYes = array('KO', 'OK');

    if(is_null($scndParam)) $scndParam = array();
    if(!is_array($scndParam)) $scndParam = array($scndParam);

    echo "---------\nTest " . $func_name[0] . "::" . $func_name[1] . "\n";
    echo ' _ Value                  State Return' . "\n";
    echo ' V = validation result is right' . "\n";
    echo ' X = validation result is wrong' . "\n";
    foreach ($data as $value => $resultWaited) {
        if ((is_array($func_name) && 
            method_exists($func_name[0],$func_name[1])) || 
            function_exists($func_name)) {
            $result = $noYes[call_user_func_array($func_name,array_merge(array($value),$scndParam))];
        } else {
            echo 'Not found ' . implode('::',$func_name);
        }

        echo ($resultWaited == $result ? ' V ' : '!X!') .
            str_pad($value, 20) . " : ".$resultWaited . '    ' . $result ."\n";

    }
    return ($resultWaited != $result );

}

?>
