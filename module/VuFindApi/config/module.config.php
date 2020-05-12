<?php
namespace VuFindApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindApi\Controller\ApiController' => 'VuFindApi\Controller\ApiControllerFactory',
            'VuFindApi\Controller\SearchApiController' => 'VuFindApi\Controller\SearchApiControllerFactory',
            'VuFindApi\Controller\Search2ApiController' => 'VuFindApi\Controller\Search2ApiControllerFactory',
        ],
        'aliases' => [
            'Api' => 'VuFindApi\Controller\ApiController',
            'SearchApi' => 'VuFindApi\Controller\SearchApiController',
            'Search2Api' => 'VuFindApi\Controller\Search2ApiController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFindApi\Formatter\FacetFormatter' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFindApi\Formatter\RecordFormatter' => 'VuFindApi\Formatter\RecordFormatterFactory',
            'VuFindApi\Formatter\Search2RecordFormatter' => 'VuFindApi\Formatter\Search2RecordFormatterFactory',
        ],
    ],
    'router' => [
        'routes' => [
            'apiHome' => [
                'type' => 'Laminas\Router\Http\Segment',
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
                'type' => 'Laminas\Router\Http\Literal',
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
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/record',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'record',
                    ]
                ]
            ],
            'search2Apiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/index2/search',
                    'defaults' => [
                        'controller' => 'Search2Api',
                        'action'     => 'search',
                    ]
                ]
            ],
            'record2Apiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/index2/record',
                    'defaults' => [
                        'controller' => 'Search2Api',
                        'action'     => 'record',
                    ]
                ]
            ]
        ],
    ],
];

return $config;
