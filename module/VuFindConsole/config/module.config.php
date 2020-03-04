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
    'service_manager' => [
        'factories' => [
            'VuFind\Sitemap\Generator' => 'VuFind\Sitemap\GeneratorFactory',
            'VuFindConsole\Generator\GeneratorTools' => 'VuFindConsole\Generator\GeneratorToolsFactory',
        ],
    ],
    'view_manager' => [
        // CLI tools are admin-oriented, so we should always output full errors:
        'display_exceptions' => true,
    ],
];

$routes = [
    'compile/theme' => 'compile theme [--force] [<source>] [<target>]',
    'generate/dynamicroute' => 'generate dynamicroute [<name>] [<newController>] [<newAction>] [<module>]',
    'generate/extendclass' => 'generate extendclass [--extendfactory] [<class>] [<target>]',
    'generate/extendservice' => 'generate extendservice [<source>] [<target>]',
    'generate/nontabrecordaction' => 'generate nontabrecordaction [<newAction>] [<module>]',
    'generate/plugin' => 'generate plugin [<class>] [<factory>]',
    'generate/recordroute' => 'generate recordroute [<base>] [<newController>] [<module>]',
    'generate/staticroute' => 'generate staticroute [<name>] [<module>]',
    'generate/theme' => 'generate theme [<themename>]',
    'generate/thememixin' => 'generate thememixin [<name>]',
    'harvest/harvest_oai' => 'harvest harvest_oai [...params]',
    'harvest/merge-marc' => 'harvest merge-marc [<dir>]',
    'import/import-xsl' => 'import import-xsl [--test-only] [--index=] [<xml>] [<properties>]',
    'import/webcrawl' => 'import webcrawl [--test-only] [--index=]',
    'language/addusingtemplate' => 'language addusingtemplate [<target>] [<template>]',
    'language/copystring' => 'language copystring [<source>] [<target>]',
    'language/delete' => 'language delete [<target>]',
    'language/normalize' => 'language normalize [<target>]',
    'scheduledsearch/notify' => 'scheduledsearch notify',
    'util/cleanup_record_cache' => 'util (cleanuprecordcache|cleanup_record_cache) [--help|-h]',
    'util/commit' => 'util commit [<core>]',
    'util/createHierarchyTrees' => 'util createHierarchyTrees [--skip-xml|-sx] [--skip-json|-sj] [<backend>] [--help|-h]',
    'util/cssBuilder' => 'util cssBuilder [...themes]',
    'util/deletes' => 'util deletes [--verbose] [<filename>] [<format>] [<index>]',
    'util/expire_auth_hashes' => 'util expire_auth_hashes [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/expire_external_sessions' => 'util expire_external_sessions [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/expire_searches' => 'util expire_searches [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/expire_sessions' => 'util expire_sessions [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/index_reserves' => 'util index_reserves [--help|-h] [-d=s] [-t=s] [-f=s]',
    'util/lint_marc' => 'util lint_marc [<filename>]',
    'util/optimize' => 'util optimize [<core>]',
    'util/sitemap' => 'util sitemap [--help|-h] [--verbose] [--baseurl=s] [--basesitemapurl=s]',
    'util/suppressed' => 'util suppressed [--help|-h] [--authorities] [--outfile=s]',
    'util/switch_db_hash' => 'util switch_db_hash [<newhash>] [<newkey>]',
];

$routeGenerator = new \VuFindConsole\Route\RouteGenerator();
$routeGenerator->addRoutes($config, $routes);

return $config;
