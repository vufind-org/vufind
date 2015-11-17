<?php

/**
 * Finna Module Configuration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 */
namespace Finna\Module\Configuration;

$config = [
    'router' => [
        'routes' => [
            'content-page' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Content/[:page]',
                    'constraints' => [
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]+',
                    ],
                    'defaults' => [
                        'controller' => 'Contentpage',
                        'action'     => 'Content',
                    ]
                ],
            ],
            'list-page' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/List[/:lid]',
                    'constraints' => [
                        'lid'     => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'Listpage',
                        'action'     => 'List',
                    ]
                ],
            ],
            'myresearch-unsubscribe' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Unsubscribe',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Unsubscribe',
                    ]
                ],
            ],
            'record-feedback' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Record/[:id]/Feedback',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Record',
                        'action'     => 'Feedback',
                    ]
                ]
            ]
        ]
    ],
    'controllers' => [
        'factories' => [
            'browse' => 'Finna\Controller\Factory::getBrowseController',
            'record' => 'Finna\Controller\Factory::getRecordController',
        ],
        'invokables' => [
            'ajax' => 'Finna\Controller\AjaxController',
            'searchapi' => 'Finna\Controller\SearchApiController',
            'combined' => 'Finna\Controller\CombinedController',
            'comments' => 'Finna\Controller\CommentsController',
            'contentpage' => 'Finna\Controller\ContentController',
            'cover' => 'Finna\Controller\CoverController',
            'feedback' => 'Finna\Controller\FeedbackController',
            'librarycards' => 'Finna\Controller\LibraryCardsController',
            'metalib' => 'Finna\Controller\MetaLibController',
            'metalibrecord' => 'Finna\Controller\MetaLibrecordController',
            'my-research' => 'Finna\Controller\MyResearchController',
            'pci' => 'Finna\Controller\PCIController',
            'primo' => 'Finna\Controller\PrimoController',
            'primorecord' => 'Finna\Controller\PrimorecordController',
            'search' => 'Finna\Controller\SearchController',
            'listpage' => 'Finna\Controller\ListController',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'Finna\OnlinePayment' => 'Finna\Service\Factory::getOnlinePayment',
            'VuFind\CacheManager' => 'Finna\Service\Factory::getCacheManager',
            'VuFind\CookieManager' => 'Finna\Service\Factory::getCookieManager',
            'VuFind\ILSConnection' => 'Finna\Service\Factory::getILSConnection',
            'VuFind\DbTablePluginManager' => 'Finna\Service\Factory::getDbTablePluginManager',
            'VuFind\AuthManager' => 'Finna\Auth\Factory::getManager',
            'VuFind\SearchResultsPluginManager' => 'Finna\Service\Factory::getSearchResultsPluginManager',
        ]
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => [
        'plugin_managers' => [
            'auth' => [
                'factories' => [
                    'ils' => 'Finna\Auth\Factory::getILS',
                    'multiils' => 'Finna\Auth\Factory::getMultiILS',
                ],
                'invokables' => [
                    'mozillapersona' => 'Finna\Auth\MozillaPersona',
                    'shibboleth' => 'Finna\Auth\Shibboleth',
                ],
            ],
            'db_table' => [
                'factories' => [
                    'resource' => 'Finna\Db\Table\Factory::getResource',
                    'user' => 'Finna\Db\Table\Factory::getUser',
                ],
                'invokables' => [
                    'comments' => 'Finna\Db\Table\Comments',
                    'comments-inappropriate' => 'Finna\Db\Table\CommentsInappropriate',
                    'comments-record' => 'Finna\Db\Table\CommentsRecord',
                    'metalibSearch' => 'Finna\Db\Table\MetaLibSearch',
                    'search' => 'Finna\Db\Table\Search',
                    'transaction' => 'Finna\Db\Table\Transaction',
                    'transaction-fee' => 'Finna\Db\Table\TransactionFee',
                    'transaction-fees' => 'Finna\Db\Table\TransactionFees'
                ],
            ],
            'ils_driver' => [
                'factories' => [
                    'demo' => 'Finna\ILS\Driver\Factory::getDemo',
                    'multibackend' => 'Finna\ILS\Driver\Factory::getMultiBackend',
                    'voyager' => 'Finna\ILS\Driver\Factory::getVoyager',
                    'voyagerrestful' => 'Finna\ILS\Driver\Factory::getVoyagerRestful',
                ],
            ],
            'recommend' => [
                'factories' => [
                    'collectionsidefacets' => 'Finna\Recommend\Factory::getCollectionSideFacets',
                    'sidefacets' => 'Finna\Recommend\Factory::getSideFacets',
                ],
            ],
            'resolver_driver' => [
                'factories' => [
                    'sfx' => 'Finna\Resolver\Driver\Factory::getSfx',
                ],
            ],
            'search_backend' => [
                'factories' => [
                    'MetaLib' => 'Finna\Search\Factory\MetaLibBackendFactory',
                    'Primo' => 'Finna\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                ],
                'aliases' => [
                    // Allow Solr core names to be used as aliases for services:
                    'biblio' => 'Solr',
                ]
            ],
            'search_options' => [
                'abstract_factories' => ['Finna\Search\Options\PluginFactory'],
            ],
            'search_params' => [
                'abstract_factories' => ['Finna\Search\Params\PluginFactory'],
            ],
            'search_results' => [
                'abstract_factories' => ['Finna\Search\Results\PluginFactory'],
                'factories' => [
                    'combined' => 'Finna\Search\Results\Factory::getCombined',
                    'metalib' => 'Finna\Search\Results\Factory::getMetaLib',
                    'solr' => 'Finna\Search\Results\Factory::getSolr',
                    'primo' => 'Finna\Search\Results\Factory::getPrimo',
                ]
            ],
            'content_covers' => [
                'invokables' => [
                    'natlibfi' => 'Finna\Content\Covers\NatLibFi'
                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'metalib' => 'Finna\RecordDriver\Factory::getMetaLib',
                    'solrdefault' => 'Finna\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'Finna\RecordDriver\Factory::getSolrMarc',
                    'solread' => 'Finna\RecordDriver\Factory::getSolrEad',
                    'solrlido' => 'Finna\RecordDriver\Factory::getSolrLido',
                    'solrqdc' => 'Finna\RecordDriver\Factory::getSolrQdc',
                    'primo' => 'Finna\RecordDriver\Factory::getPrimo'
                ],
            ],
            'recordtab' => [
                'factories' => [
                    'map' => 'Finna\RecordTab\Factory::getMap',
                    'usercomments' => 'Finna\RecordTab\Factory::getUserComments',
                ],
                'invokables' => [
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                ],
            ],
            'related' => [
                'factories' => [
                    'similardeferred' => 'Finna\Related\Factory::getSimilarDeferred',
                ],
            ],
        ],
        'recorddriver_tabs' => [
            'Finna\RecordDriver\MetaLib' => [
                'tabs' => [
                    'Details' => 'StaffViewArray'
                ],
                'defaultTab' => null,
            ],

            'Finna\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS',
                    'ComponentParts' => 'ComponentParts',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
            'Finna\RecordDriver\SolrEad' => [
                'tabs' => [
                    'HierarchyTree' => 'HierarchyTree',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'Finna\RecordDriver\SolrLido' => [
                'tabs' => [
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'Finna\RecordDriver\SolrQdc' => [
                'tabs' => [
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'Finna\RecordDriver\Primo' => [
                'tabs' => [
                    'UserComments' => 'UserComments',
                    'Details' => 'StaffViewArray'
                ],
                'defaultTab' => null,
            ],
        ],
    ],

    // Authorization configuration:
    'zfc_rbac' => [
        'vufind_permission_provider_manager' => [
            'factories' => [
                'authenticationStrategy' => 'Finna\Role\PermissionProvider\Factory::getAuthenticationStrategy',
                'ipRange' => 'Finna\Role\PermissionProvider\Factory::getIpRange'
            ],
        ],
    ],

];

$recordRoutes = [
   'metalibrecord' => 'MetaLibRecord'
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
    'Comments' => ['inappropriate' => 'inappropriate/[:id]'],
    'LibraryCards' => ['newLibraryCardPassword' => 'newPassword/[:id]']
];

$staticRoutes = [
    'Browse/Database', 'Browse/Journal',
    'MetaLib/Home', 'MetaLib/Search', 'MetaLib/Advanced',
    'PCI/Home', 'PCI/Search', 'PCI/Record'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// API routes
$config['router']['routes']['searchApi'] = [
    'type' => 'Zend\Mvc\Router\Http\Literal',
    'options' => [
        'route'    => '/api/search',
        'defaults' => [
            'controller' => 'SearchApi',
            'action'     => 'search',
        ]
    ]
];
$config['router']['routes']['searchApiRecord'] = [
    'type' => 'Zend\Mvc\Router\Http\Literal',
    'options' => [
        'route'    => '/api/record',
        'defaults' => [
            'controller' => 'SearchApi',
            'action'     => 'record',
        ]
    ]
];

return $config;
