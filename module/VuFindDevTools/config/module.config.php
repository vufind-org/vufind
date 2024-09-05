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
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/deminify',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Deminify',
                    ],
                ],
            ],
            'devtools-home' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/devtools[/home]',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Home',
                    ],
                ],
            ],
            'devtools-icon' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/icon',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Icon',
                    ],
                ],
            ],
            'devtools-language' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/language',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Language',
                    ],
                ],
            ],
            'devtools-permissions' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/permissions',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Permissions',
                    ],
                ],
            ],
        ],
    ],
];

return $config;
