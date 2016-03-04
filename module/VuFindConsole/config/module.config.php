<?php
namespace VuFindConsole\Module\Configuration;

$config = [
    'controllers' => [
        'invokables' => [
            'generate' => 'VuFindConsole\Controller\GenerateController',
            'harvest' => 'VuFindConsole\Controller\HarvestController',
            'import' => 'VuFindConsole\Controller\ImportController',
            'language' => 'VuFindConsole\Controller\LanguageController',
            'util' => 'VuFindConsole\Controller\UtilController',
        ],
    ],
    'console' => [
        'router'  => [
          'router_class'  => 'VuFindConsole\Mvc\Router\ConsoleRouter',
        ],
    ],
    'view_manager' => [
        // CLI tools are admin-oriented, so we should always output full errors:
        'display_exceptions' => true,
    ],
];

return $config;
