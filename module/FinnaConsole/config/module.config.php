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
            'VuFind\HMAC' => 'VuFind\Service\Factory::getHMAC',
            'Finna\ClearMetalibSearch' => 'FinnaConsole\Service\Factory::getClearMetaLibSearch',
            'Finna\EncryptCatalogPasswords' => 'FinnaConsole\Service\Factory::getEncryptCatalogPasswords',
            'Finna\ExpireUsers' => 'FinnaConsole\Service\Factory::getExpireUsers',
            'Finna\OnlinePaymentMonitor' => 'FinnaConsole\Service\Factory::getOnlinePaymentMonitor',
            'Finna\ScheduledAlerts' => 'FinnaConsole\Service\Factory::getScheduledAlerts',
            'Finna\VerifyRecordLinks' => 'FinnaConsole\Service\Factory::getVerifyRecordLinks'
        ]
    ]
];

return $config;
