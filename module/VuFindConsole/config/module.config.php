<?php
namespace VuFindConsole\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'generate' => 'VuFindConsole\Controller\GenerateController',
            'harvest' => 'VuFindConsole\Controller\HarvestController',
            'import' => 'VuFindConsole\Controller\ImportController',
            'language' => 'VuFindConsole\Controller\LanguageController',
            'util' => 'VuFindConsole\Controller\UtilController',
        ),
    ),
    'console' => array(
        'router'  => array(
          'router_class'  => 'VuFindConsole\Mvc\Router\ConsoleRouter',
        ),
    ),
    'view_manager' => array(
        // CLI tools are admin-oriented, so we should always output full errors:
        'display_exceptions' => true,
    ),
);

return $config;