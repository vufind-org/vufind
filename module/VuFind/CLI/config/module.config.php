<?php
namespace VuFind\CLI\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'import' => 'VuFind\CLI\Controller\ImportController',
        ),
    ),
);

return $config;