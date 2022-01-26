<?php
namespace KrimDok\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'KrimDok\Controller\BrowseController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'KrimDok\Controller\HelpController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'Browse' => 'KrimDok\Controller\BrowseController',
            'browse' => 'KrimDok\Controller\BrowseController',
            'Help' => 'KrimDok\Controller\HelpController',
            'help' => 'KrimDok\Controller\HelpController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'KrimDok\Controller\Plugin\NewItems' => 'VuFind\Controller\Plugin\NewItemsFactory',
        ],
        'aliases' => [
            'newItems' => 'KrimDok\Controller\Plugin\NewItems',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\Search\BackendManager' => 'KrimDok\Search\BackendManagerFactory',
            'KrimDok\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'KrimDok\Search\Params\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'KrimDok\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory'
        ],
        'aliases' => [
            'VuFind\RecordDriverPluginManager' => 'KrimDok\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'KrimDok\RecordDriver\PluginManager',
            'VuFind\SearchParamsPluginManager' => 'KrimDok\Search\Params\PluginManager',
            'VuFind\Search\Params\PluginManager' => 'KrimDok\Search\Params\PluginManager',
            'VuFind\Search\Results\PluginManager' => 'KrimDok\Search\Results\PluginManager'
        ],
    ],
    'vufind' => [
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Similar' => null,
                ],
            ],
        ],
    ],
];

$recordRoutes = [ 'search2record' => 'Search2Record' ];
$dynamicRoutes = [];
$staticRoutes = [
    'Help/FAQ',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
