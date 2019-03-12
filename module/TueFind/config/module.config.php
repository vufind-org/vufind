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
            'TueFind\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\PDAProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\FulltextSnippetProxyController' => '\TueFind\Controller\FulltextSnippetProxyControllerFactory',
            'TueFind\Controller\ProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\QuickLinkController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\StaticPageController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'AJAX' => 'TueFind\Controller\AjaxController',
            'ajax' => 'TueFind\Controller\AjaxController',
            'Feedback' => 'TueFind\Controller\FeedbackController',
            'feedback' => 'TueFind\Controller\FeedbackController',
            'pdaproxy' => 'TueFind\Controller\PDAProxyController',
            'fulltextsnippetproxy' => 'TueFind\Controller\FulltextSnippetProxyController',
            'proxy' => 'TueFind\Controller\ProxyController',
            'QuickLink' => 'TueFind\Controller\QuickLinkController',
            'StaticPage' => 'TueFind\Controller\StaticPageController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'TueFind\ContentBlock\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Form\Form' => 'TueFind\Form\FormFactory',
            'TueFind\Mailer\Mailer' => 'TueFind\Mailer\Factory',
            'TueFind\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFindSearch\Service' => 'TueFind\Service\Factory::getSearchService',
        ],
        'aliases' => [
            'VuFind\ContentBlock\PluginManager' => 'TueFind\ContentBlock\PluginManager',
            'VuFind\Form\Form' => 'TueFind\Form\Form',
            'VuFind\Mailer\Mailer' => 'TueFind\Mailer\Mailer',
            'VuFind\RecordDriverPluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\Search' => 'TueFindSearch\Service',
            'VuFind\Search\Results\PluginManager' => 'TueFind\Search\Results\PluginManager',
            'VuFindSearch\Service' => 'TueFindSearch\Service',
        ],
    ],
    'view_manager' => [
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ]
];

$recordRoutes = [];
$dynamicRoutes = [];
$staticRoutes = [];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);



return $config;
