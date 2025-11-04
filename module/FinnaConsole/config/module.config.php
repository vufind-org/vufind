<?php

namespace FinnaConsole\Module\Configuration;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'command' => [
                /* see VuFindConsole\Command\PluginManager for defaults */
                'factories' => [
                    'FinnaConsole\Command\Lists\ListProtected' => 'FinnaConsole\Command\Lists\ProtectedHandlerFactory',
                    'FinnaConsole\Command\Lists\Protect' => 'FinnaConsole\Command\Lists\ProtectedHandlerFactory',
                    'FinnaConsole\Command\Lists\Unprotect' => 'FinnaConsole\Command\Lists\ProtectedHandlerFactory',
                    'FinnaConsole\Command\ScheduledSearch\NotifyCommand' => 'VuFindConsole\Command\ScheduledSearch\NotifyCommandFactory',
                    'FinnaConsole\Command\Users\ListProtected' => 'FinnaConsole\Command\Users\ProtectedHandlerFactory',
                    'FinnaConsole\Command\Users\Protect' => 'FinnaConsole\Command\Users\ProtectedHandlerFactory',
                    'FinnaConsole\Command\Users\Unprotect' => 'FinnaConsole\Command\Users\ProtectedHandlerFactory',
                    'FinnaConsole\Command\Util\AccountExpirationReminders' => 'FinnaConsole\Command\Util\AccountExpirationRemindersFactory',
                    'FinnaConsole\Command\Util\DueDateReminders' => 'FinnaConsole\Command\Util\DueDateRemindersFactory',
                    'FinnaConsole\Command\Util\ExpireFinnaCacheCommand' => 'FinnaConsole\Command\Util\ExpireFinnaCacheCommandFactory',
                    'FinnaConsole\Command\Util\ExpireUsers' => 'FinnaConsole\Command\Util\ExpireUsersFactory',
                    'FinnaConsole\Command\Util\ImportComments' => 'FinnaConsole\Command\Util\ImportCommentsFactory',
                    'FinnaConsole\Command\Util\ProcessRecordStatsLog' => 'FinnaConsole\Command\Util\ProcessRecordStatsLogFactory',
                    'FinnaConsole\Command\Util\ProcessStatsQueue' => 'FinnaConsole\Command\Util\ProcessStatsQueueFactory',
                    'FinnaConsole\Command\Util\ScheduledAlerts' => 'VuFindConsole\Command\ScheduledSearch\NotifyCommandFactory',
                    'FinnaConsole\Command\Util\VerifyRecordLinks' => 'FinnaConsole\Command\Util\VerifyRecordLinksFactory',
                    'FinnaConsole\Command\Util\VerifyResourceMetadata' => 'FinnaConsole\Command\Util\VerifyResourceMetadataFactory',
                ],
                'aliases' => [
                    'lists/list_protected' => 'FinnaConsole\Command\Lists\ListProtected',
                    'lists/protect' => 'FinnaConsole\Command\Lists\Protect',
                    'lists/unprotect' => 'FinnaConsole\Command\Lists\Unprotect',
                    'users/list_protected' => 'FinnaConsole\Command\Users\ListProtected',
                    'users/protect' => 'FinnaConsole\Command\Users\Protect',
                    'users/unprotect' => 'FinnaConsole\Command\Users\Unprotect',
                    'util/account_expiration_reminders' => 'FinnaConsole\Command\Util\AccountExpirationReminders',
                    'util/due_date_reminders' => 'FinnaConsole\Command\Util\DueDateReminders',
                    'util/expire_finna_cache' => 'FinnaConsole\Command\Util\ExpireFinnaCacheCommand',
                    'util/expire_users' => 'FinnaConsole\Command\Util\ExpireUsers',
                    'util/import_comments' => 'FinnaConsole\Command\Util\ImportComments',
                    'util/process_record_stats' => 'FinnaConsole\Command\Util\ProcessRecordStatsLog',
                    'util/verify_record_links' => 'FinnaConsole\Command\Util\VerifyRecordLinks',
                    'util/verify_resource_metadata' => 'FinnaConsole\Command\Util\VerifyResourceMetadata',

                    'VuFindConsole\Command\ScheduledSearch\NotifyCommand' => 'FinnaConsole\Command\ScheduledSearch\NotifyCommand',

                    // Back-compatibility:
                    'util/scheduled_alerts' => 'FinnaConsole\Command\Util\ScheduledAlerts',
                    'util/online_payment_monitor' => \VuFindConsole\Command\OnlinePayment\MonitorCommand::class,
                ],
            ],
        ],
    ],
];

return $config;
