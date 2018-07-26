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
        ],
    ],
    'controllers' => [
        'factories' => [
            'TueFind\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\PDAProxyController' => 'TueFind\Controller\PDAProxyControllerFactory',
            'TueFind\Controller\ProxyController' => 'TueFind\Controller\ProxyControllerFactory',
            'TueFind\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\StaticPageController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'feedback' => 'TueFind\Controller\FeedbackController',
            'pdaproxy' => 'TueFind\Controller\PDAProxyController',
            'proxy' => 'TueFind\Controller\ProxyController',
            'search' => 'TueFind\Controller\SearchController',
            'StaticPage' => 'TueFind\Controller\StaticPageController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'TueFind\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
        ],
        'aliases' => [
            'VuFind\RecordDriverPluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\Search\Results\PluginManager' => 'TueFind\Search\Results\PluginManager',
        ],
    ],
];

$config['router']['routes']['static-page'] = [
    'type'    => 'Zend\Router\Http\Segment',
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

return $config;
