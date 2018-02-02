<?php
namespace VuFindLocalTemplate\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindDevTools\Controller\DevtoolsController' => 'VuFindDevTools\Controller\Factory::getDevtoolsController',
        ],
        'aliases' => [
            'DevTools' => 'VuFindDevTools\Controller\DevtoolsController',
        ],
    ],
    'router' => [
        'routes' => [
            'devtools-deminify' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/deminify',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Deminify',
                    ]
                ]
            ],
            'devtools-home' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/home',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'devtools-language' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
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
