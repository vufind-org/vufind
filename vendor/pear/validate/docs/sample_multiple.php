<?php
//include_once "Validate/FR.php";
require_once "Validate.php";
/*
test(Validate::creditCard('6762195515061813'), true);
// 4
test(Validate::creditCard('6762195515061814'), false);
// 5
*/
/*
function rib($aCodeBanque, $aCodeGuichet='', $aNoCompte='', $aKey='')
function number($number, $decimal = null, $dec_prec = null, $min = null, $max = null)
*/
$values = array(
    'amount'=> '13234,344343',
    'name'  => 'foo@example.com',
    'rib'   => array(
                'codebanque'   => '33287',
                'codeguichet'  => '00081',
                'nocompte'     => '00923458141C',
                'key'          => '52'
                ),
    'rib2'  => array(
                'codebanque'   => '12345',
                'codeguichet'  => '12345',
                'nocompte'     => '12345678901',
                'key'          => '46'
                ),
    'mail'  => 'foo@example.com',
    'hissiret' => '441 751 245 00016',
    'mystring' => 'ABCDEabcde',
    'iban'  => 'CH10002300A1023502601',
    'cep'   => '12345-123'
    );
$opts = array(
    'amount'=> array('type'=>'number','decimal'=>',.','dec_prec'=>null,'min'=>1,'max'=>32000),
    'name'  => array('type'=>'email','check_domain'=>false),
    'rib'   => array('type'=>'FR_rib'),
    'rib2'  => array('type'=>'FR_rib'),
    'mail'  => array('type'=>'email'),
    'hissiret' => array('type'=>'FR_siret'),
    'mystring' => array('type'=>'string',array('format'=>VALIDATE_ALPHA, 'min_length'=>3)),
    'iban'  => array('type'=>'Finance_iban'),
    'cep'   => array('type'=>'ptBR_postalcode')
    );

$result = Validate::multiple($values, $opts);

print_r($result);

?>
