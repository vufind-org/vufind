<?php
namespace FinnaConsole\Module\Configuration;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'command' => [
                /* see VuFindConsole\Command\PluginManager for defaults */
                'factories' => [
                    'FinnaConsole\Command\ScheduledSearch\NotifyCommand' => 'VuFindConsole\Command\ScheduledSearch\NotifyCommandFactory',
                    'FinnaConsole\Command\Util\AccountExpirationReminders' => 'FinnaConsole\Command\Util\AccountExpirationRemindersFactory',
                    'FinnaConsole\Command\Util\DueDateReminders' => 'FinnaConsole\Command\Util\DueDateRemindersFactory',
                    'FinnaConsole\Command\Util\ExpireFinnaCacheCommand' => 'FinnaConsole\Command\Util\ExpireFinnaCacheCommandFactory',
                    'FinnaConsole\Command\Util\ExpireUsers' => 'FinnaConsole\Command\Util\ExpireUsersFactory',
                    'FinnaConsole\Command\Util\ImportComments' => 'FinnaConsole\Command\Util\ImportCommentsFactory',
                    'FinnaConsole\Command\Util\OnlinePaymentMonitor' => 'FinnaConsole\Command\Util\OnlinePaymentMonitorFactory',
                    'FinnaConsole\Command\Util\ScheduledAlerts' => 'VuFindConsole\Command\ScheduledSearch\NotifyCommandFactory',
                    'FinnaConsole\Command\Util\UpdateSearchHashes' => 'FinnaConsole\Command\Util\UpdateSearchHashesFactory',
                    'FinnaConsole\Command\Util\VerifyRecordLinks' => 'FinnaConsole\Command\Util\VerifyRecordLinksFactory',
                    'FinnaConsole\Command\Util\VerifyResourceMetadata' => 'FinnaConsole\Command\Util\VerifyResourceMetadataFactory',
                ],
                'aliases' => [
                    'util/account_expiration_reminders' => 'FinnaConsole\Command\Util\AccountExpirationReminders',
                    'util/due_date_reminders' => 'FinnaConsole\Command\Util\DueDateReminders',
                    'util/expire_finna_cache' => 'FinnaConsole\Command\Util\ExpireFinnaCacheCommand',
                    'util/expire_users' => 'FinnaConsole\Command\Util\ExpireUsers',
                    'util/import_comments' => 'FinnaConsole\Command\Util\ImportComments',
                    'util/online_payment_monitor' => 'FinnaConsole\Command\Util\OnlinePaymentMonitor',
                    'util/update_search_hashes' => 'FinnaConsole\Command\Util\UpdateSearchHashes',
                    'util/verify_record_links' => 'FinnaConsole\Command\Util\VerifyRecordLinks',
                    'util/verify_resource_metadata' => 'FinnaConsole\Command\Util\VerifyResourceMetadata',

                    'VuFindConsole\Command\ScheduledSearch\NotifyCommand' => 'FinnaConsole\Command\ScheduledSearch\NotifyCommand',

                    // Back-compatibility:
                    'util/scheduled_alerts' => 'FinnaConsole\Command\Util\ScheduledAlerts',
                ],
            ],
        ],
    ],
];

return $config;
