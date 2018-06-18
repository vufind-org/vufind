<?php
namespace VuFindApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'api' => 'VuFindApi\Controller\Factory::getApiController',
            'searchapi' => 'VuFindApi\Controller\Factory::getSearchApiController',
        ]
    ],
    'router' => [
        'routes' => [
            'apiHome' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api[/v1][/]',
                    'defaults' => [
                        'controller' => 'Api',
                        'action'     => 'Index',
                    ]
                ],
            ],
            'searchApiv1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/search',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'search',
                    ]
                ]
            ],
            'recordApiv1' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/record',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'record',
                    ]
                ]
            ]
        ],
    ],
];

return $config;
