<?php
namespace KrimDok\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'acquisition_request' => 'KrimDok\Controller\Factory::getAcquisitionRequestController',
            'browse' => 'KrimDok\Controller\Factory::getBrowseController',
            'help' => 'KrimDok\Controller\Factory::getHelpController',
            'search' => 'KrimDok\Controller\Factory::getSearchController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'newitems' => 'KrimDok\Controller\Plugin\Factory::getNewItems',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [
                'factories' => [
                    'KrimDokILS' => 'KrimDok\ILS\Driver\Factory::getKrimDokILS'
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
    'Help/FAQ',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
