<?php
namespace FinnaApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'api' => 'FinnaApi\Controller\Factory::getApiController',
            'searchapi' => 'FinnaApi\Controller\Factory::getSearchApiController'
        ],
        'invokables' => [
            'adminapi' => 'FinnaApi\Controller\AdminApiController'
        ]
    ],
    'router' => [
        'routes' => [
            'adminApi' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/adminapi[/v1][/]',
                    'defaults' => [
                        'controller' => 'AdminApi',
                        'action'     => 'Index',
                    ]
                ]
            ]
        ]
    ]
];

return $config;
