<?php
namespace TueFind\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'feedback' => 'TueFind\Controller\Factory::getFeedbackController',
            'pdaproxy' => 'TueFind\Controller\Factory::getPDAProxyController',
            'proxy' => 'TueFind\Controller\Factory::getProxyController',
            'search' => 'TueFind\Controller\Factory::getSearchController',
            'StaticPage' => 'TueFind\Controller\Factory::getStaticPageController',
        ],
    ],
    'router' => [
        'routes' => [
            'proxy-load' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/Proxy/Load',
                    'defaults' => [
                        'controller' => 'Proxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
            'pdaproxy-load' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
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
    'vufind' => [
        'plugin_managers' => [
            'recorddriver' => [
                'factories' => [
                    'solrauth' => 'TueFind\RecordDriver\Factory::getSolrAuth',
                    'solrdefault' => 'TueFind\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'TueFind\RecordDriver\Factory::getSolrMarc',
                ],
            ],
            'search_results' => [
                'factories' => [
                    'solr' => 'TueFind\Search\Results\Factory::getSolr',
                ],
            ],
        ],
    ],
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

return $config;
