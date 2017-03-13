<?php
namespace FinnaApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'api' => 'FinnaApi\Controller\Factory::getApiController',
            'authapi' => 'FinnaApi\Controller\Factory::getAuthApiController',
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
            ],
            'apiHomeBareV1' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/v1[/]',
                    'defaults' => [
                        'controller' => 'Api',
                        'action'     => 'Index',
                    ]
                ],
            ],
            'authApiV1' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/auth/[:action]',
                    'defaults' => [
                        'controller' => 'AuthApi'
                    ]
                ]
            ],
            'searchApiBareV1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/v1/search',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'search',
                    ]
                ]
            ],
            'recordApiBareV1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/v1/record',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'record',
                    ]
                ]
            ]
        ]
    ]
];

return $config;
