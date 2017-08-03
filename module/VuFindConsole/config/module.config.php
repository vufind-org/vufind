<?php
namespace VuFindConsole\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'compile' => 'VuFindConsole\Controller\Factory::getCompileController',
            'generate' => 'VuFindConsole\Controller\Factory::getGenerateController',
            'harvest' => 'VuFindConsole\Controller\Factory::getHarvestController',
            'import' => 'VuFindConsole\Controller\Factory::getImportController',
            'language' => 'VuFindConsole\Controller\Factory::getLanguageController',
            'redirect' => 'VuFindConsole\Controller\Factory::getRedirectController',
            'util' => 'VuFindConsole\Controller\Factory::getUtilController',
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

$routes = [
    'compile/theme' => 'compile theme [--force] [<source>] [<target>]',
    'generate/dynamicroute' => 'generate dynamicroute [<name>] [<newController>] [<newAction>] [<module>]',
    'generate/extendservice' => 'generate extendservice [<source>] [<target>]',
    'generate/nontabrecordaction' => 'generate nontabrecordaction [<newAction>] [<module>]',
    'generate/recordroute' => 'generate recordroute [<base>] [<newController>] [<module>]',
    'generate/staticroute' => 'generate staticroute [<name>] [<module>]',
    'generate/theme' => 'generate theme [<themename>]',
    'generate/thememixin' => 'generate thememixin [<name>]',
    // harvest/harvest_oai is too complex to represent here; we need to rely on default-route
    'harvest/merge-marc' => 'harvest merge-marc [<dir>]',
    'import/import-xsl' => 'import import-xsl [--test-only] [--index=] [<xml>] [<properties>]',
    'import/webcrawl' => 'import webcrawl [--test-only] [--index=]',
    'language/addusingtemplate' => 'language addusingtemplate [<target>] [<template>]',
    'language/copystring' => 'language copystring [<source>] [<target>]',
    'language/delete' => 'language delete [<target>]',
    'language/normalize' => 'language normalize [<target>]',
    'util/cleanup_record_cache' => 'util (cleanuprecordcache|cleanup_record_cache) [--help|-h]',
    'util/commit' => 'util commit [<core>]',
    'util/createHierarchyTrees' => 'util createHierarchyTrees [--skip-xml|-sx] [--skip-json|-sj] [--help|-h]',
    // util/cssBuilder relies on default-route because it has an arbitrary number of parameters
    'util/deletes' => 'util deletes [--verbose] [<filename>] [<format>] [<index>]',
    'util/expire_external_sessions' => 'util expire_external_sessions [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/expire_searches' => 'util expire_searches [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/expire_sessions' => 'util expire_sessions [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
    'util/index_reserves' => 'util index_reserves [--help|-h] [-d=s] [-t=s] [-f=s]',
    'util/optimize' => 'util optimize [<core>]',
    'util/sitemap' => 'util sitemap',
    'util/suppressed' => 'util suppressed [--help|-h] [--authorities] [--outfile=s]',
    'util/switch_db_hash' => 'util switch_db_hash [<newhash>] [<newkey>]',
];

$routeGenerator = new \VuFindConsole\Route\RouteGenerator();
$routeGenerator->addRoutes($config, $routes);

return $config;
