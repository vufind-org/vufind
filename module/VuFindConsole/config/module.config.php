<?php
namespace VuFindConsole\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'VuFindConsole\Controller\CompileController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\GenerateController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\HarvestController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\ImportController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\LanguageController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\RedirectController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\ScheduledSearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'VuFindConsole\Controller\UtilController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'compile' => 'VuFindConsole\Controller\CompileController',
            'generate' => 'VuFindConsole\Controller\GenerateController',
            'harvest' => 'VuFindConsole\Controller\HarvestController',
            'import' => 'VuFindConsole\Controller\ImportController',
            'language' => 'VuFindConsole\Controller\LanguageController',
            'redirect' => 'VuFindConsole\Controller\RedirectController',
            'scheduledsearch' => 'VuFindConsole\Controller\ScheduledSearchController',
            'util' => 'VuFindConsole\Controller\UtilController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\Sitemap\Generator' => 'VuFind\Sitemap\GeneratorFactory',
            'VuFindConsole\Command\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'VuFindConsole\ConsoleRunner' => 'VuFindConsole\ConsoleRunnerFactory',
            'VuFindConsole\Generator\GeneratorTools' => 'VuFindConsole\Generator\GeneratorToolsFactory',
        ],
    ],
    'view_manager' => [
        // CLI tools are admin-oriented, so we should always output full errors:
        'display_exceptions' => true,
    ],
    'vufind' => [
        'plugin_managers' => [
            'command' => [ /* see VuFindConsole\Command\PluginManager for defaults */ ],
        ],
    ],
];

return $config;
