<?php
namespace VuFindAdmin\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'admin' => 'VuFindAdmin\Controller\Factory::getAdminController',
            'adminconfig' => 'VuFindAdmin\Controller\Factory::getConfigController',
            'adminsocial' => 'VuFindAdmin\Controller\Factory::getSocialstatsController',
            'adminmaintenance' => 'VuFindAdmin\Controller\Factory::getMaintenanceController',
            'adminstatistics' => 'VuFindAdmin\Controller\Factory::getStatisticsController',
            'admintags' => 'VuFindAdmin\Controller\Factory::getTagsController',
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
