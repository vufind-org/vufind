<?php
namespace VuFind\Module\Config;

$config = [
    'router' => [
        'routes' => [
            'default' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/[:controller[/[:action]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'index',
                        'action'     => 'Home',
                    ],
                ],
            ],
            'content-page' => [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/Content/[:page]',
                    'constraints' => [
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Content',
                        'action'     => 'Content',
                    ]
                ],
            ],
            'legacy-alphabrowse-results' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/AlphaBrowse/Results',
                    'defaults' => [
                        'controller' => 'Alphabrowse',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-bookcover' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/bookcover.php',
                    'defaults' => [
                        'controller' => 'cover',
                        'action'     => 'Show',
                    ]
                ]
            ],
            'legacy-summonrecord' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/Summon/Record',
                    'defaults' => [
                        'controller' => 'SummonRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-worldcatrecord' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/WorldCat/Record',
                    'defaults' => [
                        'controller' => 'WorldcatRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'soap-shibboleth-logout-notification-handler' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route' => '/soap/shiblogout',
                    'defaults' => [
                        'controller' => 'ShibbolethLogoutNotification',
                        'action' => 'index'
                    ]
                ],
                'child_routes' => [
                    'get' => [
                        'type' => 'method',
                        'options' => [
                            'verb' => 'get',
                            'defaults' => [
                                'action' => 'get'
                            ],
                        ],
                    ],
                    'post' => [
                        'type' => 'method',
                        'options' => [
                            'verb' => 'post',
                            'defaults' => [
                                'action' => 'post'
                            ]
                        ]
                    ]
                ]
            ]
        ],
    ],
    'controllers' => [
        'factories' => [
            'ajax' => 'VuFind\Controller\Factory::getAjaxController',
            'alphabrowse' => 'VuFind\Controller\Factory::getAlphabrowseController',
            'author' => 'VuFind\Controller\Factory::getAuthorController',
            'authority' => 'VuFind\Controller\Factory::getAuthorityController',
            'browse' => 'VuFind\Controller\Factory::getBrowseController',
            'browzine' => 'VuFind\Controller\Factory::getBrowZineController',
            'cart' => 'VuFind\Controller\Factory::getCartController',
            'channels' => 'VuFind\Controller\Factory::getChannelsController',
            'collection' => 'VuFind\Controller\Factory::getCollectionController',
            'collections' => 'VuFind\Controller\Factory::getCollectionsController',
            'combined' => 'VuFind\Controller\Factory::getCombinedController',
            'confirm' => 'VuFind\Controller\Factory::getConfirmController',
            'content' => 'VuFind\Controller\Factory::getContentController',
            'cover' => 'VuFind\Controller\Factory::getCoverController',
            'eds' => 'VuFind\Controller\Factory::getEdsController',
            'edsrecord' => 'VuFind\Controller\Factory::getEdsrecordController',
            'eit' => 'VuFind\Controller\Factory::getEITController',
            'eitrecord' => '\VuFind\Controller\Factory::getEITrecordController',
            'error' => 'VuFind\Controller\Factory::getErrorController',
            'externalauth' => 'VuFind\Controller\Factory::getExternalAuthController',
            'feedback' => 'VuFind\Controller\Factory::getFeedbackController',
            'help' => 'VuFind\Controller\Factory::getHelpController',
            'hierarchy' => 'VuFind\Controller\Factory::getHierarchyController',
            'index' => 'VuFind\Controller\Factory::getIndexController',
            'install' => 'VuFind\Controller\Factory::getInstallController',
            'libguides' => 'VuFind\Controller\Factory::getLibGuidesController',
            'librarycards' => 'VuFind\Controller\Factory::getLibraryCardsController',
            'missingrecord' => 'VuFind\Controller\Factory::getMissingrecordController',
            'my-research' => 'VuFind\Controller\Factory::getMyResearchController',
            'oai' => 'VuFind\Controller\Factory::getOaiController',
            'pazpar2' => 'VuFind\Controller\Factory::getPazpar2Controller',
            'primo' => 'VuFind\Controller\Factory::getPrimoController',
            'primorecord' => 'VuFind\Controller\Factory::getPrimorecordController',
            'qrcode' => 'VuFind\Controller\Factory::getQRCodeController',
            'record' => 'VuFind\Controller\Factory::getRecordController',
            'records' => 'VuFind\Controller\Factory::getRecordsController',
            'search' => 'VuFind\Controller\Factory::getSearchController',
            'shibbolethlogoutnotification' => 'VuFind\Controller\Factory::getShibbolethLogoutNotificationController',
            'summon' => 'VuFind\Controller\Factory::getSummonController',
            'summonrecord' => 'VuFind\Controller\Factory::getSummonrecordController',
            'tag' => 'VuFind\Controller\Factory::getTagController',
            'upgrade' => 'VuFind\Controller\Factory::getUpgradeController',
            'web' => 'VuFind\Controller\Factory::getWebController',
            'worldcat' => 'VuFind\Controller\Factory::getWorldcatController',
            'worldcatrecord' => 'VuFind\Controller\Factory::getWorldcatrecordController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'favorites' => 'VuFind\Controller\Plugin\Factory::getFavorites',
            'flashmessenger' => 'VuFind\Controller\Plugin\Factory::getFlashMessenger',
            'followup' => 'VuFind\Controller\Plugin\Factory::getFollowup',
            'holds' => 'VuFind\Controller\Plugin\Factory::getHolds',
            'newitems' => 'VuFind\Controller\Plugin\Factory::getNewItems',
            'ILLRequests' => 'VuFind\Controller\Plugin\Factory::getILLRequests',
            'permission' => 'VuFind\Controller\Plugin\Factory::getPermission',
            'recaptcha' => 'VuFind\Controller\Plugin\Factory::getRecaptcha',
            'reserves' => 'VuFind\Controller\Plugin\Factory::getReserves',
            'result-scroller' => 'VuFind\Controller\Plugin\Factory::getResultScroller',
            'storageRetrievalRequests' => 'VuFind\Controller\Plugin\Factory::getStorageRetrievalRequests',
        ],
        'invokables' => [
            'db-upgrade' => 'VuFind\Controller\Plugin\DbUpgrade',
            'renewals' => 'VuFind\Controller\Plugin\Renewals',
        ]
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'VuFind\AccountCapabilities' => 'VuFind\Service\Factory::getAccountCapabilities',
            'VuFind\AuthManager' => 'VuFind\Auth\Factory::getManager',
            'VuFind\AuthPluginManager' => 'VuFind\Service\Factory::getAuthPluginManager',
            'VuFind\AutocompletePluginManager' => 'VuFind\Service\Factory::getAutocompletePluginManager',
            'VuFind\CacheManager' => 'VuFind\Service\Factory::getCacheManager',
            'VuFind\Cart' => 'VuFind\Service\Factory::getCart',
            'VuFind\ChannelProviderPluginManager' => 'VuFind\Service\Factory::getChannelProviderPluginManager',
            'VuFind\Config' => 'VuFind\Service\Factory::getConfig',
            'VuFind\ContentPluginManager' => 'VuFind\Service\Factory::getContentPluginManager',
            'VuFind\ContentAuthorNotesPluginManager' => 'VuFind\Service\Factory::getContentAuthorNotesPluginManager',
            'VuFind\ContentCoversPluginManager' => 'VuFind\Service\Factory::getContentCoversPluginManager',
            'VuFind\ContentExcerptsPluginManager' => 'VuFind\Service\Factory::getContentExcerptsPluginManager',
            'VuFind\ContentReviewsPluginManager' => 'VuFind\Service\Factory::getContentReviewsPluginManager',
            'VuFind\CookieManager' => 'VuFind\Service\Factory::getCookieManager',
            'VuFind\Cover\Router' => 'VuFind\Service\Factory::getCoverRouter',
            'VuFind\DateConverter' => 'VuFind\Service\Factory::getDateConverter',
            'VuFind\DbAdapter' => 'VuFind\Service\Factory::getDbAdapter',
            'VuFind\DbAdapterFactory' => 'VuFind\Service\Factory::getDbAdapterFactory',
            'VuFind\DbRowPluginManager' => 'VuFind\Service\Factory::getDbRowPluginManager',
            'VuFind\DbTablePluginManager' => 'VuFind\Service\Factory::getDbTablePluginManager',
            'VuFind\Export' => 'VuFind\Service\Factory::getExport',
            'VuFind\Favorites\FavoritesService' => 'VuFind\Favorites\FavoritesServiceFactory',
            'VuFind\HierarchyDriverPluginManager' => 'VuFind\Service\Factory::getHierarchyDriverPluginManager',
            'VuFind\HierarchyTreeDataFormatterPluginManager' => 'VuFind\Service\Factory::getHierarchyTreeDataFormatterPluginManager',
            'VuFind\HierarchyTreeDataSourcePluginManager' => 'VuFind\Service\Factory::getHierarchyTreeDataSourcePluginManager',
            'VuFind\HierarchyTreeRendererPluginManager' => 'VuFind\Service\Factory::getHierarchyTreeRendererPluginManager',
            'VuFind\Http' => 'VuFind\Service\Factory::getHttp',
            'VuFind\HMAC' => 'VuFind\Service\Factory::getHMAC',
            'VuFind\ILSAuthenticator' => 'VuFind\Auth\Factory::getILSAuthenticator',
            'VuFind\ILSConnection' => 'VuFind\Service\Factory::getILSConnection',
            'VuFind\ILSDriverPluginManager' => 'VuFind\Service\Factory::getILSDriverPluginManager',
            'VuFind\ILSHoldLogic' => 'VuFind\Service\Factory::getILSHoldLogic',
            'VuFind\ILSHoldSettings' => 'VuFind\Service\Factory::getILSHoldSettings',
            'VuFind\ILSTitleHoldLogic' => 'VuFind\Service\Factory::getILSTitleHoldLogic',
            'VuFind\Logger' => 'VuFind\Log\LoggerFactory',
            'VuFind\Mailer' => 'VuFind\Mailer\Factory',
            'VuFind\ProxyConfig' => 'VuFind\Service\Factory::getProxyConfig',
            'VuFind\Recaptcha' => 'VuFind\Service\Factory::getRecaptcha',
            'VuFind\RecommendPluginManager' => 'VuFind\Service\Factory::getRecommendPluginManager',
            'VuFind\RecordCache' => 'VuFind\Service\Factory::getRecordCache',
            'VuFind\RecordDriverPluginManager' => 'VuFind\Service\Factory::getRecordDriverPluginManager',
            'VuFind\RecordLoader' => 'VuFind\Service\Factory::getRecordLoader',
            'VuFind\RecordRouter' => 'VuFind\Service\Factory::getRecordRouter',
            'VuFind\RecordTabPluginManager' => 'VuFind\Service\Factory::getRecordTabPluginManager',
            'VuFind\RelatedPluginManager' => 'VuFind\Service\Factory::getRelatedPluginManager',
            'VuFind\ResolverDriverPluginManager' => 'VuFind\Service\Factory::getResolverDriverPluginManager',
            'VuFind\Role\PermissionManager' => 'VuFind\Service\Factory::getPermissionManager',
            'VuFind\Role\PermissionDeniedManager' => 'VuFind\Service\Factory::getPermissionDeniedManager',
            'VuFind\Search' => 'VuFind\Service\Factory::getSearchService',
            'VuFind\Search\BackendManager' => 'VuFind\Service\Factory::getSearchBackendManager',
            'VuFind\Search\Memory' => 'VuFind\Service\Factory::getSearchMemory',
            'VuFind\SearchOptionsPluginManager' => 'VuFind\Service\Factory::getSearchOptionsPluginManager',
            'VuFind\SearchParamsPluginManager' => 'VuFind\Service\Factory::getSearchParamsPluginManager',
            'VuFind\SearchResultsPluginManager' => 'VuFind\Service\Factory::getSearchResultsPluginManager',
            'VuFind\SearchRunner' => 'VuFind\Service\Factory::getSearchRunner',
            'VuFind\SearchSpecsReader' => 'VuFind\Service\Factory::getSearchSpecsReader',
            'VuFind\SearchTabsHelper' => 'VuFind\Service\Factory::getSearchTabsHelper',
            'VuFind\SessionManager' => 'VuFind\Session\ManagerFactory',
            'VuFind\SessionPluginManager' => 'VuFind\Service\Factory::getSessionPluginManager',
            'VuFind\SMS' => 'VuFind\SMS\Factory',
            'VuFind\Solr\Writer' => 'VuFind\Service\Factory::getSolrWriter',
            'VuFind\Tags' => 'VuFind\Service\Factory::getTags',
            'VuFind\Translator' => 'VuFind\Service\Factory::getTranslator',
            'VuFind\WorldCatUtils' => 'VuFind\Service\Factory::getWorldCatUtils',
            'VuFind\YamlReader' => 'VuFind\Service\Factory::getYamlReader',
        ],
        'invokables' => [
            'VuFind\HierarchicalFacetHelper' => 'VuFind\Search\Solr\HierarchicalFacetHelper',
            'VuFind\IpAddressUtils' => 'VuFind\Net\IpAddressUtils',
            'VuFind\Session\Settings' => 'VuFind\Session\Settings',
        ],
        'initializers' => [
            'VuFind\ServiceManager\Initializer::initInstance',
        ],
        'aliases' => [
            'mvctranslator' => 'VuFind\Translator',
            'translator' => 'VuFind\Translator',
        ],
    ],
    'translator' => [],
    'view_helpers' => [
        'initializers' => [
            'VuFind\ServiceManager\Initializer::initZendPlugin',
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => APPLICATION_ENV == 'development',
        'display_exceptions'       => APPLICATION_ENV == 'development',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_path_stack'      => [],
        'whoops_no_catch' => [
            'VuFind\Exception\RecordMissing',
        ],
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => [
        // The config reader is a special service manager for loading .ini files:
        'config_reader' => [
            'abstract_factories' => ['VuFind\Config\PluginFactory'],
        ],
        // PostgreSQL sequence mapping
        'pgsql_seq_mapping'  => [
            'comments'         => ['id', 'comments_id_seq'],
            'external_session' => ['id', 'external_session_id_seq'],
            'oai_resumption'   => ['id', 'oai_resumption_id_seq'],
            'record'           => ['id', 'record_id_seq'],
            'resource'         => ['id', 'resource_id_seq'],
            'resource_tags'    => ['id', 'resource_tags_id_seq'],
            'search'           => ['id', 'search_id_seq'],
            'session'          => ['id', 'session_id_seq'],
            'tags'             => ['id', 'tags_id_seq'],
            'user'             => ['id', 'user_id_seq'],
            'user_card'        => ['id', 'user_card_id_seq'],
            'user_list'        => ['id', 'user_list_id_seq'],
            'user_resource'    => ['id', 'user_resource_id_seq'],
        ],
        // This section contains service manager configurations for all VuFind
        // pluggable components:
        'plugin_managers' => [
            'auth' => [
                'abstract_factories' => ['VuFind\Auth\PluginFactory'],
                'factories' => [
                    'choiceauth' => 'VuFind\Auth\Factory::getChoiceAuth',
                    'facebook' => 'VuFind\Auth\Factory::getFacebook',
                    'ils' => 'VuFind\Auth\Factory::getILS',
                    'multiils' => 'VuFind\Auth\Factory::getMultiILS',
                    'shibboleth' => 'VuFind\Auth\Factory::getShibboleth'
                ],
                'invokables' => [
                    'cas' => 'VuFind\Auth\CAS',
                    'database' => 'VuFind\Auth\Database',
                    'ldap' => 'VuFind\Auth\LDAP',
                    'multiauth' => 'VuFind\Auth\MultiAuth',
                    'sip2' => 'VuFind\Auth\SIP2',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'db' => 'Database',
                    'sip' => 'Sip2',
                ],
            ],
            'autocomplete' => [
                'abstract_factories' => ['VuFind\Autocomplete\PluginFactory'],
                'factories' => [
                    'solr' => 'VuFind\Autocomplete\Factory::getSolr',
                    'solrauth' => 'VuFind\Autocomplete\Factory::getSolrAuth',
                    'solrcn' => 'VuFind\Autocomplete\Factory::getSolrCN',
                    'solrreserves' => 'VuFind\Autocomplete\Factory::getSolrReserves',
                ],
                'invokables' => [
                    'none' => 'VuFind\Autocomplete\None',
                    'oclcidentities' => 'VuFind\Autocomplete\OCLCIdentities',
                    'tag' => 'VuFind\Autocomplete\Tag',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'noautocomplete' => 'None',
                    'oclcidentitiesautocomplete' => 'OCLCIdentities',
                    'solrautocomplete' => 'Solr',
                    'solrauthautocomplete' => 'SolrAuth',
                    'solrcnautocomplete' => 'SolrCN',
                    'solrreservesautocomplete' => 'SolrReserves',
                    'tagautocomplete' => 'Tag',
                ],
            ],
            'channelprovider' => [
                'factories' => [
                    'alphabrowse' => 'VuFind\ChannelProvider\Factory::getAlphaBrowse',
                    'facets' => 'VuFind\ChannelProvider\Factory::getFacets',
                    'listitems' => 'VuFind\ChannelProvider\Factory::getListItems',
                    'random' => 'VuFind\ChannelProvider\Factory::getRandom',
                    'similaritems' => 'VuFind\ChannelProvider\Factory::getSimilarItems',
                ]
            ],
            'content' => [
                'factories' => [
                    'authornotes' => 'VuFind\Content\Factory::getAuthorNotes',
                    'excerpts' => 'VuFind\Content\Factory::getExcerpts',
                    'reviews' => 'VuFind\Content\Factory::getReviews',
                ],
            ],
            'content_authornotes' => [
                'factories' => [
                    'syndetics' => 'VuFind\Content\AuthorNotes\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\AuthorNotes\Factory::getSyndeticsPlus',
                ],
            ],
            'content_excerpts' => [
                'factories' => [
                    'syndetics' => 'VuFind\Content\Excerpts\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Excerpts\Factory::getSyndeticsPlus',
                ],
            ],
            'content_covers' => [
                'factories' => [
                    'amazon' => 'VuFind\Content\Covers\Factory::getAmazon',
                    'booksite' => 'VuFind\Content\Covers\Factory::getBooksite',
                    'buchhandel' => 'VuFind\Content\Covers\Factory::getBuchhandel',
                    'contentcafe' => 'VuFind\Content\Covers\Factory::getContentCafe',
                    'syndetics' => 'VuFind\Content\Covers\Factory::getSyndetics',
                ],
                'invokables' => [
                    'google' => 'VuFind\Content\Covers\Google',
                    'librarything' => 'VuFind\Content\Covers\LibraryThing',
                    'localfile' => 'VuFind\Content\Covers\LocalFile',
                    'openlibrary' => 'VuFind\Content\Covers\OpenLibrary',
                    'summon' => 'VuFind\Content\Covers\Summon',
                ],
            ],
            'content_reviews' => [
                'factories' => [
                    'amazon' => 'VuFind\Content\Reviews\Factory::getAmazon',
                    'amazoneditorial' => 'VuFind\Content\Reviews\Factory::getAmazonEditorial',
                    'booksite' => 'VuFind\Content\Reviews\Factory::getBooksite',
                    'syndetics' => 'VuFind\Content\Reviews\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Reviews\Factory::getSyndeticsPlus',
                ],
                'invokables' => [
                    'guardian' => 'VuFind\Content\Reviews\Guardian',
                ],
            ],
            'db_row' => [
                'factories' => [
                    'changetracker' => 'VuFind\Db\Row\Factory::getChangeTracker',
                    'comments' => 'VuFind\Db\Row\Factory::getComments',
                    'externalsession' => 'VuFind\Db\Row\Factory::getExternalSession',
                    'oairesumption' => 'VuFind\Db\Row\Factory::getOaiResumption',
                    'record' => 'VuFind\Db\Row\Factory::getRecord',
                    'resource' => 'VuFind\Db\Row\Factory::getResource',
                    'resourcetags' => 'VuFind\Db\Row\Factory::getResourceTags',
                    'search' => 'VuFind\Db\Row\Factory::getSearch',
                    'session' => 'VuFind\Db\Row\Factory::getSession',
                    'tags' => 'VuFind\Db\Row\Factory::getTags',
                    'user' => 'VuFind\Db\Row\Factory::getUser',
                    'usercard' => 'VuFind\Db\Row\Factory::getUserCard',
                    'userlist' => 'VuFind\Db\Row\Factory::getUserList',
                    'userresource' => 'VuFind\Db\Row\Factory::getUserResource',
                ],
            ],
            'db_table' => [
                'abstract_factories' => ['VuFind\Db\Table\PluginFactory'],
                'factories' => [
                    'changetracker' => 'VuFind\Db\Table\Factory::getChangeTracker',
                    'comments' => 'VuFind\Db\Table\Factory::getComments',
                    'externalsession' => 'VuFind\Db\Table\Factory::getExternalSession',
                    'oairesumption' => 'VuFind\Db\Table\Factory::getOaiResumption',
                    'record' => 'VuFind\Db\Table\Factory::getRecord',
                    'resource' => 'VuFind\Db\Table\Factory::getResource',
                    'resourcetags' => 'VuFind\Db\Table\Factory::getResourceTags',
                    'search' => 'VuFind\Db\Table\Factory::getSearch',
                    'session' => 'VuFind\Db\Table\Factory::getSession',
                    'tags' => 'VuFind\Db\Table\Factory::getTags',
                    'user' => 'VuFind\Db\Table\Factory::getUser',
                    'usercard' => 'VuFind\Db\Table\Factory::getUserCard',
                    'userlist' => 'VuFind\Db\Table\Factory::getUserList',
                    'userresource' => 'VuFind\Db\Table\Factory::getUserResource',
                ],
            ],
            'hierarchy_driver' => [
                'factories' => [
                    'default' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyDefault',
                    'flat' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyFlat',
                ],
            ],
            'hierarchy_treedataformatter' => [
                'invokables' => [
                    'json' => 'VuFind\Hierarchy\TreeDataFormatter\Json',
                    'xml' => 'VuFind\Hierarchy\TreeDataFormatter\Xml',
                ],
            ],
            'hierarchy_treedatasource' => [
                'factories' => [
                    'solr' => 'VuFind\Hierarchy\TreeDataSource\Factory::getSolr',
                ],
                'invokables' => [
                    'xmlfile' => 'VuFind\Hierarchy\TreeDataSource\XMLFile',
                ],
            ],
            'hierarchy_treerenderer' => [
                'factories' => [
                    'jstree' => 'VuFind\Hierarchy\TreeRenderer\Factory::getJSTree'
                ],
            ],
            'ils_driver' => [
                'abstract_factories' => ['VuFind\ILS\Driver\PluginFactory'],
                'factories' => [
                    'aleph' => 'VuFind\ILS\Driver\Factory::getAleph',
                    'daia' => 'VuFind\ILS\Driver\Factory::getDAIA',
                    'demo' => 'VuFind\ILS\Driver\Factory::getDemo',
                    'horizon' => 'VuFind\ILS\Driver\Factory::getHorizon',
                    'horizonxmlapi' => 'VuFind\ILS\Driver\Factory::getHorizonXMLAPI',
                    'lbs4' => 'VuFind\ILS\Driver\Factory::getLBS4',
                    'multibackend' => 'VuFind\ILS\Driver\Factory::getMultiBackend',
                    'noils' => 'VuFind\ILS\Driver\Factory::getNoILS',
                    'paia' => 'VuFind\ILS\Driver\Factory::getPAIA',
                    'koha' => 'VuFind\ILS\Driver\Factory::getKoha',
                    'kohailsdi' => 'VuFind\ILS\Driver\Factory::getKohaILSDI',
                    'sierrarest' => 'VuFind\ILS\Driver\Factory::getSierraRest',
                    'symphony' => 'VuFind\ILS\Driver\Factory::getSymphony',
                    'unicorn' => 'VuFind\ILS\Driver\Factory::getUnicorn',
                    'voyager' => 'VuFind\ILS\Driver\Factory::getVoyager',
                    'voyagerrestful' => 'VuFind\ILS\Driver\Factory::getVoyagerRestful',
                ],
                'invokables' => [
                    'amicus' => 'VuFind\ILS\Driver\Amicus',
                    'claviussql' => 'VuFind\ILS\Driver\ClaviusSQL',
                    'evergreen' => 'VuFind\ILS\Driver\Evergreen',
                    'innovative' => 'VuFind\ILS\Driver\Innovative',
                    'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
                    'polaris' => 'VuFind\ILS\Driver\Polaris',
                    'sample' => 'VuFind\ILS\Driver\Sample',
                    'sierra' => 'VuFind\ILS\Driver\Sierra',
                    'virtua' => 'VuFind\ILS\Driver\Virtua',
                    'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
                ],
            ],
            'recommend' => [
                'abstract_factories' => ['VuFind\Recommend\PluginFactory'],
                'factories' => [
                    'authorfacets' => 'VuFind\Recommend\Factory::getAuthorFacets',
                    'authorinfo' => 'VuFind\Recommend\Factory::getAuthorInfo',
                    'authorityrecommend' => 'VuFind\Recommend\Factory::getAuthorityRecommend',
                    'catalogresults' => 'VuFind\Recommend\Factory::getCatalogResults',
                    'collectionsidefacets' => 'VuFind\Recommend\Factory::getCollectionSideFacets',
                    'dplaterms' => 'VuFind\Recommend\Factory::getDPLATerms',
                    'europeanaresults' => 'VuFind\Recommend\Factory::getEuropeanaResults',
                    'expandfacets' => 'VuFind\Recommend\Factory::getExpandFacets',
                    'favoritefacets' => 'VuFind\Recommend\Factory::getFavoriteFacets',
                    'mapselection' => 'VuFind\Recommend\Factory::getMapSelection',
                    'sidefacets' => 'VuFind\Recommend\Factory::getSideFacets',
                    'randomrecommend' => 'VuFind\Recommend\Factory::getRandomRecommend',
                    'summonbestbets' => 'VuFind\Recommend\Factory::getSummonBestBets',
                    'summondatabases' => 'VuFind\Recommend\Factory::getSummonDatabases',
                    'summonresults' => 'VuFind\Recommend\Factory::getSummonResults',
                    'summontopics' => 'VuFind\Recommend\Factory::getSummonTopics',
                    'switchquery' => 'VuFind\Recommend\Factory::getSwitchQuery',
                    'topfacets' => 'VuFind\Recommend\Factory::getTopFacets',
                    'visualfacets' => 'VuFind\Recommend\Factory::getVisualFacets',
                    'webresults' => 'VuFind\Recommend\Factory::getWebResults',
                    'worldcatidentities' => 'VuFind\Recommend\Factory::getWorldCatIdentities',
                ],
                'invokables' => [
                    'alphabrowselink' => 'VuFind\Recommend\AlphaBrowseLink',
                    'channels' => 'VuFind\Recommend\Channels',
                    'doi' => 'VuFind\Recommend\DOI',
                    'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
                    'facetcloud' => 'VuFind\Recommend\FacetCloud',
                    'libraryh3lp' => 'VuFind\Recommend\Libraryh3lp',
                    'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
                    'openlibrarysubjectsdeferred' => 'VuFind\Recommend\OpenLibrarySubjectsDeferred',
                    'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
                    'removefilters' => 'VuFind\Recommend\RemoveFilters',
                    'resultgooglemapajax' => 'VuFind\Recommend\Deprecated',
                    'spellingsuggestions' => 'VuFind\Recommend\SpellingSuggestions',
                    'summonbestbetsdeferred' => 'VuFind\Recommend\SummonBestBetsDeferred',
                    'summondatabasesdeferred' => 'VuFind\Recommend\SummonDatabasesDeferred',
                    'summonresultsdeferred' => 'VuFind\Recommend\SummonResultsDeferred',
                    'switchtype' => 'VuFind\Recommend\SwitchType',
                    'worldcatterms' => 'VuFind\Recommend\Deprecated',
                ],
            ],
            'recorddriver' => [
                'abstract_factories' => ['VuFind\RecordDriver\PluginFactory'],
                'factories' => [
                    'eds' => 'VuFind\RecordDriver\Factory::getEDS',
                    'eit' => 'VuFind\RecordDriver\Factory::getEIT',
                    'missing' => 'VuFind\RecordDriver\Factory::getMissing',
                    'pazpar2' => 'VuFind\RecordDriver\Factory::getPazpar2',
                    'primo' => 'VuFind\RecordDriver\Factory::getPrimo',
                    'solrauth' => 'VuFind\RecordDriver\Factory::getSolrAuth',
                    'solrdefault' => 'VuFind\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'VuFind\RecordDriver\Factory::getSolrMarc',
                    'solrmarcremote' => 'VuFind\RecordDriver\Factory::getSolrMarcRemote',
                    'solrreserves' => 'VuFind\RecordDriver\Factory::getSolrReserves',
                    'solrweb' => 'VuFind\RecordDriver\Factory::getSolrWeb',
                    'summon' => 'VuFind\RecordDriver\Factory::getSummon',
                    'worldcat' => 'VuFind\RecordDriver\Factory::getWorldCat',
                ],
                'invokables' => [
                    'browzine' => 'VuFind\RecordDriver\BrowZine',
                    'libguides' => 'VuFind\RecordDriver\LibGuides',
                ],
            ],
            'recordtab' => [
                'abstract_factories' => ['VuFind\RecordTab\PluginFactory'],
                'factories' => [
                    'collectionhierarchytree' => 'VuFind\RecordTab\Factory::getCollectionHierarchyTree',
                    'collectionlist' => 'VuFind\RecordTab\Factory::getCollectionList',
                    'excerpt' => 'VuFind\RecordTab\Factory::getExcerpt',
                    'hierarchytree' => 'VuFind\RecordTab\Factory::getHierarchyTree',
                    'holdingsils' => 'VuFind\RecordTab\Factory::getHoldingsILS',
                    'holdingsworldcat' => 'VuFind\RecordTab\Factory::getHoldingsWorldCat',
                    'map' => 'VuFind\RecordTab\Factory::getMap',
                    'preview' => 'VuFind\RecordTab\Factory::getPreview',
                    'reviews' => 'VuFind\RecordTab\Factory::getReviews',
                    'similaritemscarousel' => 'VuFind\RecordTab\Factory::getSimilarItemsCarousel',
                    'usercomments' => 'VuFind\RecordTab\Factory::getUserComments',
                ],
                'invokables' => [
                    'description' => 'VuFind\RecordTab\Description',
                    'staffviewarray' => 'VuFind\RecordTab\StaffViewArray',
                    'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                    'toc' => 'VuFind\RecordTab\TOC',
                ],
                'initializers' => [
                    'ZfcRbac\Initializer\AuthorizationServiceInitializer'
                ],
            ],
            'related' => [
                'abstract_factories' => ['VuFind\Related\PluginFactory'],
                'factories' => [
                    'similar' => 'VuFind\Related\Factory::getSimilar',
                    'worldcatsimilar' => 'VuFind\Related\Factory::getWorldCatSimilar',
                ],
                'invokables' => [
                    'channels' => 'VuFind\Related\Channels',
                    'editions' => 'VuFind\Related\Deprecated',
                    'worldcateditions' => 'VuFind\Related\Deprecated',
                ],
            ],
            'resolver_driver' => [
                'abstract_factories' => ['VuFind\Resolver\Driver\PluginFactory'],
                'factories' => [
                    '360link' => 'VuFind\Resolver\Driver\Factory::getThreesixtylink',
                    'ezb' => 'VuFind\Resolver\Driver\Factory::getEzb',
                    'sfx' => 'VuFind\Resolver\Driver\Factory::getSfx',
                    'redi' => 'VuFind\Resolver\Driver\Factory::getRedi',
                ],
                'invokables' => [
                    'demo' => 'VuFind\Resolver\Driver\Demo',
                ],
                'aliases' => [
                    'threesixtylink' => '360link',
                ],
            ],
            'search_backend' => [
                'factories' => [
                    'BrowZine' => 'VuFind\Search\Factory\BrowZineBackendFactory',
                    'EDS' => 'VuFind\Search\Factory\EdsBackendFactory',
                    'EIT' => 'VuFind\Search\Factory\EITBackendFactory',
                    'LibGuides' => 'VuFind\Search\Factory\LibGuidesBackendFactory',
                    'Pazpar2' => 'VuFind\Search\Factory\Pazpar2BackendFactory',
                    'Primo' => 'VuFind\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'VuFind\Search\Factory\SolrDefaultBackendFactory',
                    'SolrAuth' => 'VuFind\Search\Factory\SolrAuthBackendFactory',
                    'SolrReserves' => 'VuFind\Search\Factory\SolrReservesBackendFactory',
                    'SolrWeb' => 'VuFind\Search\Factory\SolrWebBackendFactory',
                    'Summon' => 'VuFind\Search\Factory\SummonBackendFactory',
                    'WorldCat' => 'VuFind\Search\Factory\WorldCatBackendFactory',
                ],
                'aliases' => [
                    // Allow Solr core names to be used as aliases for services:
                    'authority' => 'SolrAuth',
                    'biblio' => 'Solr',
                    'reserves' => 'SolrReserves',
                    // Legacy:
                    'VuFind' => 'Solr',
                ]
            ],
            'search_options' => [
                'abstract_factories' => ['VuFind\Search\Options\PluginFactory'],
                'factories' => [
                    'eds' => 'VuFind\Search\Options\Factory::getEDS',
                ],
            ],
            'search_params' => [
                'abstract_factories' => ['VuFind\Search\Params\PluginFactory'],
                'factories' => [
                    'solr' => 'VuFind\Search\Params\Factory::getSolr',
                ],
            ],
            'search_results' => [
                'abstract_factories' => ['VuFind\Search\Results\PluginFactory'],
                'factories' => [
                    'favorites' => 'VuFind\Search\Results\Factory::getFavorites',
                    'solr' => 'VuFind\Search\Results\Factory::getSolr',
                    'tags' => 'VuFind\Search\Results\Factory::getTags',
                ],
            ],
            'session' => [
                'abstract_factories' => ['VuFind\Session\PluginFactory'],
                'invokables' => [
                    'database' => 'VuFind\Session\Database',
                    'file' => 'VuFind\Session\File',
                    'memcache' => 'VuFind\Session\Memcache',
                ],
                'aliases' => [
                    // for legacy 1.x compatibility
                    'filesession' => 'File',
                    'memcachesession' => 'Memcache',
                    'mysqlsession' => 'Database',
                ],
            ]
        ],
        // This section behaves just like recorddriver_tabs below, but is used for
        // the collection module instead of the standard record view.
        'recorddriver_collection_tabs' => [
            'VuFind\RecordDriver\AbstractBase' => [
                'tabs' => [
                    'CollectionList' => 'CollectionList',
                    'HierarchyTree' => 'CollectionHierarchyTree',
                ],
                'defaultTab' => null,
            ],
        ],
        // This section controls which tabs are used for which record driver classes.
        // Each sub-array is a map from a tab name (as used in a record URL) to a tab
        // service (found in recordtab_plugin_manager, below).  If a particular record
        // driver is not defined here, it will inherit configuration from a configured
        // parent class.  The defaultTab setting may be used to specify the default
        // active tab; if null, the value from the relevant .ini file will be used.
        // You can also specify which tabs are loaded in the background when arriving
        // at a record tabs view with backgroundLoadedTabs as a list of tab indexes.
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\EDS' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Pazpar2' => [
                'tabs' => [
                    'Details' => 'StaffViewMARC',
                 ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Primo' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\SolrAuth' => [
                'tabs' => [
                    'Details' => 'StaffViewMARC',
                 ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\SolrDefault' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Similar' => 'SimilarItemsCarousel',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
                // 'backgroundLoadedTabs' => ['UserComments', 'Details']
            ],
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Similar' => 'SimilarItemsCarousel',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\Summon' => [
                'tabs' => [
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ],
                'defaultTab' => null,
            ],
            'VuFind\RecordDriver\WorldCat' => [
                'tabs' => [
                    'Holdings' => 'HoldingsWorldCat', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Details' => 'StaffViewMARC',
                ],
                'defaultTab' => null,
            ],
        ],
    ],
    // Authorization configuration:
    'zfc_rbac' => [
        'identity_provider' => 'VuFind\AuthManager',
        'guest_role' => 'guest',
        'role_provider' => [
            'VuFind\Role\DynamicRoleProvider' => [
                'map_legacy_settings' => true,
            ],
        ],
        'role_provider_manager' => [
            'factories' => [
                'VuFind\Role\DynamicRoleProvider' => 'VuFind\Role\DynamicRoleProviderFactory',
            ],
        ],
        'vufind_permission_provider_manager' => [
            'factories' => [
                'ipRange' => 'VuFind\Role\PermissionProvider\Factory::getIpRange',
                'ipRegEx' => 'VuFind\Role\PermissionProvider\Factory::getIpRegEx',
                'serverParam' => 'VuFind\Role\PermissionProvider\Factory::getServerParam',
                'shibboleth' => 'VuFind\Role\PermissionProvider\Factory::getShibboleth',
                'user' => 'VuFind\Role\PermissionProvider\Factory::getUser',
                'username' => 'VuFind\Role\PermissionProvider\Factory::getUsername',
            ],
            'invokables' => [
                'role' => 'VuFind\Role\PermissionProvider\Role',
            ],
        ],
    ],
];

// Define record view routes -- route name => controller
$recordRoutes = [
    'record' => 'Record',
    'collection' => 'Collection',
    'edsrecord' => 'EdsRecord',
    'eitrecord' => 'EITRecord',
    'missingrecord' => 'MissingRecord',
    'primorecord' => 'PrimoRecord',
    'solrauthrecord' => 'Authority',
    'summonrecord' => 'SummonRecord',
    'worldcatrecord' => 'WorldcatRecord',

    // For legacy (1.x/2.x) compatibility:
    'vufindrecord' => 'Record',
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
    'MyResearch' => ['userList' => 'MyList/[:id]', 'editList' => 'EditList/[:id]'],
    'LibraryCards' => ['editLibraryCard' => 'editCard/[:id]'],
];

// Define static routes -- Controller/Action strings
$staticRoutes = [
    'Alphabrowse/Home', 'Author/FacetList', 'Author/Home', 'Author/Search',
    'Authority/FacetList', 'Authority/Home', 'Authority/Record', 'Authority/Search',
    'Browse/Author', 'Browse/Dewey', 'Browse/Era', 'Browse/Genre', 'Browse/Home',
    'Browse/LCC', 'Browse/Region', 'Browse/Tag', 'Browse/Topic', 'Cart/doExport',
    'BrowZine/Home', 'BrowZine/Search',
    'Cart/Email', 'Cart/Export', 'Cart/Home', 'Cart/MyResearchBulk',
    'Cart/Processor', 'Cart/Save', 'Cart/SearchResultsBulk',
    'Channels/Home', 'Channels/Record', 'Channels/Search',
    'Collections/ByTitle',
    'Collections/Home', 'Combined/Home', 'Combined/Results', 'Combined/SearchBox',
    'Confirm/Confirm', 'Cover/Show', 'Cover/Unavailable',
    'EDS/Advanced', 'EDS/Home', 'EDS/Search',
    'EIT/Advanced', 'EIT/Home', 'EIT/Search',
    'Error/PermissionDenied', 'Error/Unavailable',
    'Feedback/Email', 'Feedback/Home', 'Help/Home',
    'Install/Done', 'Install/FixBasicConfig', 'Install/FixCache',
    'Install/FixDatabase', 'Install/FixDependencies', 'Install/FixILS',
    'Install/FixSecurity', 'Install/FixSolr', 'Install/FixSSLCerts', 'Install/Home',
    'Install/PerformSecurityFix', 'Install/ShowSQL',
    'LibGuides/Home', 'LibGuides/Results',
    'LibraryCards/Home', 'LibraryCards/SelectCard',
    'LibraryCards/DeleteCard',
    'MyResearch/Account', 'MyResearch/ChangePassword', 'MyResearch/CheckedOut',
    'MyResearch/Delete', 'MyResearch/DeleteList', 'MyResearch/Edit',
    'MyResearch/Email', 'MyResearch/Favorites', 'MyResearch/Fines',
    'MyResearch/Holds', 'MyResearch/Home',
    'MyResearch/ILLRequests', 'MyResearch/Logout',
    'MyResearch/NewPassword', 'MyResearch/Profile',
    'MyResearch/Recover', 'MyResearch/SaveSearch',
    'MyResearch/StorageRetrievalRequests', 'MyResearch/UserLogin',
    'MyResearch/Verify',
    'Primo/Advanced', 'Primo/Home', 'Primo/Search',
    'QRCode/Show', 'QRCode/Unavailable',
    'OAI/Server', 'Pazpar2/Home', 'Pazpar2/Search', 'Records/Home',
    'Search/Advanced', 'Search/Email', 'Search/FacetList', 'Search/History',
    'Search/Home', 'Search/NewItem', 'Search/OpenSearch', 'Search/Reserves',
    'Search/ReservesFacetList', 'Search/Results', 'Search/Suggest',
    'Summon/Advanced', 'Summon/FacetList', 'Summon/Home', 'Summon/Search',
    'Tag/Home',
    'Upgrade/Home', 'Upgrade/FixAnonymousTags', 'Upgrade/FixDuplicateTags',
    'Upgrade/FixConfig', 'Upgrade/FixDatabase', 'Upgrade/FixMetadata',
    'Upgrade/GetDBCredentials', 'Upgrade/GetDbEncodingPreference',
    'Upgrade/GetSourceDir', 'Upgrade/GetSourceVersion', 'Upgrade/Reset',
    'Upgrade/ShowSQL',
    'Web/Home', 'Web/FacetList', 'Web/Results',
    'Worldcat/Advanced', 'Worldcat/Home', 'Worldcat/Search'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// Add the home route last
$config['router']['routes']['home'] = [
    'type' => 'Zend\Mvc\Router\Http\Literal',
    'options' => [
        'route'    => '/',
        'defaults' => [
            'controller' => 'index',
            'action'     => 'Home',
        ]
    ]
];

return $config;
