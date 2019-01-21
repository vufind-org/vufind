<?php
namespace Ixtheo\Module\Config;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'auth' => [
                'invokables' => [
                    'database' => 'IxTheo\Auth\Database',
                ],
            ],
            'autocomplete' => [
                'factories' => [
                    'solr' => 'IxTheo\Autocomplete\Factory::getSolr',
                ],
            ],
            'db_table' => [
                'factories' => [
                    'tags' => 'IxTheo\Db\Table\Factory::getTags',
                ],
            ],
            'recommend' => [
                'invokables' => [
                    'bibleranges' => 'IxTheo\Recommend\BibleRanges',
                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'solrdefault' => 'IxTheo\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'IxTheo\RecordDriver\Factory::getSolrMarc',
                ],
            ],
            'search_options' => [
                'factories' => [
                    'PDASubscriptions' => 'IxTheo\Search\Options\Factory::getPDASubscriptions',
                    'Subscriptions' => 'IxTheo\Search\Options\Factory::getSubscriptions',
                ],
            ],
            'search_results' => [
                'factories' => [
                    'pdasubscriptions' => 'IxTheo\Search\Results\Factory::getPDASubscriptions',
                    'Subscriptions' => 'IxTheo\Search\Results\Factory::getSubscriptions',
                ],
            ],
        ],
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    // Disable certain tabs (overwrite value with null)
                    'Excerpt' => null,
                    'HierarchyTree' => null,
                    'Holdings' => null,
                    'Map' => null,
                    'Preview' => null,
                    'Reviews' => null,
                    'Similar' => null,
                    'TOC' => null,
                    'UserComments' => null,
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'IxTheo\Controller\AlphabrowseController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\BrowseController' => 'VuFind\Controller\BrowseControllerFactory',
            'IxTheo\Controller\ClassificationController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'IxTheo\Controller\RecordController' => 'VuFind\Controller\RecordControllerFactory',
            'IxTheo\Controller\Search\KeywordChainSearchController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'alphabrowse' => 'IxTheo\Controller\AlphabrowseController',
            'browse' => 'IxTheo\Controller\BrowseController',
            'classification' => 'IxTheo\Controller\ClassificationController',
            'KeywordChainSearch' => 'IxTheo\Controller\Search\KeywordChainSearchController',
            'Keywordchainsearch' => 'IxTheo\Controller\Search\KeywordChainSearchController',
            'MyResearch' => 'IxTheo\Controller\MyResearchController',
            'record' => 'IxTheo\Controller\RecordController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'subscriptions' => 'IxTheo\Controller\Plugin\Factory::getSubscriptions',
            'pdasubscriptions' => 'IxTheo\Controller\Plugin\Factory::getPDASubscriptions',
        ]
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\AuthManager' => 'IxTheo\Auth\Factory::getManager',
            'VuFind\Export' => 'IxTheo\Service\Factory::getExport',
            'VuFind\Mailer' => 'IxTheo\Mailer\Factory',
            'VuFind\Search\BackendManager' => 'IxTheo\Search\BackendManagerFactory',
            'VuFind\Db\Row\PluginManager' => 'IxTheo\ServiceManager\AbstractPluginManagerFactory',
            'VuFind\Db\Table\PluginManager' => 'IxTheo\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\ContentBlock\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Options\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Params\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'IxTheo\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
        ],
        'aliases' => [
            'VuFind\ContentBlock\PluginManager' => 'IxTheo\ContentBlock\PluginManager',
            'VuFind\RecordDriverPluginManager' => 'IxTheo\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'IxTheo\RecordDriver\PluginManager',
            'VuFind\Search\Options\PluginManager' => 'IxTheo\Search\Options\PluginManager',
            'VuFind\Search\Params\PluginManager' => 'IxTheo\Search\Params\PluginManager',
            'VuFind\Search\Results\PluginManager' => 'IxTheo\Search\Results\PluginManager',
        ],
    ],
    'router' => [
        'routes' => [
            'classification' => [
                'type' => 'Zend\Router\Http\Segment',
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
];

$recordRoutes = [
    // needs to be registered again even if already registered in parent module,
    // for the nonTabRecordActions added in \IxTheo\Route\RouteGenerator
    'record' => 'Record',
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
