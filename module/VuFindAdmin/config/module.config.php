<?php
namespace VuFindAdmin\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'admin' => 'VuFindAdmin\Controller\AdminController',
            'adminconfig' => 'VuFindAdmin\Controller\ConfigController',
            'adminsocial' => 'VuFindAdmin\Controller\SocialstatsController',
            'adminmaintenance' => 'VuFindAdmin\Controller\MaintenanceController',
            'adminstatistics' => 'VuFindAdmin\Controller\StatisticsController',
            'admintags' => 'VuFindAdmin\Controller\TagsController',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/Admin',
                    'defaults' => [
                        'controller' => 'Admin',
                        'action'     => 'Home',
                    ]
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'disabled' => [
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/Disabled',
                            'defaults' => [
                                'controller' => 'Admin',
                                'action'     => 'Disabled',
                            ]
                        ]
                    ],
                    'config' => [
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Config[/:action]',
                            'defaults' => [
                                'controller' => 'AdminConfig',
                                'action'     => 'Home',
                            ]
                        ]
                    ],
                    'maintenance' => [
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Maintenance[/:action]',
                            'defaults' => [
                                'controller' => 'AdminMaintenance',
                                'action'     => 'Home',
                            ]
                        ]
                    ],
                    'social' => [
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Social[/:action]',
                            'defaults' => [
                                'controller' => 'AdminSocial',
                                'action'     => 'Home',
                            ]
                        ]
                    ],
                    'statistics' => [
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Statistics[/:action]',
                            'defaults' => [
                                'controller' => 'AdminStatistics',
                                'action'     => 'Home',
                            ]
                        ]
                    ],
                    'tags' => [
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/Tags[/:action]',
                            'defaults' => [
                                'controller' => 'AdminTags',
                                'action'     => 'Home',
                            ]
                        ]
                    ],
                ],
            ],
        ],
    ],
];

return $config;
