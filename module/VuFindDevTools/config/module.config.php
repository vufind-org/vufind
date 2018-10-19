<?php
namespace VuFindLocalTemplate\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindDevTools\Controller\DevtoolsController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'DevTools' => 'VuFindDevTools\Controller\DevtoolsController',
        ],
    ],
    'router' => [
        'routes' => [
            'devtools-deminify' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/deminify',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Deminify',
                    ]
                ]
            ],
            'devtools-home' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/home',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'devtools-language' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/language',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Language',
                    ]
                ]
            ],
        ],
    ],
];

return $config;
