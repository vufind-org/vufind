<?php

namespace VuFindDevTools\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindDevTools\Controller\DevtoolsController' => 'VuFind\Controller\AbstractBaseFactory',
            \VuFindDevTools\Controller\PaymentServiceController::class => \VuFind\Controller\AbstractBaseFactory::class,
        ],
        'aliases' => [
            'DevTools' => 'VuFindDevTools\Controller\DevtoolsController',
            'PaymentService' => \VuFindDevTools\Controller\PaymentServiceController::class,
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
            'devtools-payment-init' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/payment/init',
                    'defaults' => [
                        'controller' => 'PaymentService',
                        'action'     => 'Init',
                    ],
                ],
            ],
            'devtools-payment-handle' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/payment/handle',
                    'defaults' => [
                        'controller' => 'PaymentService',
                        'action'     => 'Handle',
                    ],
                ],
            ],
            'devtools-payment-get-status' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/payment/status',
                    'defaults' => [
                        'controller' => 'PaymentService',
                        'action'     => 'Status',
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
