<?php

namespace VuFindConsole\Module\Configuration;

$config = [
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
