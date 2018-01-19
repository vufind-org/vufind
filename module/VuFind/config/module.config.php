<?php
namespace VuFind\Module\Config;

$config = [
    'router' => [
        'routes' => [
            'default' => [
                'type'    => 'Zend\Router\Http\Segment',
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
                'type'    => 'Zend\Router\Http\Segment',
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
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/AlphaBrowse/Results',
                    'defaults' => [
                        'controller' => 'Alphabrowse',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-bookcover' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/bookcover.php',
                    'defaults' => [
                        'controller' => 'Cover',
                        'action'     => 'Show',
                    ]
                ]
            ],
            'legacy-summonrecord' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/Summon/Record',
                    'defaults' => [
                        'controller' => 'SummonRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'legacy-worldcatrecord' => [
                'type' => 'Zend\Router\Http\Literal',
                'options' => [
                    'route'    => '/WorldCat/Record',
                    'defaults' => [
                        'controller' => 'WorldcatRecord',
                        'action'     => 'Home',
                    ]
                ]
            ],
            'soap-shibboleth-logout-notification-handler' => [
                'type' => 'Zend\Router\Http\Literal',
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
            'VuFind\Controller\AjaxController' => 'VuFind\Controller\Factory::getAjaxController',
            'VuFind\Controller\AlphabrowseController' => 'VuFind\Controller\Factory::getAlphabrowseController',
            'VuFind\Controller\AuthorController' => 'VuFind\Controller\Factory::getAuthorController',
            'VuFind\Controller\AuthorityController' => 'VuFind\Controller\Factory::getAuthorityController',
            'VuFind\Controller\BrowseController' => 'VuFind\Controller\Factory::getBrowseController',
            'VuFind\Controller\BrowZineController' => 'VuFind\Controller\Factory::getBrowZineController',
            'VuFind\Controller\CartController' => 'VuFind\Controller\Factory::getCartController',
            'VuFind\Controller\ChannelsController' => 'VuFind\Controller\Factory::getChannelsController',
            'VuFind\Controller\CollectionController' => 'VuFind\Controller\Factory::getCollectionController',
            'VuFind\Controller\CollectionsController' => 'VuFind\Controller\Factory::getCollectionsController',
            'VuFind\Controller\CombinedController' => 'VuFind\Controller\Factory::getCombinedController',
            'VuFind\Controller\ConfirmController' => 'VuFind\Controller\Factory::getConfirmController',
            'VuFind\Controller\ContentController' => 'VuFind\Controller\Factory::getContentController',
            'VuFind\Controller\CoverController' => 'VuFind\Controller\Factory::getCoverController',
            'VuFind\Controller\EdsController' => 'VuFind\Controller\Factory::getEdsController',
            'VuFind\Controller\EdsrecordController' => 'VuFind\Controller\Factory::getEdsrecordController',
            'VuFind\Controller\EITController' => 'VuFind\Controller\Factory::getEITController',
            'VuFind\Controller\EITrecordController' => '\VuFind\Controller\Factory::getEITrecordController',
            'VuFind\Controller\ErrorController' => 'VuFind\Controller\Factory::getErrorController',
            'VuFind\Controller\ExternalAuthController' => 'VuFind\Controller\Factory::getExternalAuthController',
            'VuFind\Controller\FeedbackController' => 'VuFind\Controller\Factory::getFeedbackController',
            'VuFind\Controller\HelpController' => 'VuFind\Controller\Factory::getHelpController',
            'VuFind\Controller\HierarchyController' => 'VuFind\Controller\Factory::getHierarchyController',
            'VuFind\Controller\IndexController' => 'VuFind\Controller\Factory::getIndexController',
            'VuFind\Controller\InstallController' => 'VuFind\Controller\Factory::getInstallController',
            'VuFind\Controller\LibGuidesController' => 'VuFind\Controller\Factory::getLibGuidesController',
            'VuFind\Controller\LibraryCardsController' => 'VuFind\Controller\Factory::getLibraryCardsController',
            'VuFind\Controller\MissingrecordController' => 'VuFind\Controller\Factory::getMissingrecordController',
            'VuFind\Controller\MyResearchController' => 'VuFind\Controller\Factory::getMyResearchController',
            'VuFind\Controller\OaiController' => 'VuFind\Controller\Factory::getOaiController',
            'VuFind\Controller\Pazpar2Controller' => 'VuFind\Controller\Factory::getPazpar2Controller',
            'VuFind\Controller\PrimoController' => 'VuFind\Controller\Factory::getPrimoController',
            'VuFind\Controller\PrimorecordController' => 'VuFind\Controller\Factory::getPrimorecordController',
            'VuFind\Controller\QRCodeController' => 'VuFind\Controller\Factory::getQRCodeController',
            'VuFind\Controller\RecordController' => 'VuFind\Controller\Factory::getRecordController',
            'VuFind\Controller\RecordsController' => 'VuFind\Controller\Factory::getRecordsController',
            'VuFind\Controller\SearchController' => 'VuFind\Controller\Factory::getSearchController',
            'VuFind\Controller\ShibbolethLogoutNotificationController' => 'VuFind\Controller\Factory::getShibbolethLogoutNotificationController',
            'VuFind\Controller\SummonController' => 'VuFind\Controller\Factory::getSummonController',
            'VuFind\Controller\SummonrecordController' => 'VuFind\Controller\Factory::getSummonrecordController',
            'VuFind\Controller\TagController' => 'VuFind\Controller\Factory::getTagController',
            'VuFind\Controller\UpgradeController' => 'VuFind\Controller\Factory::getUpgradeController',
            'VuFind\Controller\WebController' => 'VuFind\Controller\Factory::getWebController',
            'VuFind\Controller\WorldcatController' => 'VuFind\Controller\Factory::getWorldcatController',
            'VuFind\Controller\WorldcatrecordController' => 'VuFind\Controller\Factory::getWorldcatrecordController',
        ],
        'aliases' => [
            'AJAX' => 'VuFind\Controller\AjaxController',
            'ajax' => 'VuFind\Controller\AjaxController',
            'Alphabrowse' => 'VuFind\Controller\AlphabrowseController',
            'alphabrowse' => 'VuFind\Controller\AlphabrowseController',
            'Author' => 'VuFind\Controller\AuthorController',
            'author' => 'VuFind\Controller\AuthorController',
            'Authority' => 'VuFind\Controller\AuthorityController',
            'authority' => 'VuFind\Controller\AuthorityController',
            'Browse' => 'VuFind\Controller\BrowseController',
            'browse' => 'VuFind\Controller\BrowseController',
            'BrowZine' => 'VuFind\Controller\BrowZineController',
            'browzine' => 'VuFind\Controller\BrowZineController',
            'Cart' => 'VuFind\Controller\CartController',
            'cart' => 'VuFind\Controller\CartController',
            'Channels' => 'VuFind\Controller\ChannelsController',
            'channels' => 'VuFind\Controller\ChannelsController',
            'Collection' => 'VuFind\Controller\CollectionController',
            'collection' => 'VuFind\Controller\CollectionController',
            'Collections' => 'VuFind\Controller\CollectionsController',
            'collections' => 'VuFind\Controller\CollectionsController',
            'Combined' => 'VuFind\Controller\CombinedController',
            'combined' => 'VuFind\Controller\CombinedController',
            'Confirm' => 'VuFind\Controller\ConfirmController',
            'confirm' => 'VuFind\Controller\ConfirmController',
            'Content' => 'VuFind\Controller\ContentController',
            'content' => 'VuFind\Controller\ContentController',
            'Cover' => 'VuFind\Controller\CoverController',
            'cover' => 'VuFind\Controller\CoverController',
            'EDS' => 'VuFind\Controller\EdsController',
            'eds' => 'VuFind\Controller\EdsController',
            'EdsRecord' => 'VuFind\Controller\EdsrecordController',
            'edsrecord' => 'VuFind\Controller\EdsrecordController',
            'EIT' => 'VuFind\Controller\EITController',
            'eit' => 'VuFind\Controller\EITController',
            'EITRecord' => 'VuFind\Controller\EITrecordController',
            'eitrecord' => 'VuFind\Controller\EITrecordController',
            'Error' => 'VuFind\Controller\ErrorController',
            'error' => 'VuFind\Controller\ErrorController',
            'ExternalAuth' => 'VuFind\Controller\ExternalAuthController',
            'externalauth' => 'VuFind\Controller\ExternalAuthController',
            'Feedback' => 'VuFind\Controller\FeedbackController',
            'feedback' => 'VuFind\Controller\FeedbackController',
            'Help' => 'VuFind\Controller\HelpController',
            'help' => 'VuFind\Controller\HelpController',
            'Hierarchy' => 'VuFind\Controller\HierarchyController',
            'hierarchy' => 'VuFind\Controller\HierarchyController',
            'Index' => 'VuFind\Controller\IndexController',
            'index' => 'VuFind\Controller\IndexController',
            'Install' => 'VuFind\Controller\InstallController',
            'install' => 'VuFind\Controller\InstallController',
            'LibGuides' => 'VuFind\Controller\LibGuidesController',
            'libguides' => 'VuFind\Controller\LibGuidesController',
            'LibraryCards' => 'VuFind\Controller\LibraryCardsController',
            'librarycards' => 'VuFind\Controller\LibraryCardsController',
            'MissingRecord' => 'VuFind\Controller\MissingrecordController',
            'missingrecord' => 'VuFind\Controller\MissingrecordController',
            'MyResearch' => 'VuFind\Controller\MyResearchController',
            'myresearch' => 'VuFind\Controller\MyResearchController',
            'OAI' => 'VuFind\Controller\OaiController',
            'oai' => 'VuFind\Controller\OaiController',
            'Pazpar2' => 'VuFind\Controller\Pazpar2Controller',
            'pazpar2' => 'VuFind\Controller\Pazpar2Controller',
            'Primo' => 'VuFind\Controller\PrimoController',
            'primo' => 'VuFind\Controller\PrimoController',
            'PrimoRecord' => 'VuFind\Controller\PrimorecordController',
            'primorecord' => 'VuFind\Controller\PrimorecordController',
            'QRCode' => 'VuFind\Controller\QRCodeController',
            'qrcode' => 'VuFind\Controller\QRCodeController',
            'Record' => 'VuFind\Controller\RecordController',
            'record' => 'VuFind\Controller\RecordController',
            'Records' => 'VuFind\Controller\RecordsController',
            'records' => 'VuFind\Controller\RecordsController',
            'Search' => 'VuFind\Controller\SearchController',
            'search' => 'VuFind\Controller\SearchController',
            'ShibbolethLogoutNotification' => 'VuFind\Controller\ShibbolethLogoutNotificationController',
            'shibbolethlogoutnotification' => 'VuFind\Controller\ShibbolethLogoutNotificationController',
            'Summon' => 'VuFind\Controller\SummonController',
            'summon' => 'VuFind\Controller\SummonController',
            'SummonRecord' => 'VuFind\Controller\SummonrecordController',
            'summonrecord' => 'VuFind\Controller\SummonrecordController',
            'Tag' => 'VuFind\Controller\TagController',
            'tag' => 'VuFind\Controller\TagController',
            'Upgrade' => 'VuFind\Controller\UpgradeController',
            'upgrade' => 'VuFind\Controller\UpgradeController',
            'Web' => 'VuFind\Controller\WebController',
            'web' => 'VuFind\Controller\WebController',
            'Worldcat' => 'VuFind\Controller\WorldcatController',
            'worldcat' => 'VuFind\Controller\WorldcatController',
            'WorldcatRecord' => 'VuFind\Controller\WorldcatrecordController',
            'worldcatrecord' => 'VuFind\Controller\WorldcatrecordController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'VuFind\Controller\Plugin\DbUpgrade' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'VuFind\Controller\Plugin\Favorites' => 'VuFind\Controller\Plugin\Factory::getFavorites',
            'VuFind\Controller\Plugin\Followup' => 'VuFind\Controller\Plugin\Factory::getFollowup',
            'VuFind\Controller\Plugin\Holds' => 'VuFind\Controller\Plugin\Factory::getHolds',
            'VuFind\Controller\Plugin\ILLRequests' => 'VuFind\Controller\Plugin\Factory::getILLRequests',
            'VuFind\Controller\Plugin\NewItems' => 'VuFind\Controller\Plugin\Factory::getNewItems',
            'VuFind\Controller\Plugin\Permission' => 'VuFind\Controller\Plugin\Factory::getPermission',
            'VuFind\Controller\Plugin\Recaptcha' => 'VuFind\Controller\Plugin\Factory::getRecaptcha',
            'VuFind\Controller\Plugin\Renewals' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'VuFind\Controller\Plugin\Reserves' => 'VuFind\Controller\Plugin\Factory::getReserves',
            'VuFind\Controller\Plugin\ResultScroller' => 'VuFind\Controller\Plugin\Factory::getResultScroller',
            'VuFind\Controller\Plugin\StorageRetrievalRequests' => 'VuFind\Controller\Plugin\Factory::getStorageRetrievalRequests',
            'Zend\Mvc\Plugin\FlashMessenger\FlashMessenger' => 'VuFind\Controller\Plugin\Factory::getFlashMessenger',
        ],
        'aliases' => [
            'dbUpgrade' => 'VuFind\Controller\Plugin\DbUpgrade',
            'favorites' => 'VuFind\Controller\Plugin\Favorites',
            'flashMessenger' => 'Zend\Mvc\Plugin\FlashMessenger\FlashMessenger',
            'followup' => 'VuFind\Controller\Plugin\Followup',
            'holds' => 'VuFind\Controller\Plugin\Holds',
            'ILLRequests' => 'VuFind\Controller\Plugin\ILLRequests',
            'newItems' => 'VuFind\Controller\Plugin\NewItems',
            'permission' => 'VuFind\Controller\Plugin\Permission',
            'recaptcha' => 'VuFind\Controller\Plugin\Recaptcha',
            'renewals' => 'VuFind\Controller\Plugin\Renewals',
            'reserves' => 'VuFind\Controller\Plugin\Reserves',
            'resultScroller' => 'VuFind\Controller\Plugin\ResultScroller',
            'storageRetrievalRequests' => 'VuFind\Controller\Plugin\StorageRetrievalRequests',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'VuFind\AccountCapabilities' => 'VuFind\Service\Factory::getAccountCapabilities',
            'VuFind\AuthManager' => 'VuFind\Auth\Factory::getManager',
            'VuFind\AuthPluginManager' => 'VuFind\Service\Factory::getAuthPluginManager',
            'VuFind\AutocompletePluginManager' => 'VuFind\Service\Factory::getAutocompletePluginManager',
            'VuFind\Autocomplete\Suggester' => 'VuFind\Autocomplete\SuggesterFactory',
            'VuFind\CacheManager' => 'VuFind\Service\Factory::getCacheManager',
            'VuFind\Cart' => 'VuFind\Service\Factory::getCart',
            'VuFind\ChannelProviderPluginManager' => 'VuFind\Service\Factory::getChannelProviderPluginManager',
            'VuFind\Config' => 'VuFind\Service\Factory::getConfig',
            'VuFind\ContentPluginManager' => 'VuFind\Service\Factory::getContentPluginManager',
            'VuFind\ContentAuthorNotesPluginManager' => 'VuFind\Service\Factory::getContentAuthorNotesPluginManager',
            'VuFind\ContentCoversPluginManager' => 'VuFind\Service\Factory::getContentCoversPluginManager',
            'VuFind\ContentExcerptsPluginManager' => 'VuFind\Service\Factory::getContentExcerptsPluginManager',
            'VuFind\ContentReviewsPluginManager' => 'VuFind\Service\Factory::getContentReviewsPluginManager',
            'VuFind\ContentSummariesPluginManager' => 'VuFind\Service\Factory::getContentSummariesPluginManager',
            'VuFind\ContentTOCPluginManager' => 'VuFind\Service\Factory::getContentTOCPluginManager',
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
            'VuFind\Search\History' => 'VuFind\Service\Factory::getSearchHistory',
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
            'VuFind\ServiceManager\ServiceInitializer',
        ],
        'aliases' => [
            'mvctranslator' => 'VuFind\Translator',
            'translator' => 'VuFind\Translator',
        ],
    ],
    'translator' => [],
    'view_helpers' => [
        'initializers' => [
            'VuFind\ServiceManager\ServiceInitializer',
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
            'auth' => [ /* see VuFind\Auth\PluginManager for defaults */ ],
            'autocomplete' => [ /* see VuFind\Autocomplete\PluginManager for defaults */ ],
            'channelprovider' => [ /* see VuFind\ChannelProvider\PluginManager for defaults */ ],
            'content' => [ /* see VuFind\Content\PluginManager for defaults */ ],
            'content_authornotes' => [ /* see VuFind\Content\AuthorNotes\PluginManager for defaults */ ],
            'content_covers' => [ /* see VuFind\Content\Covers\PluginManager for defaults */ ],
            'content_excerpts' => [ /* see VuFind\Content\Excerpts\PluginManager for defaults */ ],
            'content_reviews' => [ /* see VuFind\Content\Reviews\PluginManager for defaults */ ],
            'content_summaries' => [ /* see VuFind\Content\Summaries\PluginManager for defaults */ ],
            'content_toc' => [ /* see VuFind\Content\TOC\PluginManager for defaults */ ],
            'db_row' => [ /* see VuFind\Db\Row\PluginManager for defaults */ ],
            'db_table' => [ /* see VuFind\Db\Table\PluginManager for defaults */ ],
            'hierarchy_driver' => [ /* see VuFind\Hierarchy\Driver\PluginManager for defaults */ ],
            'hierarchy_treedataformatter' => [ /* see VuFind\Hierarchy\TreeDataFormatter\PluginManager for defaults */ ],
            'hierarchy_treedatasource' => [ /* see VuFind\Hierarchy\TreeDataSource\PluginManager for defaults */ ],
            'hierarchy_treerenderer' => [ /* see VuFind\Hierarchy\TreeRenderer\PluginManager for defaults */ ],
            'ils_driver' => [ /* See VuFind\ILS\Driver\PluginManager for defaults */ ],
            'recommend' => [ /* See VuFind\Recommend\PluginManager for defaults */ ],
            'recorddriver' => [ /* See VuFind\RecordDriver\PluginManager for defaults */ ],
            'recordtab' => [ /* See VuFind\RecordTab\PluginManager for defaults */ ],
            'related' => [ /* See VuFind\Related\PluginManager for defaults */ ],
            'resolver_driver' => [ /* See VuFind\Resolver\Driver\PluginManager for defaults */ ],
            'search_backend' => [ /* See VuFind\Search\BackendRegistry for defaults */ ],
            'search_options' => [ /* See VuFind\Search\Options\PluginManager for defaults */ ],
            'search_params' => [ /* See VuFind\Search\Params\PluginManager for defaults */ ],
            'search_results' => [ /* See VuFind\Search\Results\PluginManager for defaults */ ],
            'session' => [ /* see VuFind\Session\PluginManager for defaults */ ],
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
        // service (found in recordtab plugin manager settings above). If a
        // particular record driver is not defined here, it will inherit
        // configuration from a configured parent class.  The defaultTab setting may
        // be used to specify the default active tab; if null, the value from the
        // relevant .ini file will be used. You can also specify which tabs are
        // loaded in the background when arriving at a record tabs view with
        // backgroundLoadedTabs as a list of tab indexes.
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
            'VuFind\RecordDriver\DefaultRecord' => [
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
    'MyResearch/HistoricLoans', 'MyResearch/Holds', 'MyResearch/Home',
    'MyResearch/ILLRequests', 'MyResearch/Logout',
    'MyResearch/NewPassword', 'MyResearch/Profile',
    'MyResearch/Recover', 'MyResearch/SaveSearch',
    'MyResearch/StorageRetrievalRequests', 'MyResearch/UserLogin',
    'MyResearch/Verify',
    'Primo/Advanced', 'Primo/Home', 'Primo/Search',
    'QRCode/Show', 'QRCode/Unavailable',
    'OAI/Server', 'Pazpar2/Home', 'Pazpar2/Search', 'Records/Home',
    'Search/Advanced', 'Search/CollectionFacetList', 'Search/Email',
    'Search/FacetList', 'Search/History', 'Search/Home', 'Search/NewItem',
    'Search/OpenSearch', 'Search/Reserves', 'Search/ReservesFacetList',
    'Search/Results', 'Search/Suggest',
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
    'type' => 'Zend\Router\Http\Literal',
    'options' => [
        'route'    => '/',
        'defaults' => [
            'controller' => 'index',
            'action'     => 'Home',
        ]
    ]
];

return $config;
