<?php
namespace Ixtheo\Module\Config;

$config = [
    'router' => [
        'routes' => [
            'classification' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/classification[/:notation]',
                    'constraints' => [
                        'notation' => '[a-zA-Z][a-zA-Z]*',
                    ],
                    'defaults' => [
                        'controller' => 'Classification',
                        'action'     => 'Home',
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'IxTheo\Controller\AlphabrowseController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\BrowseController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'IxTheo\Controller\ClassificationController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'IxTheo\Controller\Search\KeywordChainSearchController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'Alphabrowse' => 'IxTheo\Controller\AlphabrowseController',
            'alphabrowse' => 'IxTheo\Controller\AlphabrowseController',
            'Browse' => 'IxTheo\Controller\BrowseController',
            'browse' => 'IxTheo\Controller\BrowseController',
            'Classification' => 'IxTheo\Controller\ClassificationController',
            'classification' => 'IxTheo\Controller\ClassificationController',
            'KeywordChainSearch' => 'IxTheo\Controller\Search\KeywordChainSearchController',
            'Keywordchainsearch' => 'IxTheo\Controller\Search\KeywordChainSearchController',
            'MyResearch' => 'IxTheo\Controller\MyResearchController',
            'myresearch' => 'IxTheo\Controller\MyResearchController',
            'Record' => 'IxTheo\Controller\RecordController',
            'record' => 'IxTheo\Controller\RecordController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'IxTheo\Controller\Plugin\NewItems' => 'VuFind\Controller\Plugin\NewItemsFactory',
            'IxTheo\Controller\Plugin\Subscriptions' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'IxTheo\Controller\Plugin\PDASubscriptions' => 'IxTheo\Controller\Plugin\PDASubscriptionsFactory',
        ],
        'aliases' => [
            'newItems' => 'IxTheo\Controller\Plugin\NewItems',
            'pdasubscriptions' => 'IxTheo\Controller\Plugin\PDASubscriptions',
            'PDASubscriptions' => 'IxTheo\Controller\Plugin\PDASubscriptions',
            'subscriptions' => 'IxTheo\Controller\Plugin\Subscriptions',
            'Subscriptions' => 'IxTheo\Controller\Plugin\Subscriptions',

        ],
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\Search\BackendManager' => 'IxTheo\Search\BackendManagerFactory',

            'IxTheo\Auth\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Autocomplete\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Db\Row\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Db\Table\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Export' => 'VuFind\ExportFactory',
            'IxTheo\Recommend\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Options\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Params\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\RecordTab\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
        ],
        'aliases' => [
            'VuFind\AuthPluginManager' => 'IxTheo\Auth\PluginManager',
            'VuFind\Auth\PluginManager' => 'IxTheo\Auth\PluginManager',
            'VuFind\Autocomplete\PluginManager' => 'IxTheo\Autocomplete\PluginManager',
            'VuFind\DbRowPluginManager' => 'IxTheo\Db\Row\PluginManager',
            'VuFind\Db\Row\PluginManager' => 'IxTheo\Db\Row\PluginManager',
            'VuFind\DbTablePluginManager' => 'IxTheo\Db\Table\PluginManager',
            'VuFind\Db\Table\PluginManager' => 'IxTheo\Db\Table\PluginManager',
            'VuFind\Export' => 'IxTheo\Export',
            'VuFind\RecommendPluginManager' => 'IxTheo\Recommend\PluginManager',
            'VuFind\Recommend\PluginManager' => 'IxTheo\Recommend\PluginManager',
            'VuFind\RecordDriverPluginManager' => 'IxTheo\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'IxTheo\RecordDriver\PluginManager',
            'VuFind\Search\Options\PluginManager' => 'IxTheo\Search\Options\PluginManager',
            'VuFind\Search\Params\PluginManager' => 'IxTheo\Search\Params\PluginManager',
            'VuFind\Search\Results\PluginManager' => 'IxTheo\Search\Results\PluginManager',
            'VuFind\RecordTab\PluginManager' => 'IxTheo\RecordTab\PluginManager',
        ],
    ],
];

$recordRoutes = [
    // needs to be registered again even if already registered in parent module,
    // for the nonTabRecordActions added in \IxTheo\Route\RouteGenerator
    'record' => 'Record',
    'search2record' => 'Search2Record',
];
$dynamicRoutes = [];
$staticRoutes = [
    'Browse/IxTheo-Classification',
    'Browse/Publisher',
    'Browse/RelBib-Classification',
    'Keywordchainsearch/Home',
    'Keywordchainsearch/Results',
    'Keywordchainsearch/Search',
    'MyResearch/Subscriptions',
    'MyResearch/DeleteSubscription',
    'MyResearch/PDASubscriptions',
    'MyResearch/DeletePDASubscription',
    'Classification/Home'
];

$routeGenerator = new \IxTheo\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
