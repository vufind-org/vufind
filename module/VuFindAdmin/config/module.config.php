<?php
namespace VuFindAdmin\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'admin' => 'VuFindAdmin\Controller\AdminController',
            'adminconfig' => 'VuFindAdmin\Controller\ConfigController',
            'adminsocial' => 'VuFindAdmin\Controller\SocialstatsController',
            'adminmaintenance' => 'VuFindAdmin\Controller\MaintenanceController',
            'adminstatistics' => 'VuFindAdmin\Controller\StatisticsController',
            'admintags' => 'VuFindAdmin\Controller\TagsController',
        ),
    ),
    'router' => array(
        'routes' => array(
            'admin' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Admin',
                    'defaults' => array(
                        'controller' => 'Admin',
                        'action'     => 'Home',
                    )
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'disabled' => array(
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route'    => '/Disabled',
                            'defaults' => array(
                                'controller' => 'Admin',
                                'action'     => 'Disabled',
                            )
                        )
                    ),
                    'config' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'    => '/Config[/:action]',
                            'defaults' => array(
                                'controller' => 'AdminConfig',
                                'action'     => 'Home',
                            )
                        )
                    ),
                    'maintenance' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'    => '/Maintenance[/:action]',
                            'defaults' => array(
                                'controller' => 'AdminMaintenance',
                                'action'     => 'Home',
                            )
                        )
                    ),
                    'social' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'    => '/Social[/:action]',
                            'defaults' => array(
                                'controller' => 'AdminSocial',
                                'action'     => 'Home',
                            )
                        )
                    ),
                    'statistics' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'    => '/Statistics[/:action]',
                            'defaults' => array(
                                'controller' => 'AdminStatistics',
                                'action'     => 'Home',
                            )
                        )
                    ),
                    'tags' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route'    => '/Tags[/:action]',
                            'defaults' => array(
                                'controller' => 'AdminTags',
                                'action'     => 'Home',
                            )
                        )
                    ),
                ),
            ),
        ),
    ),
);

return $config;