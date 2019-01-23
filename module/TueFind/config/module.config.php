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
            'TueFind\Controller\AcquisitionRequestController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\AjaxController' => 'VuFind\Controller\AjaxControllerFactory',
            'TueFind\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\PDAProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\ProxyController' => 'VuFind\Controller\AbstractBaseFactory',
            'TueFind\Controller\StaticPageController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'acquisition_request' => 'TueFind\Controller\AcquisitionRequestController',
            'AJAX' => 'TueFind\Controller\AjaxController',
            'ajax' => 'TueFind\Controller\AjaxController',
            'Feedback' => 'TueFind\Controller\FeedbackController',
            'feedback' => 'TueFind\Controller\FeedbackController',
            'pdaproxy' => 'TueFind\Controller\PDAProxyController',
            'proxy' => 'TueFind\Controller\ProxyController',
            'StaticPage' => 'TueFind\Controller\StaticPageController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'TueFind\ContentBlock\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Mailer\Mailer' => 'TueFind\Mailer\Factory',
            'TueFind\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TueFind\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
        ],
        'aliases' => [
            'VuFind\ContentBlock\PluginManager' => 'TueFind\ContentBlock\PluginManager',
            'VuFind\Mailer\Mailer' => 'TueFind\Mailer\Mailer',
            'VuFind\RecordDriverPluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TueFind\RecordDriver\PluginManager',
            'VuFind\Search\Results\PluginManager' => 'TueFind\Search\Results\PluginManager',
        ],
    ],
];

$recordRoutes = [];
$dynamicRoutes = [];
$staticRoutes = [
    'AcquisitionRequest/Create',
    'AcquisitionRequest/Send',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);



return $config;
