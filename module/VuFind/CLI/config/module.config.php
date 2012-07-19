<?php
namespace VuFind\CLI\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'harvest' => 'VuFind\CLI\Controller\HarvestController',
            'import' => 'VuFind\CLI\Controller\ImportController',
            'util' => 'VuFind\CLI\Controller\UtilController',
        ),
    ),
);

return $config;