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
                    ]
                ]
            ],
            'devtools-home' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/devtools/home',
                    'defaults' => [
                        'controller' => 'DevTools',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'devtools-language' => [
                'type' => 'Laminas\Router\Http\Literal',
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
    'vufind' => [
        // List of prefixes leading to simpler (non-default) inflection.
        // Required to allow VuFind to load templates associated with this module
        // from themes, instead of using the default Laminas template loading logic.
        'template_injection' => ['VuFindDevTools/'],
    ],
];

return $config;
