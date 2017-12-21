<?php
namespace VuFindAdmin\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindAdmin\Controller\AdminController' => 'VuFindAdmin\Controller\Factory::getAdminController',
            'VuFindAdmin\Controller\ConfigController' => 'VuFindAdmin\Controller\Factory::getConfigController',
            'VuFindAdmin\Controller\MaintenanceController' => 'VuFindAdmin\Controller\Factory::getMaintenanceController',
            'VuFindAdmin\Controller\SocialController' => 'VuFindAdmin\Controller\Factory::getSocialstatsController',
            'VuFindAdmin\Controller\StatisticsController' => 'VuFindAdmin\Controller\Factory::getStatisticsController',
            'VuFindAdmin\Controller\TagsController' => 'VuFindAdmin\Controller\Factory::getTagsController',
        ],
        'aliases' => [
            'Admin' => 'VuFindAdmin\Controller\AdminController',
            'AdminConfig' => 'VuFindAdmin\Controller\ConfigController',
            'AdminMaintenance' => 'VuFindAdmin\Controller\MaintenanceController',
            'AdminSocial' => 'VuFindAdmin\Controller\SocialController',
            'AdminStatistics' => 'VuFindAdmin\Controller\StatisticsController',
            'AdminTags' => 'VuFindAdmin\Controller\TagsController',
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
