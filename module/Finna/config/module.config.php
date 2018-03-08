<?php
/**
 * Finna Module Configuration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014-2018.
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
                'type'    => 'Zend\Router\Http\Segment',
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
                'type'    => 'Zend\Router\Http\Segment',
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
                'type'    => 'Zend\Router\Http\Segment',
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
                'type'    => 'Zend\Router\Http\Segment',
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
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeMessagingSettings',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeMessagingSettings',
                    ]
                ],
            ],
            'myresearch-changeprofileaddress' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeProfileAddress',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeProfileAddress',
                    ]
                ],
            ],
            'myresearch-deleteaccount' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/DeleteAccount',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'DeleteAccount',
                    ]
                ],
            ],
            'myresearch-unsubscribe' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Unsubscribe',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Unsubscribe',
                    ]
                ],
            ],
            'myresearch-export' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Export',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Export',
                    ]
                ],
            ],
            'myresearch-import' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Import',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Import',
                    ]
                ],
            ],
            'record-feedback' => [
                'type'    => 'Zend\Router\Http\Segment',
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
            'Finna\Controller\AjaxController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\BarcodeController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\BrowseController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CacheController' => 'Finna\Controller\CacheControllerFactory',
            'Finna\Controller\CartController' => 'VuFind\Controller\CartControllerFactory',
            'Finna\Controller\CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CombinedController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CommentsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CoverController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\EdsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ErrorController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ExternalAuthController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedContentPageController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\LibraryCardsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\LocationServiceController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetaLibController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetalibRecordController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\OrganisationInfoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PCIController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PrimoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PrimoRecordController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ListController' => 'Finna\Controller\ListControllerFactory',
        ],
        'aliases' => [
            'AJAX' => 'Finna\Controller\AjaxController',
            'ajax' => 'Finna\Controller\AjaxController',
            'barcode' => 'Finna\Controller\BarcodeController',
            'Browse' => 'Finna\Controller\BrowseController',
            'browse' => 'Finna\Controller\BrowseController',
            'Cache' => 'Finna\Controller\CacheController',
            'cache' => 'Finna\Controller\CacheController',
            'Cart' => 'Finna\Controller\CartController',
            'cart' => 'Finna\Controller\CartController',
            'Collection' => 'Finna\Controller\CollectionController',
            'collection' => 'Finna\Controller\CollectionController',
            'Combined' => 'Finna\Controller\CombinedController',
            'combined' => 'Finna\Controller\CombinedController',
            'Comments' => 'Finna\Controller\CommentsController',
            'comments' => 'Finna\Controller\CommentsController',
            'Content' => 'Finna\Controller\ContentController',
            'content' => 'Finna\Controller\ContentController',
            'Cover' => 'Finna\Controller\CoverController',
            'cover' => 'Finna\Controller\CoverController',
            'EDS' => 'Finna\Controller\EdsController',
            'eds' => 'Finna\Controller\EdsController',
            'Error' => 'Finna\Controller\ErrorController',
            'error' => 'Finna\Controller\ErrorController',
            'ExternalAuth' => 'Finna\Controller\ExternalAuthController',
            'externalauth' => 'Finna\Controller\ExternalAuthController',
            'Feedback' => 'Finna\Controller\FeedbackController',
            'feedback' => 'Finna\Controller\FeedbackController',
            'FeedContentPage' => 'Finna\Controller\FeedContentController',
            'feedcontentpage' => 'Finna\Controller\FeedContentController',
            'LibraryCards' => 'Finna\Controller\LibraryCardsController',
            'librarycards' => 'Finna\Controller\LibraryCardsController',
            'LocationService' => 'Finna\Controller\LocationServiceController',
            'locationservice' => 'Finna\Controller\LocationServiceController',
            'MetaLib' => 'Finna\Controller\MetaLibController',
            'metalib' => 'Finna\Controller\MetaLibController',
            'MetaLibRecord' => 'Finna\Controller\MetaLibrecordController',
            'metalibrecord' => 'Finna\Controller\MetaLibrecordController',
            'MyResearch' => 'Finna\Controller\MyResearchController',
            'myresearch' => 'Finna\Controller\MyResearchController',
            'OrganisationInfo' => 'Finna\Controller\OrganisationInfoController',
            'organisationinfo' => 'Finna\Controller\OrganisationInfoController',
            'PCI' => 'Finna\Controller\PCIController',
            'pci' => 'Finna\Controller\PCIController',
            'Primo' => 'Finna\Controller\PrimoController',
            'primo' => 'Finna\Controller\PrimoController',
            'PrimoRecord' => 'Finna\Controller\PrimorecordController',
            'primorecord' => 'Finna\Controller\PrimorecordController',
            'Record' => 'Finna\Controller\RecordController',
            'record' => 'Finna\Controller\RecordController',
            'Search' => 'Finna\Controller\SearchController',
            'search' => 'Finna\Controller\SearchController',
            'ListPage' => 'Finna\Controller\ListController',
            'listpage' => 'Finna\Controller\ListController',
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'recaptcha' => 'Finna\Controller\Plugin\Factory::getRecaptcha',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'Finna\Auth\ILSAuthenticator' => 'VuFind\Auth\ILSAuthenticatorFactory',
            'Finna\Auth\Manager' => 'VuFind\Auth\ManagerFactory',
            'Finna\Autocomplete\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Cache\Manager' => 'VuFind\Cache\ManagerFactory',
            'Finna\Config\PluginManager' => 'VuFind\Config\PluginManagerFactory',
            'Finna\Config\SearchSpecsReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Config\YamlReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Feed' => 'Finna\Service\Factory::getFeed',
            'Finna\ILS\Connection' => 'VuFind\ILS\ConnectionFactory',
            'Finna\LocationService' => 'Finna\Service\Factory::getLocationService',
            'Finna\Mailer\Mailer' => 'VuFind\Mailer\Factory',
            'Finna\OnlinePayment' => 'Finna\Service\Factory::getOnlinePayment',
            'Finna\OrganisationInfo' => 'Finna\Service\Factory::getOrganisationInfo',
            'Finna\Record\Loader' => 'VuFind\Record\LoaderFactory',
            'Finna\Role\PermissionManager' => 'VuFind\Role\PermissionManagerFactory',
            'Finna\Search\Memory' => 'VuFind\Search\MemoryFactory',
            'Finna\Search\Solr\HierarchicalFacetHelper' => 'Zend\ServiceManager\Factory\InvokableFactory',

            'VuFind\Cookie\CookieManager' => 'Finna\Cookie\CookieManagerFactory',
            'VuFind\Search\SearchTabsHelper' => 'Finna\Search\SearchTabsHelperFactory',
        ],
        'aliases' => [
            'VuFind\Auth\Manager' => 'Finna\Auth\Manager',
            'VuFind\Auth\ILSAuthenticator' => 'Finna\Auth\ILSAuthenticator',
            'VuFind\Autocomplete\PluginManager' => 'Finna\Autocomplete\PluginManager',
            'VuFind\Cache\Manager' => 'Finna\Cache\Manager',
            'VuFind\Config\PluginManager' => 'Finna\Config\PluginManager',
            'VuFind\Config\SearchSpecsReader' => 'Finna\Config\SearchSpecsReader',
            'VuFind\Config\YamlReader' => 'Finna\Config\YamlReader',
            'VuFind\ILS\Connection' => 'Finna\ILS\Connection',
            'VuFind\Mailer\Mailer' => 'Finna\Mailer\Mailer',
            'VuFind\Record\Loader' => 'Finna\Record\Loader',
            'VuFind\Role\PermissionManager' => 'Finna\Role\PermissionManager',
            'VuFind\Search\Memory' => 'Finna\Search\Memory',
            'VuFind\Search\Solr\HierarchicalFacetHelper' => 'Finna\Search\Solr\HierarchicalFacetHelper',
        ],
        'invokables' => [
            'VuFind\HierarchicalFacetHelper' => 'Finna\Search\Solr\HierarchicalFacetHelper',
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
                    'Finna\Db\Row\CommentsInappropriate' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\CommentsRecord' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\DueDateReminder' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Fee' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaCache' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\PrivateUser' => 'VuFind\Db\Row\UserFactory',
                    'Finna\Db\Row\Resource' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Search' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Transaction' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\User' => 'VuFind\Db\Row\UserFactory',
                    'Finna\Db\Row\UserList' => 'VuFind\Db\Row\RowGatewayFactory',
                ],
                'aliases' => [
                    'VuFind\Db\Row\PrivateUser' => 'Finna\Db\Row\PrivateUser',
                    'VuFind\Db\Row\Resource' => 'Finna\Db\Row\Resource',
                    'VuFind\Db\Row\Search' => 'Finna\Db\Row\Search',
                    'VuFind\Db\Row\Transaction' => 'Finna\Db\Row\Transaction',
                    'VuFind\Db\Row\User' => 'Finna\Db\Row\User',
                    'VuFind\Db\Row\UserList' => 'Finna\Db\Row\UserList',

                    'commentsinappropriate' => 'Finna\Db\Row\CommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Row\CommentsRecord',
                    'duedatereminder' => 'Finna\Db\Row\DueDateReminder',
                    'fee' => 'Finna\Db\Row\Fee',
                    'finnacache' => 'Finna\Db\Row\FinnaCache',
                ]
            ],
            'db_table' => [
                'factories' => [
                    'Finna\Db\Table\Comments' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\CommentsInappropriate' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\CommentsRecord' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\DueDateReminder' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Fee' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaCache' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Resource' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Search' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Session' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Transaction' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\User' => 'VuFind\Db\Table\UserFactory',
                    'Finna\Db\Table\UserList' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\UserResource' => 'VuFind\Db\Table\GatewayFactory',
                ],
                'aliases' => [
                    'comments-inappropriate' => 'Finna\Db\Table\Factory\CommentsInappropriate',
                    'comments-record' => 'Finna\Db\Table\Factory\CommentsRecord',
                    'due-date-reminder' => 'Finna\Db\Table\Factory\DueDateReminder',
                    'fee' => 'Finna\Db\Table\Factory\Fee',
                    'finnacache' => 'Finna\Db\Table\FinnaCache',
                ]
            ],
            'ils_driver' => [
                'factories' => [
                    'Finna\ILS\Driver\AxiellWebServices' => 'VuFind\ILS\Driver\PluginManager',
                    'Finna\ILS\Driver\Demo' => 'VuFind\ILS\Driver\PluginManager',
                    'Finna\ILS\Driver\Gemini' => 'VuFind\ILS\Driver\PluginManager',
                    'Finna\ILS\Driver\KohaRest' => 'Finna\ILS\Driver\KohaRestFactory',
                    'Finna\ILS\Driver\Mikromarc' => 'VuFind\ILS\Driver\PluginManager',
                    'Finna\ILS\Driver\MultiBackend' => 'VuFind\ILS\Driver\MultiBackendFactory',
                    'Finna\ILS\Driver\SierraRest' => 'VuFind\ILS\Driver\SierraRestFactory',
                    'Finna\ILS\Driver\Voyager' => 'VuFind\ILS\Driver\PluginManager',
                    'Finna\ILS\Driver\VoyagerRestful' => 'VuFind\ILS\Driver\PluginManager',
                ],
                'aliases' => [
                    'axiellwebservices' => 'Finna\ILS\Driver\AxiellWebServices',
                    'gemini' => 'Finna\ILS\Driver\Gemini',
                    'mikromark' => 'Finna\ILS\Driver\Mikromarc',
                    // TOOD: remove the following line when KohaRest driver is available upstream:
                    'koharest' => 'Finna\ILS\Driver\KohaRest',

                    'VuFind\ILS\Driver\Demo' => 'Finna\ILS\Driver\Demo',
                    'VuFind\ILS\Driver\KohaRest' => 'Finna\ILS\Driver\KohaRest',
                    'VuFind\ILS\Driver\MultiBackend' => 'Finna\ILS\Driver\MultiBackend',
                    'VuFind\ILS\Driver\SierraRest' => 'Finna\ILS\Driver\SierraRest',
                    'VuFind\ILS\Driver\VoyagerRestful' => 'Finna\ILS\Driver\Voyager',
                ]
            ],
            'recommend' => [
                'factories' => [
                    'VuFind\Recommend\CollectionSideFacets' => 'Finna\Recommend\Factory::getCollectionSideFacets',
                    'VuFind\Recommend\SideFacets' => 'Finna\Recommend\Factory::getSideFacets',
                    'Finna\Recommend\SideFacetsDeferred' => 'Finna\Recommend\Factory::getSideFacetsDeferred',
                ],
                'aliases' => [
                    'sidefacetsdeferred' => 'Finna\Recommend\SideFacetsDeferred',
                ]
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
                    'Finna\Search\Solr\Params' => 'Finna\Search\Params\Factory::getSolr',
                    'Finna\Search\Combined\Params' => 'Finna\Search\Params\Factory::getCombined',
                ],
                'aliases' => [
                    'VuFind\Search\Combined\Params' => 'Finna\Search\Combined\Params',
                    'VuFind\Search\Solr\Params' => 'Finna\Search\Solr\Params',
                ]
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
                    'Finna\RecordDriver\EDS' => 'Finna\RecordDriver\Factory::getEDS',
                    'Finna\RecordDriver\SolrDefault' => 'Finna\RecordDriver\Factory::getSolrDefault',
                    'Finna\RecordDriver\SolrMarc' => 'Finna\RecordDriver\Factory::getSolrMarc',
                    'Finna\RecordDriver\SolrEad' => 'Finna\RecordDriver\Factory::getSolrEad',
                    'Finna\RecordDriver\SolrForward' => 'Finna\RecordDriver\Factory::getSolrForward',
                    'Finna\RecordDriver\SolrLido' => 'Finna\RecordDriver\Factory::getSolrLido',
                    'Finna\RecordDriver\SolrQdc' => 'Finna\RecordDriver\Factory::getSolrQdc',
                    'Finna\RecordDriver\Primo' => 'Finna\RecordDriver\Factory::getPrimo',
                ],
                'aliases' => [
                    'SolrEad' => 'Finna\RecordDriver\SolrEad',
                    'SolrForward' => 'Finna\RecordDriver\SolrForward',
                    'SolrLido' => 'Finna\RecordDriver\SolrLido',
                    'SolrQdc' => 'Finna\RecordDriver\SolrQdc',

                    'VuFind\RecordDriver\EDS' => 'Finna\RecordDriver\EDS',
                    'VuFind\RecordDriver\SolrDefault' => 'Finna\RecordDriver\SolrDefault',
                    'VuFind\RecordDriver\SolrMarc' => 'Finna\RecordDriver\SolrMarc',
                    'VuFind\RecordDriver\Primo' => 'Finna\RecordDriver\Primo',
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
