<?php
namespace VuFindConsole\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'generate' => 'VuFindConsole\Controller\GenerateController',
            'harvest' => 'VuFindConsole\Controller\HarvestController',
            'import' => 'VuFindConsole\Controller\ImportController',
            'language' => 'VuFindConsole\Controller\LanguageController',
            'redirect' => 'VuFindConsole\Controller\RedirectController',
            'util' => 'VuFindConsole\Controller\UtilController',
        ],
    ],
    'console' => [
        'router'  => [
            'routes'  => [
                'default-route' => [
                    'type' => 'catchall',
                    'options' => [
                        'route' => '',
                        'defaults' => [
                            'controller' => 'redirect',
                            'action' => 'consoledefault',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        // CLI tools are admin-oriented, so we should always output full errors:
        'display_exceptions' => true,
    ],
];

return $config;
