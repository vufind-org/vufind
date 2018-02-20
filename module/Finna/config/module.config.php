<?php

/**
 * Finna Module Configuration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014-2016.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 */
namespace Finna\Module\Configuration;

$config = [
    'router' => [
        'routes' => [
            'cache-file' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/cache/[:file]',
                    'constraints' => [
                        'file'     => '[.a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Cache',
                        'action'     => 'File',
                    ]
                ],
            ],
            'comments-inappropriate' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Comments/Inappropriate/[:id]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Comments',
                        'action'     => 'Inappropriate',
                    ]
                ]
            ],
            'feed-content-page' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent[/:page][/:element]',
                    'constraints' => [
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]*'
                    ],
                    'defaults' => [
                        'controller' => 'Feedcontentpage',
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
            'myresearch-changemessagingsettings' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeMessagingSettings',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeMessagingSettings',
                    ]
                ],
            ],
            'myresearch-changeprofileaddress' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeProfileAddress',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeProfileAddress',
                    ]
                ],
            ],
            'myresearch-deleteaccount' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/DeleteAccount',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'DeleteAccount',
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
            'myresearch-export' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Export',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Export',
                    ]
                ],
            ],
            'myresearch-import' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Import',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Import',
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
            'ajax' => 'Finna\Controller\Factory::getAjaxController',
            'barcode' => 'Finna\Controller\Factory::getBarcodeController',
            'browse' => 'Finna\Controller\Factory::getBrowseController',
            'cache' => 'Finna\Controller\Factory::getCacheController',
            'cart' => 'Finna\Controller\Factory::getCartController',
            'combined' => 'Finna\Controller\Factory::getCombinedController',
            'comments' => 'Finna\Controller\Factory::getCommentsController',
            'content' => 'Finna\Controller\Factory::getContentController',
            'cover' => 'Finna\Controller\Factory::getCoverController',
            'eds' => 'Finna\Controller\Factory::getEdsController',
            'error' => 'Finna\Controller\Factory::getErrorController',
            'externalauth' => 'Finna\Controller\Factory::getExternalAuthController',
            'feedback' => 'Finna\Controller\Factory::getFeedbackController',
            'feedcontentpage' => 'Finna\Controller\Factory::getFeedContentController',
            'librarycards' => 'Finna\Controller\Factory::getLibraryCardsController',
            'locationService' => 'Finna\Controller\Factory::getLocationServiceController',
            'metalib' => 'Finna\Controller\Factory::getMetaLibController',
            'metalibrecord' => 'Finna\Controller\Factory::getMetaLibrecordController',
            'my-research' => 'Finna\Controller\Factory::getMyResearchController',
            'organisationInfo' => 'Finna\Controller\Factory::getOrganisationInfoController',
            'pci' => 'Finna\Controller\Factory::getPCIController',
            'primo' => 'Finna\Controller\Factory::getPrimoController',
            'primorecord' => 'Finna\Controller\Factory::getPrimorecordController',
            'record' => 'Finna\Controller\Factory::getRecordController',
            'collection' => 'Finna\Controller\Factory::getCollectionController',
            'search' => 'Finna\Controller\Factory::getSearchController',
            'listpage' => 'Finna\Controller\Factory::getListController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'recaptcha' => 'Finna\Controller\Plugin\Factory::getRecaptcha',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'Finna\Feed' => 'Finna\Service\Factory::getFeed',
            'Finna\LocationService' => 'Finna\Service\Factory::getLocationService',
            'Finna\OnlinePayment' => 'Finna\Service\Factory::getOnlinePayment',
            'Finna\OrganisationInfo' => 'Finna\Service\Factory::getOrganisationInfo',
            'Finna\Search\Memory' => 'Finna\Service\Factory::getSearchMemory',
            'VuFind\AutocompletePluginManager' => 'Finna\Service\Factory::getAutocompletePluginManager',
            'VuFind\CacheManager' => 'Finna\Service\Factory::getCacheManager',
            'VuFind\CookieManager' => 'Finna\Service\Factory::getCookieManager',
            'VuFind\ILSAuthenticator' => 'Finna\Auth\Factory::getILSAuthenticator',
            'VuFind\ILSConnection' => 'Finna\Service\Factory::getILSConnection',
            'VuFind\ILSHoldLogic' => 'Finna\Service\Factory::getILSHoldLogic',
            'VuFind\AuthManager' => 'Finna\Auth\Factory::getManager',
            'VuFind\RecordLoader' => 'Finna\Service\Factory::getRecordLoader',
            'VuFind\Role\PermissionManager' => 'Finna\Service\Factory::getPermissionManager',
            'VuFind\SearchSpecsReader' => 'Finna\Service\Factory::getSearchSpecsReader',
            'VuFind\SearchTabsHelper' => 'Finna\Service\Factory::getSearchTabsHelper',
            'VuFind\YamlReader' => 'Finna\Service\Factory::getYamlReader',
            'VuFind\Mailer' => 'Finna\Mailer\Factory',
        ],
        'invokables' => [
            'VuFind\HierarchicalFacetHelper' => 'Finna\Search\Solr\HierarchicalFacetHelper',
        ]
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => [
        // The config reader is a special service manager for loading .ini files:
        'config_reader' => [
            'abstract_factories' => ['Finna\Config\PluginFactory'],
        ],
        'plugin_managers' => [
            'auth' => [
                'factories' => [
                    'ils' => 'Finna\Auth\Factory::getILS',
                    'multiils' => 'Finna\Auth\Factory::getMultiILS',
                    'shibboleth' => 'Finna\Auth\Factory::getShibboleth'
                ],
            ],
            'autocomplete' => [
                'factories' => [
                    'solr' => 'Finna\Autocomplete\Factory::getSolr'
                ]
            ],
            'db_row' => [
                'factories' => [
                    'commentsinappropriate' => 'Finna\Db\Row\Factory::getCommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Row\Factory::getCommentsRecord',
                    'duedatereminder' => 'Finna\Db\Row\Factory::getDueDateReminder',
                    'fee' => 'Finna\Db\Row\Factory::getFee',
                    'finnacache' => 'Finna\Db\Row\Factory::getFinnaCache',
                    'privateuser' => 'Finna\Db\Row\Factory::getPrivateUser',
                    'resource' => 'Finna\Db\Row\Factory::getResource',
                    'search' => 'Finna\Db\Row\Factory::getSearch',
                    'transaction' => 'Finna\Db\Row\Factory::getTransaction',
                    'user' => 'Finna\Db\Row\Factory::getUser',
                    'userlist' => 'Finna\Db\Row\Factory::getUserList',
                ],
            ],
            'db_table' => [
                'factories' => [
                    'comments' => 'Finna\Db\Table\Factory::getComments',
                    'comments-inappropriate' => 'Finna\Db\Table\Factory::getCommentsInappropriate',
                    'comments-record' => 'Finna\Db\Table\Factory::getCommentsRecord',
                    'due-date-reminder' => 'Finna\Db\Table\Factory::getDueDateReminder',
                    'fee' => 'Finna\Db\Table\Factory::getFee',
                    'finnacache' => 'Finna\Db\Table\Factory::getFinnaCache',
                    'resource' => 'Finna\Db\Table\Factory::getResource',
                    'search' => 'Finna\Db\Table\Factory::getSearch',
                    'session' => 'Finna\Db\Table\Factory::getSession',
                    'transaction' => 'Finna\Db\Table\Factory::getTransaction',
                    'user' => 'Finna\Db\Table\Factory::getUser',
                    'userlist' => 'Finna\Db\Table\Factory::getUserList',
                    'userresource' => 'Finna\Db\Table\Factory::getUserResource',
                ],
            ],
            'ils_driver' => [
                'factories' => [
                    'axiellwebservices' => 'Finna\ILS\Driver\Factory::getAxiellWebServices',
                    'demo' => 'Finna\ILS\Driver\Factory::getDemo',
                    'gemini' => 'Finna\ILS\Driver\Factory::getGemini',
                    'koharest' => 'Finna\ILS\Driver\Factory::getKohaRest',
                    'mikromarc' => 'Finna\ILS\Driver\Factory::getMikromarc',
                    'multibackend' => 'Finna\ILS\Driver\Factory::getMultiBackend',
                    'sierrarest' => 'Finna\ILS\Driver\Factory::getSierraRest',
                    'voyager' => 'Finna\ILS\Driver\Factory::getVoyager',
                    'voyagerrestful' => 'Finna\ILS\Driver\Factory::getVoyagerRestful'
                ],
            ],
            'recommend' => [
                'factories' => [
                    'collectionsidefacets' => 'Finna\Recommend\Factory::getCollectionSideFacets',
                    'sidefacets' => 'Finna\Recommend\Factory::getSideFacets',
                    'sidefacetsdeferred' => 'Finna\Recommend\Factory::getSideFacetsDeferred',
                ],
            ],
            'resolver_driver' => [
                'factories' => [
                    'sfx' => 'Finna\Resolver\Driver\Factory::getSfx',
                ],
            ],
            'search_backend' => [
                'factories' => [
                    'EDS' => 'Finna\Search\Factory\EdsBackendFactory',
                    'Primo' => 'Finna\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                    'Summon' => 'Finna\Search\Factory\SummonBackendFactory',
                ],
                'aliases' => [
                    // Allow Solr core names to be used as aliases for services:
                    'biblio' => 'Solr',
                ]
            ],
            'search_options' => [
                'abstract_factories' => ['Finna\Search\Options\PluginFactory'],
                'factories' => [
                    'eds' => 'Finna\Search\Options\Factory::getEDS',
                ],
            ],
            'search_params' => [
                'abstract_factories' => ['Finna\Search\Params\PluginFactory'],
                'factories' => [
                    'solr' => 'Finna\Search\Params\Factory::getSolr',
                    'combined' => 'Finna\Search\Params\Factory::getCombined',
                ],
            ],
            'search_results' => [
                'abstract_factories' => ['Finna\Search\Results\PluginFactory'],
                'factories' => [
                    'combined' => 'Finna\Search\Results\Factory::getCombined',
                    'favorites' => 'Finna\Search\Results\Factory::getFavorites',
                    'solr' => 'Finna\Search\Results\Factory::getSolr',
                    'primo' => 'Finna\Search\Results\Factory::getPrimo',
                ]
            ],
            'content_covers' => [
                'invokables' => [
                    'bookyfi' => 'Finna\Content\Covers\BookyFi',
                    'natlibfi' => 'Finna\Content\Covers\NatLibFi'
                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'eds' => 'Finna\RecordDriver\Factory::getEDS',
                    'solrdefault' => 'Finna\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'Finna\RecordDriver\Factory::getSolrMarc',
                    'solread' => 'Finna\RecordDriver\Factory::getSolrEad',
                    'solrforward' => 'Finna\RecordDriver\Factory::getSolrForward',
                    'solrlido' => 'Finna\RecordDriver\Factory::getSolrLido',
                    'solrqdc' => 'Finna\RecordDriver\Factory::getSolrQdc',
                    'primo' => 'Finna\RecordDriver\Factory::getPrimo'
                ],
            ],
            'recordtab' => [
                'factories' => [
                    'map' => 'Finna\RecordTab\Factory::getMap',
                    'usercomments' => 'Finna\RecordTab\Factory::getUserComments',
                    'pressreview' => 'Finna\RecordTab\Factory::getPressReviews',
                    'music' => 'Finna\RecordTab\Factory::getMusic',
                    'distribution' => 'Finna\RecordTab\Factory::getDistribution',
                    'inspectionDetails' =>
                        'Finna\RecordTab\Factory::getInspectionDetails',
                    'descriptionFWD' => 'Finna\RecordTab\Factory::getDescriptionFWD',
                    'itemdescription' =>
                        'Finna\RecordTab\Factory::getItemDescription',
                ],
                'invokables' => [
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                ],
            ],
            'related' => [
                'factories' => [
                    'nothing' => 'Finna\Related\Factory::getNothing',
                    'similardeferred' => 'Finna\Related\Factory::getSimilarDeferred',
                ],
            ],
        ],
        'recorddriver_collection_tabs' => [
            'Finna\RecordDriver\SolrEad' => [
                'tabs' => [
                    'CollectionList' => 'CollectionList',
                    'HierarchyTree' => 'CollectionHierarchyTree',
                    'UserComments' => 'UserComments',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
        ],
        'recorddriver_tabs' => [
            'Finna\RecordDriver\EDS' => [
                'tabs' => [
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'Finna\RecordDriver\SolrDefault' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS',
                    'ComponentParts' => 'ComponentParts',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewArray',
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
            'Finna\RecordDriver\SolrForward' => [
                'tabs' => [
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'PressReview' => 'PressReview',
                    'Music' => 'Music',
                    'Distribution' => 'Distribution',
                    'InspectionDetails' => 'InspectionDetails',
                    'DescriptionFWD' => 'DescriptionFWD',
                    'ItemDescription' => 'ItemDescription',
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
    'LibraryCards' => ['newLibraryCardPassword' => 'newPassword/[:id]'],
    'MyResearch' => ['sortList' => 'SortList/[:id]']
];

$staticRoutes = [
    'Browse/Database', 'Browse/Journal',
    'LibraryCards/Recover', 'LibraryCards/ResetPassword',
    'LocationService/Modal',
    'MetaLib/Home', 'MetaLib/Search', 'MetaLib/Advanced',
    'MyResearch/SaveCustomOrder', 'MyResearch/PurgeHistoricLoans',
    'OrganisationInfo/Home',
    'PCI/Home', 'PCI/Search', 'PCI/Record',
    'Search/StreetSearch',
    'Barcode/Show', 'Search/MapFacet'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
