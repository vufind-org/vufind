<?php

namespace VuFindDevTools\Module\Configuration;

$config = [
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
                        'controller' => 'DevToolsPaymentService',
                        'action'     => 'Init',
                    ],
                ],
            ],
            'devtools-payment-handle' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/payment/handle',
                    'defaults' => [
                        'controller' => 'DevToolsPaymentService',
                        'action'     => 'Handle',
                    ],
                ],
            ],
            'devtools-payment-get-status' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/payment/status',
                    'defaults' => [
                        'controller' => 'DevToolsPaymentService',
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
            'devtools-translate' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/translate',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Translate',
                    ],
                ],
            ],
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'action' => [
                'autodiscovery_namespaces' => [
                    'VuFindDevTools\Action' => true,
                ],
                'aliases' => [
                ],
                'category_aliases' => [
                    'Devtools' => 'DevTools',
                    'Devtoolspaymentservice' => 'DevToolsPaymentService',
                ],
            ],
        ],
    ],
];

return $config;
