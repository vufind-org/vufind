<?php
namespace VuDL\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'vudl' => 'VuDL\Controller\VudlController'
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuDL\Connection\Manager' => 'VuDL\Factory::getConnectionManager',
            'VuDL\Connection\Fedora' => 'VuDL\Factory::getConnectionFedora',
            'VuDL\Connection\Solr' => 'VuDL\Factory::getConnectionSolr',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'recorddriver' => [
                'factories' => [
                    'solrvudl' => 'VuDL\Factory::getRecordDriver',
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'files' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/files/:id/:type'
                ]
            ],
            'vudl-about' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/VuDL/About',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'About',
                    ]
                ]
            ],
            'vudl-default-collection' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Collection[/]',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'Collections'
                    ]
                ]
            ],
            'vudl-grid' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Grid/:id',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'Grid'
                    ]
                ]
            ],
            'vudl-home' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/VuDL/Home[/]',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'vudl-record' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Item/:id',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'Record'
                    ]
                ]
            ],
            'vudl-sibling' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Vudl/Sibling/',
                    'defaults' => [
                        'controller' => 'VuDL',
                        'action'     => 'Sibling'
                    ]
                ]
            ],
        ]
    ],
];

return $config;
