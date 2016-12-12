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
            'Finna\DueDateReminders' => 'FinnaConsole\Service\Factory::getDueDateReminders',
            'Finna\EncryptCatalogPasswords' => 'FinnaConsole\Service\Factory::getEncryptCatalogPasswords',
            'Finna\ExpireUsers' => 'FinnaConsole\Service\Factory::getExpireUsers',
            'Finna\OnlinePaymentMonitor' => 'FinnaConsole\Service\Factory::getOnlinePaymentMonitor',
            'Finna\ScheduledAlerts' => 'FinnaConsole\Service\Factory::getScheduledAlerts',
            'Finna\UpdateSearchHashes' => 'FinnaConsole\Service\Factory::getUpdateSearchHashes',
            'Finna\VerifyRecordLinks' => 'FinnaConsole\Service\Factory::getVerifyRecordLinks'
        ]
    ]
];

$routes = [
    'util/due_date_reminders' => 'util due_date_reminders <vufind_dir> <view_dir>',
    'util/encrypt_catalog_passwords' => 'util encrypt_catalog_passwords Y',
    'util/expire_users' => 'util expire_users <days>',
    'util/online_payment_monitor' => 'util online_payment_monitor <expire_hours> <from_email> <report_interval_hours>',
    'util/scheduled_alerts' => 'util scheduled_alerts <view_base_directory> <VuFind_local_configuration_directory>',
    'util/update_search_hashes' => 'util update_search_hashes Y',
    'util/verify_record_links' => 'util verify_record_links'
];

$routeGenerator = new \VuFindConsole\Route\RouteGenerator();
$routeGenerator->addRoutes($config, $routes);

return $config;
