<?php

namespace VuFindAdmin\Module\Configuration;

$config = [
    'router' => [
        'routes' => [
            'admin' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/Admin',
                    'defaults' => [
                        'controller' => 'Admin',
                        'action'     => 'Home',
                        'admin_route' => true,
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'config' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Config[/:action]',
                            'defaults' => [
                                'controller' => 'AdminConfig',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'feedback-details' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Feedback/Details/:id',
                            'defaults' => [
                                'controller' => 'AdminFeedback',
                                'action'     => 'Details',
                            ],
                        ],
                    ],
                    'feedback' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Feedback[/:action]',
                            'defaults' => [
                                'controller' => 'AdminFeedback',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'maintenance' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Maintenance[/:action]',
                            'defaults' => [
                                'controller' => 'AdminMaintenance',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'notices' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Notices[/:action][?notice_id=:notice_id]',
                            'defaults' => [
                                'controller' => 'AdminNotices',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'script' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Script[/:name]',
                            'defaults' => [
                                'controller' => 'AdminMaintenance',
                                'action'     => 'Script',
                            ],
                        ],
                    ],
                    'social' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Social[/:action]',
                            'defaults' => [
                                'controller' => 'AdminSocial',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'tags' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Tags[/:action]',
                            'defaults' => [
                                'controller' => 'AdminTags',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'overdrive' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Overdrive',
                            'defaults' => [
                                'controller' => 'AdminOverdrive',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'payment' => [
                        'type' => 'Laminas\Router\Http\Literal',
                        'options' => [
                            'route'    => '/Payment',
                            'defaults' => [
                                'controller' => 'AdminPayment',
                                'action'     => 'Home',
                            ],
                        ],
                    ],
                    'payment-details' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Payment/:id/Details',
                            'defaults' => [
                                'controller' => 'AdminPayment',
                                'action'     => 'Details',
                            ],
                        ],
                    ],
                    'payment-resolve' => [
                        'type' => 'Laminas\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Payment/:id/Resolve',
                            'defaults' => [
                                'controller' => 'AdminPayment',
                                'action'     => 'Resolve',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'vufind' => [
        'action_config' => [
            'vufindadmin_admin' => [
                'actionIds' => [
                    [
                        'type' => 'prefix',
                        'prefix' => 'admin/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminconfig/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminfeedback/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminmaintenance/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminnotices/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminpayment/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminoverdrive/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'adminsocial/',
                    ],
                    [
                        'type' => 'prefix',
                        'prefix' => 'admintags/',
                    ],
                ],
                'accessPermission' => 'access.AdminModule',
            ],
            'vufindadmin_notices' => [
                'actionIds' => [
                    [
                        'type' => 'prefix',
                        'prefix' => 'notices/',
                    ],
                ],
                'accessPermission' => 'access.AdminModule',
            ],
        ],
        'plugin_managers' => [
            'action' => [
                'autodiscovery_namespaces' => [
                    'VuFindAdmin\Action' => true,
                ],
                'aliases' => [
                    'config/enableautoconfig' => \VuFindAdmin\Action\AdminConfig\EnableAutoConfigAction::class,
                    'feedback/updatestatus' => \VuFindAdmin\Action\AdminFeedback\UpdateStatusAction::class,
                    'maintenance/clearcache' => \VuFindAdmin\Action\AdminMaintenance\ClearCacheAction::class,
                    'maintenance/deleteexpiredsearches'
                        => \VuFindAdmin\Action\AdminMaintenance\DeleteExpiredSearchesAction::class,
                    'maintenance/deleteexpiredsessions'
                        => \VuFindAdmin\Action\AdminMaintenance\DeleteExpiredSessionsAction::class,
                    'maintenance/updatebrowscapcache'
                        => \VuFindAdmin\Action\AdminMaintenance\UpdateBrowscapCacheAction::class,
                ],
                'category_aliases' => [
                    'Adminconfig' => 'AdminConfig',
                    'Adminfeedback' => 'AdminFeedback',
                    'Adminmaintenance' => 'AdminMaintenance',
                    'Adminnotices' => 'AdminNotices',
                    'Adminoverdrive' => 'AdminOverdrive',
                    'Adminpayment' => 'AdminPayment',
                    'Adminsocial' => 'AdminSocial',
                    'Admintags' => 'AdminTags',
                ],
            ],
        ],
    ],
];

return $config;
