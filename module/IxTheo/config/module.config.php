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
            'db_row' => [
                'factories' => [
                    'IxTheoUser' => 'IxTheo\Db\Row\Factory::getIxTheoUser',
                    'pdasubscription' => 'IxTheo\Db\Row\Factory::getPDASubscription',
                    'subscription' => 'IxTheo\Db\Row\Factory::getSubscription',
                ],
            ],
            'db_table' => [
                'factories' => [
                    'IxTheoUser' => 'IxTheo\Db\Table\Factory::getIxTheoUser',
                    'pdasubscription' => 'IxTheo\Db\Table\Factory::getPDASubscription',
                    'subscription' => 'IxTheo\Db\Table\Factory::getSubscription',
                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'solrdefault' => 'IxTheo\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'IxTheo\RecordDriver\Factory::getSolrMarc',
                ],
            ],
            'search_backend' => [
            	'factories' => [
                    'Solr' => 'IxTheo\Search\Factory\SolrDefaultBackendFactory',
                ],
            ],
            'search_options' => [
                'factories' => [
                    'KeywordChainSearch' => 'IxTheo\Search\Options\Factory::getKeywordChainSearch',
                    'PDASubscriptions' => 'IxTheo\Search\Options\Factory::getPDASubscriptions',
                    'Subscriptions' => 'IxTheo\Search\Options\Factory::getSubscriptions',
                ],
            ],
            'search_params' => [
                'abstract_factories' => ['IxTheo\Search\Params\PluginFactory'],
            ],
            'search_results' => [
                'factories' => [
                    'KeywordChainSearch' => 'IxTheo\Search\Results\Factory::getKeywordChainSearch',
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
            'alphabrowse' => 'IxTheo\Controller\Factory::getAlphabrowseController',
            'browse' => 'IxTheo\Controller\Factory::getBrowseController',
            'feedback' => 'IxTheo\Controller\Factory::getFeedbackController',
            'KeywordChainSearch' => 'IxTheo\Controller\Factory::getKeywordChainSearchController',
            'MyResearch' => 'IxTheo\Controller\Factory::getMyResearchController',
            'Pipeline' => 'IxTheo\Controller\Factory::getPipelineController',
            'record' => 'IxTheo\Controller\Factory::getRecordController',
            'search' => 'IxTheo\Controller\Factory::getSearchController',
            'StaticPage' => 'IxTheo\Controller\Factory::getStaticPageController',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'subscriptions' => 'IxTheo\Controller\Plugin\Subscriptions',
            'pdasubscriptions' => 'IxTheo\Controller\Plugin\PDASubscriptions',
        ]
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\Mailer' => 'IxTheo\Mailer\Factory',
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
    'Biblerangesearch/Home',
    'Keywordchainsearch/Home',
    'Keywordchainsearch/Results',
    'Keywordchainsearch/Search',
    'MyResearch/Subscriptions',
    'MyResearch/DeleteSubscription',
    'MyResearch/PDASubscriptions',
    'MyResearch/DeletePDASubscription',
    'Pipeline/Home',
];

$config['router']['routes']['static-page'] = [
    'type'    => 'Zend\Mvc\Router\Http\Segment',
    'options' => [
        'route'    => "/:page",
        'constraints' => [
            'page'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'StaticPage',
            'action'     => 'staticPage',
        ]
    ]
];

$routeGenerator = new \IxTheo\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
