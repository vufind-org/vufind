<?php
namespace KrimDok\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'acquisition_request' => 'KrimDok\Controller\Factory::getAcquisitionRequestController',
            'browse' => 'KrimDok\Controller\Factory::getBrowseController',
            'fidsystematik' => 'KrimDok\Controller\Factory::getFIDSystematikController',
            'help' => 'KrimDok\Controller\Factory::getHelpController',
            'search' => 'KrimDok\Controller\Factory::getSearchController',
            'static_pages' => 'KrimDok\Controller\Factory::getStaticPagesController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'newitems' => 'KrimDok\Controller\Plugin\Factory::getNewItems',
        ],
    ],
    'router' => [
        'routes' => [
            'static-catalogs' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/static/catalogs',
                    'defaults' => [
                        'controller' => 'static_pages',
                        'action'     => 'catalogs',
                    ],
                ],
            ],
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [
                'factories' => [
                    'KrimDok' => 'KrimDok\ILS\Driver\Factory::getKrimDok'
                ],
            ],
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
    'AcquisitionRequest/Create',
    'AcquisitionRequest/Send',
    'FIDSystematik/Home',
    'Help/FAQ',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
