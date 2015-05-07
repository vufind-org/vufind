<?php
namespace FinnaConsole\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'util' => 'FinnaConsole\Controller\UtilController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\HMAC' => 'VuFind\Service\Factory::getHMAC'
        ]
    ]
];

return $config;
