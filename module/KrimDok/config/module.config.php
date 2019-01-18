<?php
namespace KrimDok\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'browse' => 'KrimDok\Controller\Factory::getBrowseController',
            'help' => 'KrimDok\Controller\Factory::getHelpController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'newitems' => 'KrimDok\Controller\Plugin\Factory::getNewItems',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'KrimDok\ILS\Driver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'KrimDok\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
        ],
        'aliases' => [
            'VuFind\ILSDriverPluginManager' => 'KrimDok\ILS\Driver\PluginManager',
            'VuFind\ILS\Driver\PluginManager' => 'KrimDok\ILS\Driver\PluginManager',
            'VuFind\RecordDriverPluginManager' => 'KrimDok\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'KrimDok\RecordDriver\PluginManager',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'recorddriver' => [
                'factories' => [
                    'solrdefault' => 'KrimDok\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'KrimDok\RecordDriver\Factory::getSolrMarc'
                ],
            ],
            'search_params' => [
                'abstract_factories' => ['KrimDok\Search\Params\PluginFactory'],
            ],
        ],
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Similar' => null,
                ],
            ],
        ],
    ],
];

$recordRoutes = [];
$dynamicRoutes = [];
$staticRoutes = [
    'Help/FAQ',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
