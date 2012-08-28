<?php
namespace VuFindConsole\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'harvest' => 'VuFindConsole\Controller\HarvestController',
            'import' => 'VuFindConsole\Controller\ImportController',
            'util' => 'VuFindConsole\Controller\UtilController',
        ),
    ),
);

return $config;