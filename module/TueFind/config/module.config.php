<?php
namespace TueFind\Module\Config;

$config = [
    'router' => [
        'routes' => [
            'proxy-load' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/Proxy/Load',
                    'defaults' => [
                        'controller' => 'Proxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
            'pdaproxy-load' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/PDAProxy/Load',
                    'defaults' => [
                        'controller' => 'PDAProxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
            'fulltextsnippetproxy-load' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/FulltextSnippetProxy/Load',
                    'defaults' => [
                        'controller' => 'FulltextSnippetProxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
            'wikidataproxy-load' => [
                'type'    => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/WikidataProxy/Load',
                    'defaults' => [
                        'controller' => 'WikidataProxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
            'quicklink' => [
                'type'    => 'Zend\Router\Http\Segment',
                'options' => [
                    'route'    => '/r/[:id]',
                    'constraints' => [
                        'id'     => '[a-zA-Z0-9._-]+',
                    ],
                    'defaults' => [
                        'controller' => 'QuickLink',
                        'action'     => 'redirect',
                    ]
                ],
            ],
            'static-page' => [
                'type'    => 'Zend\Router\Http\Segment',
                'options' => [
                    'route'    => "/:page",
                    'constraints' => [
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'StaticPage',
                        'action'     => 'staticPage',
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'TueFind\Controller\AjaxController' => 'VuFind\Controller\AjaxControllerFactory',
            'TueFind\Controller\AuthorityController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\PDAProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\FulltextSnippetProxyController' => '\TueFind\Controller\FulltextSnippetProxyControllerFactory',
            'TueFind\Controller\ProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\QuickLinkController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'TueFind\Controller\RssFeedController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\StaticPageController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\WikidataProxyController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'AJAX' => 'TueFind\Controller\AjaxController',
            'ajax' => 'TueFind\Controller\AjaxController',
            'Authority' => 'TueFind\Controller\AuthorityController',
            'authority' => 'TueFind\Controller\AuthorityController',
            'Feedback' => 'TueFind\Controller\FeedbackController',
            'feedback' => 'TueFind\Controller\FeedbackController',
            'pdaproxy' => 'TueFind\Controller\PDAProxyController',
            'fulltextsnippetproxy' => 'TueFind\Controller\FulltextSnippetProxyController',
            'proxy' => 'TueFind\Controller\ProxyController',
            'QuickLink' => 'TueFind\Controller\QuickLinkController',
            'Record' => 'TueFind\Controller\RecordController',
            'record' => 'TueFind\Controller\RecordController',
            'RssFeed' => 'TueFind\Controller\RssFeedController',
            'rssfeed' => 'TueFind\Controller\RssFeedController',
            'StaticPage' => 'TueFind\Controller\StaticPageController',
            'wikidataproxy' => 'TueFind\Controller\WikidataProxyController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'TueFind\Controller\Plugin\Wikidata' => 'Zend\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'wikidata' => 'TueFind\Controller\Plugin\Wikidata',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'TueFind\ContentBlock\BlockLoader' => 'TueFind\ContentBlock\BlockLoaderFactory',
            'TueFind\ContentBlock\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Cookie\CookieManager' => 'VuFind\Cookie\CookieManagerFactory',
            'TueFind\Form\Form' => 'TueFind\Form\FormFactory',
            'TueFind\Mailer\Mailer' => 'TueFind\Mailer\Factory',
            'TueFind\MetadataVocabulary\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Record\FallbackLoader\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Record\Loader' => 'VuFind\Record\LoaderFactory',
            'TueFind\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\RecordTab\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFindSearch\Service' => 'VuFind\Service\SearchServiceFactory',
            'Zend\Session\SessionManager' => 'TueFind\Session\ManagerFactory',
        ],
        'initializers' => [
            'TueFind\ServiceManager\ServiceInitializer',
        ],
        'aliases' => [
            'VuFind\ContentBlock\BlockLoader' => 'TueFind\ContentBlock\BlockLoader',
            'VuFind\ContentBlock\PluginManager' => 'TueFind\ContentBlock\PluginManager',
            'VuFind\Cookie\CookieManager' => 'TueFind\Cookie\CookieManager',
            'VuFind\CookieManager' => 'TueFind\Cookie\CookieManager',
            'VuFind\Form\Form' => 'TueFind\Form\Form',
            'VuFind\Mailer\Mailer' => 'TueFind\Mailer\Mailer',
            'VuFind\Record\FallbackLoader\PluginManager' => 'TueFind\Record\FallbackLoader\PluginManager',
            'VuFind\Record\Loader' => 'TueFind\Record\Loader',
            'VuFind\RecordLoader' => 'TueFind\Record\Loader',
            'VuFind\RecordDriverPluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\RecordTabPluginManager' => 'TueFind\RecordTab\PluginManager',
            'VuFind\RecordTab\PluginManager' => 'TueFind\RecordTab\PluginManager',
            'VuFind\Search' => 'TueFindSearch\Service',
            'VuFind\Search\Results\PluginManager' => 'TueFind\Search\Results\PluginManager',
            'VuFindSearch\Service' => 'TueFindSearch\Service',
        ],
    ],
    'view_helpers' => [
        'initializers' => [
            'TueFind\ServiceManager\ServiceInitializer',
        ],
    ],
    'view_manager' => [
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'metadatavocabulary' => [],
        ],
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\SolrAuthMarc' => [
                'tabs' => [
                    'ExternalAuthorityDatabases' => 'ExternalAuthorityDatabases',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
        ],
    ],
];

$recordRoutes = [];
$dynamicRoutes = [];
$staticRoutes = ['RssFeed/Full'];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
