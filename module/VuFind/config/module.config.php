<?php
namespace VuFind\Module\Config;

$config = array(
    'router' => array(
        'routes' => array(
            'default' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/[:controller[/[:action]]]',
                    'constraints' => array(
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'index',
                        'action'     => 'Home',
                    ),
                ),
            ),
            'legacy-alphabrowse-results' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/AlphaBrowse/Results',
                    'defaults' => array(
                        'controller' => 'Alphabrowse',
                        'action'     => 'Home',
                    )
                )
            ),
            'legacy-bookcover' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/bookcover.php',
                    'defaults' => array(
                        'controller' => 'cover',
                        'action'     => 'Show',
                    )
                )
            ),
            'legacy-summonrecord' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Summon/Record',
                    'defaults' => array(
                        'controller' => 'SummonRecord',
                        'action'     => 'Home',
                    )
                )
            ),
            'legacy-worldcatrecord' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/WorldCat/Record',
                    'defaults' => array(
                        'controller' => 'WorldcatRecord',
                        'action'     => 'Home',
                    )
                )
            )
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'browse' => 'VuFind\Controller\Factory::getBrowseController',
            'collection' => 'VuFind\Controller\Factory::getCollectionController',
            'collections' => 'VuFind\Controller\Factory::getCollectionsController',
            'record' => 'VuFind\Controller\Factory::getRecordController',
        ),
        'invokables' => array(
            'ajax' => 'VuFind\Controller\AjaxController',
            'alphabrowse' => 'VuFind\Controller\AlphabrowseController',
            'author' => 'VuFind\Controller\AuthorController',
            'authority' => 'VuFind\Controller\AuthorityController',
            'cart' => 'VuFind\Controller\CartController',
            'combined' => 'VuFind\Controller\CombinedController',
            'confirm' => 'VuFind\Controller\ConfirmController',
            'cover' => 'VuFind\Controller\CoverController',
            'eds' => 'VuFind\Controller\EdsController',
            'edsrecord' => 'VuFind\Controller\EdsrecordController',
            'eit' => 'VuFind\Controller\EITController',
            'eitrecord' => '\VuFind\Controller\EITrecordController',
            'error' => 'VuFind\Controller\ErrorController',
            'feedback' => 'VuFind\Controller\FeedbackController',
            'help' => 'VuFind\Controller\HelpController',
            'hierarchy' => 'VuFind\Controller\HierarchyController',
            'index' => 'VuFind\Controller\IndexController',
            'install' => 'VuFind\Controller\InstallController',
            'libguides' => 'VuFind\Controller\LibGuidesController',
            'missingrecord' => 'VuFind\Controller\MissingrecordController',
            'my-research' => 'VuFind\Controller\MyResearchController',
            'oai' => 'VuFind\Controller\OaiController',
            'pazpar2' => 'VuFind\Controller\Pazpar2Controller',
            'primo' => 'VuFind\Controller\PrimoController',
            'primorecord' => 'VuFind\Controller\PrimorecordController',
            'qrcode' => 'VuFind\Controller\QRCodeController',
            'records' => 'VuFind\Controller\RecordsController',
            'search' => 'VuFind\Controller\SearchController',
            'summon' => 'VuFind\Controller\SummonController',
            'summonrecord' => 'VuFind\Controller\SummonrecordController',
            'tag' => 'VuFind\Controller\TagController',
            'upgrade' => 'VuFind\Controller\UpgradeController',
            'web' => 'VuFind\Controller\WebController',
            'worldcat' => 'VuFind\Controller\WorldcatController',
            'worldcatrecord' => 'VuFind\Controller\WorldcatrecordController',
        ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'holds' => 'VuFind\Controller\Plugin\Factory::getHolds',
            'newitems' => 'VuFind\Controller\Plugin\Factory::getNewItems',
            'ILLRequests' => 'VuFind\Controller\Plugin\Factory::getILLRequests',
            'recaptcha' => 'VuFind\Controller\Plugin\Factory::getRecaptcha',
            'reserves' => 'VuFind\Controller\Plugin\Factory::getReserves',
            'storageRetrievalRequests' => 'VuFind\Controller\Plugin\Factory::getStorageRetrievalRequests',
        ),
        'invokables' => array(
            'db-upgrade' => 'VuFind\Controller\Plugin\DbUpgrade',
            'favorites' => 'VuFind\Controller\Plugin\Favorites',
            'followup' => 'VuFind\Controller\Plugin\Followup',
            'renewals' => 'VuFind\Controller\Plugin\Renewals',
            'result-scroller' => 'VuFind\Controller\Plugin\ResultScroller',
        )
    ),
    'service_manager' => array(
        'allow_override' => true,
        'factories' => array(
            'VuFind\AuthManager' => 'VuFind\Auth\Factory::getManager',
            'VuFind\AuthPluginManager' => 'VuFind\Service\Factory::getAuthPluginManager',
            'VuFind\AutocompletePluginManager' => 'VuFind\Service\Factory::getAutocompletePluginManager',
            'VuFind\CacheManager' => 'VuFind\Service\Factory::getCacheManager',
            'VuFind\Cart' => 'VuFind\Service\Factory::getCart',
            'VuFind\Config' => 'VuFind\Service\Factory::getConfig',
            'VuFind\ContentPluginManager' => 'VuFind\Service\Factory::getContentPluginManager',
            'VuFind\ContentAuthorNotesPluginManager' => 'VuFind\Service\Factory::getContentAuthorNotesPluginManager',
            'VuFind\ContentCoversPluginManager' => 'VuFind\Service\Factory::getContentCoversPluginManager',
            'VuFind\ContentExcerptsPluginManager' => 'VuFind\Service\Factory::getContentExcerptsPluginManager',
            'VuFind\ContentReviewsPluginManager' => 'VuFind\Service\Factory::getContentReviewsPluginManager',
            'VuFind\DateConverter' => 'VuFind\Service\Factory::getDateConverter',
            'VuFind\DbAdapter' => 'VuFind\Service\Factory::getDbAdapter',
            'VuFind\DbAdapterFactory' => 'VuFind\Service\Factory::getDbAdapterFactory',
            'VuFind\DbTablePluginManager' => 'VuFind\Service\Factory::getDbTablePluginManager',
            'VuFind\Export' => 'VuFind\Service\Factory::getExport',
            'VuFind\HierarchyDriverPluginManager' => 'VuFind\Service\Factory::getHierarchyDriverPluginManager',
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
            'VuFind\Logger' => 'VuFind\Service\Factory::getLogger',
            'VuFind\Mailer' => 'VuFind\Mailer\Factory',
            'VuFind\Recaptcha' => 'VuFind\Service\Factory::getRecaptcha',
            'VuFind\RecommendPluginManager' => 'VuFind\Service\Factory::getRecommendPluginManager',
            'VuFind\RecordDriverPluginManager' => 'VuFind\Service\Factory::getRecordDriverPluginManager',
            'VuFind\RecordLoader' => 'VuFind\Service\Factory::getRecordLoader',
            'VuFind\RecordRouter' => 'VuFind\Service\Factory::getRecordRouter',
            'VuFind\RecordStats' => 'VuFind\Service\Factory::getRecordStats',
            'VuFind\RecordTabPluginManager' => 'VuFind\Service\Factory::getRecordTabPluginManager',
            'VuFind\RelatedPluginManager' => 'VuFind\Service\Factory::getRelatedPluginManager',
            'VuFind\ResolverDriverPluginManager' => 'VuFind\Service\Factory::getResolverDriverPluginManager',
            'VuFind\Search\BackendManager' => 'VuFind\Service\Factory::getSearchBackendManager',
            'VuFind\SearchOptionsPluginManager' => 'VuFind\Service\Factory::getSearchOptionsPluginManager',
            'VuFind\SearchParamsPluginManager' => 'VuFind\Service\Factory::getSearchParamsPluginManager',
            'VuFind\SearchResultsPluginManager' => 'VuFind\Service\Factory::getSearchResultsPluginManager',
            'VuFind\SearchSpecsReader' => 'VuFind\Service\Factory::getSearchSpecsReader',
            'VuFind\SearchStats' => 'VuFind\Service\Factory::getSearchStats',
            'VuFind\SessionPluginManager' => 'VuFind\Service\Factory::getSessionPluginManager',
            'VuFind\SMS' => 'VuFind\SMS\Factory',
            'VuFind\Solr\Writer' => 'VuFind\Service\Factory::getSolrWriter',
            'VuFind\StatisticsDriverPluginManager' => 'VuFind\Service\Factory::getStatisticsDriverPluginManager',
            'VuFind\Tags' => 'VuFind\Service\Factory::getTags',
            'VuFind\Translator' => 'VuFind\Service\Factory::getTranslator',
            'VuFind\WorldCatUtils' => 'VuFind\Service\Factory::getWorldCatUtils',
        ),
        'invokables' => array(
            'VuFind\SessionManager' => 'Zend\Session\SessionManager',
            'VuFind\Search'         => 'VuFindSearch\Service',
            'VuFind\Search\Memory'  => 'VuFind\Search\Memory',
        ),
        'initializers' => array(
            'VuFind\ServiceManager\Initializer::initInstance',
        ),
        'aliases' => array(
            'mvctranslator' => 'VuFind\Translator',
            'translator' => 'VuFind\Translator',
        ),
    ),
    'translator' => array(),
    'view_helpers' => array(
        'initializers' => array(
            'VuFind\ServiceManager\Initializer::initZendPlugin',
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => APPLICATION_ENV == 'development',
        'display_exceptions'       => APPLICATION_ENV == 'development',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_path_stack'      => array(),
    ),
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => array(
        // The config reader is a special service manager for loading .ini files:
        'config_reader' => array(
            'abstract_factories' => array('VuFind\Config\PluginFactory'),
        ),
        // PostgreSQL sequence mapping
        'pgsql_seq_mapping'  => array(
            'comments'       => array('id', 'comments_id_seq'),
            'oai_resumption' => array('id', 'oai_resumption_id_seq'),
            'resource'       => array('id', 'resource_id_seq'),
            'resource_tags'  => array('id', 'resource_tags_id_seq'),
            'search'         => array('id', 'search_id_seq'),
            'session'        => array('id', 'session_id_seq'),
            'tags'           => array('id', 'tags_id_seq'),
            'user'           => array('id', 'user_id_seq'),
            'user_list'      => array('id', 'user_list_id_seq'),
            'user_resource'  => array('id', 'user_resource_id_seq')
        ),
        // This section contains service manager configurations for all VuFind
        // pluggable components:
        'plugin_managers' => array(
            'auth' => array(
                'abstract_factories' => array('VuFind\Auth\PluginFactory'),
                'factories' => array(
                    'ils' => 'VuFind\Auth\Factory::getILS',
                    'multiils' => 'VuFind\Auth\Factory::getMultiILS',
                ),
                'invokables' => array(
                    'choiceauth' => 'VuFind\Auth\ChoiceAuth',
                    'database' => 'VuFind\Auth\Database',
                    'ldap' => 'VuFind\Auth\LDAP',
                    'multiauth' => 'VuFind\Auth\MultiAuth',
                    'shibboleth' => 'VuFind\Auth\Shibboleth',
                    'cas' => 'VuFind\Auth\CAS',
                    'sip2' => 'VuFind\Auth\SIP2',
                ),
                'aliases' => array(
                    // for legacy 1.x compatibility
                    'db' => 'Database',
                    'sip' => 'Sip2',
                ),
            ),
            'autocomplete' => array(
                'abstract_factories' => array('VuFind\Autocomplete\PluginFactory'),
                'factories' => array(
                    'solr' => 'VuFind\Autocomplete\Factory::getSolr',
                    'solrauth' => 'VuFind\Autocomplete\Factory::getSolrAuth',
                    'solrcn' => 'VuFind\Autocomplete\Factory::getSolrCN',
                    'solrreserves' => 'VuFind\Autocomplete\Factory::getSolrReserves',
                ),
                'invokables' => array(
                    'none' => 'VuFind\Autocomplete\None',
                    'oclcidentities' => 'VuFind\Autocomplete\OCLCIdentities',
                    'tag' => 'VuFind\Autocomplete\Tag',
                ),
                'aliases' => array(
                    // for legacy 1.x compatibility
                    'noautocomplete' => 'None',
                    'oclcidentitiesautocomplete' => 'OCLCIdentities',
                    'solrautocomplete' => 'Solr',
                    'solrauthautocomplete' => 'SolrAuth',
                    'solrcnautocomplete' => 'SolrCN',
                    'solrreservesautocomplete' => 'SolrReserves',
                    'tagautocomplete' => 'Tag',
                ),
            ),
            'content' => array(
                'factories' => array(
                    'authornotes' => 'VuFind\Content\Factory::getAuthorNotes',
                    'excerpts' => 'VuFind\Content\Factory::getExcerpts',
                    'reviews' => 'VuFind\Content\Factory::getReviews',
                ),
            ),
            'content_authornotes' => array(
                'factories' => array(
                    'syndetics' => 'VuFind\Content\AuthorNotes\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\AuthorNotes\Factory::getSyndeticsPlus',
                ),
            ),
            'content_excerpts' => array(
                'factories' => array(
                    'syndetics' => 'VuFind\Content\Excerpts\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Excerpts\Factory::getSyndeticsPlus',
                ),
            ),
            'content_covers' => array(
                'factories' => array(
                    'amazon' => 'VuFind\Content\Covers\Factory::getAmazon',
                    'booksite' => 'VuFind\Content\Covers\Factory::getBooksite',
                    'contentcafe' => 'VuFind\Content\Covers\Factory::getContentCafe',
                    'syndetics' => 'VuFind\Content\Covers\Factory::getSyndetics',
                ),
                'invokables' => array(
                    'google' => 'VuFind\Content\Covers\Google',
                    'librarything' => 'VuFind\Content\Covers\LibraryThing',
                    'openlibrary' => 'VuFind\Content\Covers\OpenLibrary',
                    'summon' => 'VuFind\Content\Covers\Summon',
                ),
            ),
            'content_reviews' => array(
                'factories' => array(
                    'amazon' => 'VuFind\Content\Reviews\Factory::getAmazon',
                    'amazoneditorial' => 'VuFind\Content\Reviews\Factory::getAmazonEditorial',
                    'booksite' => 'VuFind\Content\Reviews\Factory::getBooksite',
                    'syndetics' => 'VuFind\Content\Reviews\Factory::getSyndetics',
                    'syndeticsplus' => 'VuFind\Content\Reviews\Factory::getSyndeticsPlus',
                ),
                'invokables' => array(
                    'guardian' => 'VuFind\Content\Reviews\Guardian',
                ),
            ),
            'db_table' => array(
                'abstract_factories' => array('VuFind\Db\Table\PluginFactory'),
                'factories' => array(
                    'resource' => 'VuFind\Db\Table\Factory::getResource',
                ),
                'invokables' => array(
                    'changetracker' => 'VuFind\Db\Table\ChangeTracker',
                    'comments' => 'VuFind\Db\Table\Comments',
                    'oairesumption' => 'VuFind\Db\Table\OaiResumption',
                    'resourcetags' => 'VuFind\Db\Table\ResourceTags',
                    'search' => 'VuFind\Db\Table\Search',
                    'session' => 'VuFind\Db\Table\Session',
                    'tags' => 'VuFind\Db\Table\Tags',
                    'user' => 'VuFind\Db\Table\User',
                    'userlist' => 'VuFind\Db\Table\UserList',
                    'userresource' => 'VuFind\Db\Table\UserResource',
                    'userstats' => 'VuFind\Db\Table\UserStats',
                    'userstatsfields' => 'VuFind\Db\Table\UserStatsFields',
                ),
            ),
            'hierarchy_driver' => array(
                'factories' => array(
                    'default' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyDefault',
                    'flat' => 'VuFind\Hierarchy\Driver\Factory::getHierarchyFlat',
                ),
            ),
            'hierarchy_treedatasource' => array(
                'factories' => array(
                    'solr' => 'VuFind\Hierarchy\TreeDataSource\Factory::getSolr',
                ),
                'invokables' => array(
                    'xmlfile' => 'VuFind\Hierarchy\TreeDataSource\XMLFile',
                ),
            ),
            'hierarchy_treerenderer' => array(
                'invokables' => array(
                    'jstree' => 'VuFind\Hierarchy\TreeRenderer\JSTree',
                    'fancytree' => 'VuFind\Hierarchy\TreeRenderer\FancyTree',
                )
            ),
            'ils_driver' => array(
                'abstract_factories' => array('VuFind\ILS\Driver\PluginFactory'),
                'factories' => array(
                    'aleph' => 'VuFind\ILS\Driver\Factory::getAleph',
                    'demo' => 'VuFind\ILS\Driver\Factory::getDemo',
                    'horizon' => 'VuFind\ILS\Driver\Factory::getHorizon',
                    'horizonxmlapi' => 'VuFind\ILS\Driver\Factory::getHorizonXMLAPI',
                    'multibackend' => 'VuFind\ILS\Driver\Factory::getMultiBackend',
                    'noils' => 'VuFind\ILS\Driver\Factory::getNoILS',
                    'unicorn' => 'VuFind\ILS\Driver\Factory::getUnicorn',
                    'voyager' => 'VuFind\ILS\Driver\Factory::getVoyager',
                    'voyagerrestful' => 'VuFind\ILS\Driver\Factory::getVoyagerRestful',
                ),
                'invokables' => array(
                    'amicus' => 'VuFind\ILS\Driver\Amicus',
                    'claviussql' => 'VuFind\ILS\Driver\ClaviusSQL',
                    'daia' => 'VuFind\ILS\Driver\DAIA',
                    'evergreen' => 'VuFind\ILS\Driver\Evergreen',
                    'innovative' => 'VuFind\ILS\Driver\Innovative',
                    'koha' => 'VuFind\ILS\Driver\Koha',
                    'lbs4' => 'VuFind\ILS\Driver\LBS4',
                    'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
                    'pica' => 'VuFind\ILS\Driver\PICA',
                    'polaris' => 'VuFind\ILS\Driver\Polaris',
                    'sample' => 'VuFind\ILS\Driver\Sample',
                    'sierra' => 'VuFind\ILS\Driver\Sierra',
                    'symphony' => 'VuFind\ILS\Driver\Symphony',
                    'virtua' => 'VuFind\ILS\Driver\Virtua',
                    'xcncip' => 'VuFind\ILS\Driver\XCNCIP',
                    'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
                ),
            ),
            'recommend' => array(
                'abstract_factories' => array('VuFind\Recommend\PluginFactory'),
                'factories' => array(
                    'authorfacets' => 'VuFind\Recommend\Factory::getAuthorFacets',
                    'authorinfo' => 'VuFind\Recommend\Factory::getAuthorInfo',
                    'authorityrecommend' => 'VuFind\Recommend\Factory::getAuthorityRecommend',
                    'catalogresults' => 'VuFind\Recommend\Factory::getCatalogResults',
                    'collectionsidefacets' => 'VuFind\Recommend\Factory::getCollectionSideFacets',
                    'dplaterms' => 'VuFind\Recommend\Factory::getDPLATerms',
                    'europeanaresults' => 'VuFind\Recommend\Factory::getEuropeanaResults',
                    'expandfacets' => 'VuFind\Recommend\Factory::getExpandFacets',
                    'favoritefacets' => 'VuFind\Recommend\Factory::getFavoriteFacets',
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
                    'worldcatterms' => 'VuFind\Recommend\Factory::getWorldCatTerms',
                ),
                'invokables' => array(
                    'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
                    'facetcloud' => 'VuFind\Recommend\FacetCloud',
                    'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
                    'openlibrarysubjectsdeferred' => 'VuFind\Recommend\OpenLibrarySubjectsDeferred',
                    'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
                    'resultgooglemapajax' => 'VuFind\Recommend\ResultGoogleMapAjax',
                    'summonbestbetsdeferred' => 'VuFind\Recommend\SummonBestBetsDeferred',
                    'summondatabasesdeferred' => 'VuFind\Recommend\SummonDatabasesDeferred',
                    'summonresultsdeferred' => 'VuFind\Recommend\SummonResultsDeferred',
                    'switchtype' => 'VuFind\Recommend\SwitchType',
                ),
            ),
            'recorddriver' => array(
                'abstract_factories' => array('VuFind\RecordDriver\PluginFactory'),
                'factories' => array(
                    'eds' => 'VuFind\RecordDriver\Factory::getEDS',
                    'eit' => 'VuFind\RecordDriver\Factory::getEIT',
                    'libguides' => 'VuFind\RecordDriver\Factory::getLibGuides',
                    'missing' => 'VuFind\RecordDriver\Factory::getMissing',
                    'pazpar2' => 'VuFind\RecordDriver\Factory::getPazpar2',
                    'primo' => 'VuFind\RecordDriver\Factory::getPrimo',
                    'solrauth' => 'VuFind\RecordDriver\Factory::getSolrAuth',
                    'solrdefault' => 'VuFind\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'VuFind\RecordDriver\Factory::getSolrMarc',
                    'solrreserves' => 'VuFind\RecordDriver\Factory::getSolrReserves',
                    'solrweb' => 'VuFind\RecordDriver\Factory::getSolrWeb',
                    'summon' => 'VuFind\RecordDriver\Factory::getSummon',
                    'worldcat' => 'VuFind\RecordDriver\Factory::getWorldCat',
                ),
            ),
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'factories' => array(
                    'collectionhierarchytree' => 'VuFind\RecordTab\Factory::getCollectionHierarchyTree',
                    'collectionlist' => 'VuFind\RecordTab\Factory::getCollectionList',
                    'excerpt' => 'VuFind\RecordTab\Factory::getExcerpt',
                    'hierarchytree' => 'VuFind\RecordTab\Factory::getHierarchyTree',
                    'holdingsils' => 'VuFind\RecordTab\Factory::getHoldingsILS',
                    'holdingsworldcat' => 'VuFind\RecordTab\Factory::getHoldingsWorldCat',
                    'map' => 'VuFind\RecordTab\Factory::getMap',
                    'preview' => 'VuFind\RecordTab\Factory::getPreview',
                    'reviews' => 'VuFind\RecordTab\Factory::getReviews',
                    'usercomments' => 'VuFind\RecordTab\Factory::getUserComments',
                ),
                'invokables' => array(
                    'description' => 'VuFind\RecordTab\Description',
                    'staffviewarray' => 'VuFind\RecordTab\StaffViewArray',
                    'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                    'toc' => 'VuFind\RecordTab\TOC',
                ),
            ),
            'related' => array(
                'abstract_factories' => array('VuFind\Related\PluginFactory'),
                'factories' => array(
                    'editions' => 'VuFind\Related\Factory::getEditions',
                    'similar' => 'VuFind\Related\Factory::getSimilar',
                    'worldcateditions' => 'VuFind\Related\Factory::getWorldCatEditions',
                    'worldcatsimilar' => 'VuFind\Related\Factory::getWorldCatSimilar',
                ),
            ),
            'resolver_driver' => array(
                'abstract_factories' => array('VuFind\Resolver\Driver\PluginFactory'),
                'factories' => array(
                    '360link' => 'VuFind\Resolver\Driver\Factory::getThreesixtylink',
                    'ezb' => 'VuFind\Resolver\Driver\Factory::getEzb',
                    'sfx' => 'VuFind\Resolver\Driver\Factory::getSfx',
                ),
                'aliases' => array(
                    'threesixtylink' => '360link',
                ),
            ),
            'search_backend' => array(
                'factories' => array(
                    'EDS' => 'VuFind\Search\Factory\EdsBackendFactory',
                    'EIT' => 'VuFind\Search\Factory\EITBackendFactory',
                    'LibGuides' => 'VuFind\Search\Factory\LibGuidesBackendFactory',
                    'Pazpar2' => 'VuFind\Search\Factory\Pazpar2BackendFactory',
                    'Primo' => 'VuFind\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'VuFind\Search\Factory\SolrDefaultBackendFactory',
                    'SolrAuth' => 'VuFind\Search\Factory\SolrAuthBackendFactory',
                    'SolrReserves' => 'VuFind\Search\Factory\SolrReservesBackendFactory',
                    'SolrStats' => 'VuFind\Search\Factory\SolrStatsBackendFactory',
                    'SolrWeb' => 'VuFind\Search\Factory\SolrWebBackendFactory',
                    'Summon' => 'VuFind\Search\Factory\SummonBackendFactory',
                    'WorldCat' => 'VuFind\Search\Factory\WorldCatBackendFactory',
                ),
                'aliases' => array(
                    // Allow Solr core names to be used as aliases for services:
                    'authority' => 'SolrAuth',
                    'biblio' => 'Solr',
                    'reserves' => 'SolrReserves',
                    'stats' => 'SolrStats',
                    // Legacy:
                    'VuFind' => 'Solr',
                )
            ),
            'search_options' => array(
                'abstract_factories' => array('VuFind\Search\Options\PluginFactory'),
                'factories' => array(
                    'eds' => 'VuFind\Search\Options\Factory::getEDS',
                ),
            ),
            'search_params' => array(
                'abstract_factories' => array('VuFind\Search\Params\PluginFactory'),
            ),
            'search_results' => array(
                'abstract_factories' => array('VuFind\Search\Results\PluginFactory'),
                'factories' => array(
                    'solr' => 'VuFind\Search\Results\Factory::getSolr',
                ),
            ),
            'session' => array(
                'abstract_factories' => array('VuFind\Session\PluginFactory'),
                'invokables' => array(
                    'database' => 'VuFind\Session\Database',
                    'file' => 'VuFind\Session\File',
                    'memcache' => 'VuFind\Session\Memcache',
                ),
                'aliases' => array(
                    // for legacy 1.x compatibility
                    'filesession' => 'File',
                    'memcachesession' => 'Memcache',
                    'mysqlsession' => 'Database',
                ),
            ),
            'statistics_driver' => array(
                'abstract_factories' => array('VuFind\Statistics\Driver\PluginFactory'),
                'factories' => array(
                    'file' => 'VuFind\Statistics\Driver\Factory::getFile',
                    'solr' => 'VuFind\Statistics\Driver\Factory::getSolr',
                ),
                'invokables' => array(
                    'db' => 'VuFind\Statistics\Driver\Db',
                ),
                'aliases' => array(
                    'database' => 'db',
                ),
            ),
        ),
        // This section behaves just like recorddriver_tabs below, but is used for
        // the collection module instead of the standard record view.
        'recorddriver_collection_tabs' => array(
            'VuFind\RecordDriver\AbstractBase' => array(
                'tabs' => array(
                    'CollectionList' => 'CollectionList',
                    'HierarchyTree' => 'CollectionHierarchyTree',
                ),
                'defaultTab' => null,
            ),
        ),
        // This section controls which tabs are used for which record driver classes.
        // Each sub-array is a map from a tab name (as used in a record URL) to a tab
        // service (found in recordtab_plugin_manager, below).  If a particular record
        // driver is not defined here, it will inherit configuration from a configured
        // parent class.  The defaultTab setting may be used to specify the default
        // active tab; if null, the value from the relevant .ini file will be used.
        'recorddriver_tabs' => array(
            'VuFind\RecordDriver\EDS' => array(
                'tabs' => array(
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\Pazpar2' => array(
                'tabs' => array (
                    'Details' => 'StaffViewMARC',
                 ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\Primo' => array(
                'tabs' => array(
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\SolrAuth' => array(
                'tabs' => array (
                    'Details' => 'StaffViewMARC',
                 ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\SolrDefault' => array(
                'tabs' => array (
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\SolrMarc' => array(
                'tabs' => array(
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\Summon' => array(
                'tabs' => array(
                    'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'VuFind\RecordDriver\WorldCat' => array(
                'tabs' => array (
                    'Holdings' => 'HoldingsWorldCat', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
        ),
    ),
);

// Define record view routes -- route name => controller
$recordRoutes = array(
    'record' => 'Record',
    'collection' => 'Collection',
    'edsrecord' => 'EdsRecord',
    'eitrecord' => 'EITRecord',
    'missingrecord' => 'MissingRecord',
    'primorecord' => 'PrimoRecord',
    'solrauthrecord' => 'Authority',
    'summonrecord' => 'SummonRecord',
    'worldcatrecord' => 'WorldcatRecord'
);
// Record sub-routes are generally used to access tab plug-ins, but a few
// URLs are hard-coded to specific actions; this array lists those actions.
$nonTabRecordActions = array(
    'AddComment', 'DeleteComment', 'AddTag', 'Save', 'Email', 'SMS', 'Cite',
    'Export', 'RDF', 'Hold', 'BlockedHold', 'Home', 'StorageRetrievalRequest', 'AjaxTab',
    'BlockedStorageRetrievalRequest', 'ILLRequest', 'BlockedILLRequest', 'PDF',
);

// Define list-related routes -- route name => MyResearch action
$listRoutes = array('userList' => 'MyList', 'editList' => 'EditList');

// Define static routes -- Controller/Action strings
$staticRoutes = array(
    'Alphabrowse/Home', 'Author/Home', 'Author/Search',
    'Authority/Home', 'Authority/Record', 'Authority/Search',
    'Browse/Author', 'Browse/Dewey', 'Browse/Era', 'Browse/Genre', 'Browse/Home',
    'Browse/LCC', 'Browse/Region', 'Browse/Tag', 'Browse/Topic',
    'Cart/doExport', 'Cart/Email', 'Cart/Export', 'Cart/Home', 'Cart/MyResearchBulk',
    'Cart/Save', 'Collections/ByTitle', 'Collections/Home',
    'Combined/Home', 'Combined/Results', 'Combined/SearchBox', 'Confirm/Confirm',
    'Cover/Show', 'Cover/Unavailable',
    'EDS/Advanced', 'EDS/Home', 'EDS/Search',
    'EIT/Advanced', 'EIT/Home', 'EIT/Search',
    'Error/Unavailable', 'Feedback/Email', 'Feedback/Home', 'Help/Home',
    'Install/Done', 'Install/FixBasicConfig', 'Install/FixCache',
    'Install/FixDatabase', 'Install/FixDependencies', 'Install/FixILS',
    'Install/FixSecurity', 'Install/FixSolr', 'Install/Home',
    'Install/PerformSecurityFix', 'Install/ShowSQL',
    'LibGuides/Home', 'LibGuides/Results',
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
    'Search/Advanced', 'Search/Email', 'Search/History', 'Search/Home',
    'Search/NewItem', 'Search/OpenSearch', 'Search/Reserves', 'Search/Results',
    'Search/Suggest',
    'Summon/Advanced', 'Summon/Home', 'Summon/Search',
    'Tag/Home',
    'Upgrade/Home', 'Upgrade/FixAnonymousTags', 'Upgrade/FixDuplicateTags',
    'Upgrade/FixConfig', 'Upgrade/FixDatabase', 'Upgrade/FixMetadata',
    'Upgrade/GetDBCredentials', 'Upgrade/GetDbEncodingPreference',
    'Upgrade/GetSourceDir', 'Upgrade/GetSourceVersion', 'Upgrade/Reset',
    'Upgrade/ShowSQL',
    'Web/Home', 'Web/Results',
    'Worldcat/Advanced', 'Worldcat/Home', 'Worldcat/Search'
);

// Build record routes
foreach ($recordRoutes as $routeBase => $controller) {
    // catch-all "tab" route:
    $config['router']['routes'][$routeBase] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/' . $controller . '/[:id[/:tab]]',
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => $controller,
                'action'     => 'Home',
            )
        )
    );
    // special non-tab actions that each need their own route:
    foreach ($nonTabRecordActions as $action) {
        $config['router']['routes'][$routeBase . '-' . strtolower($action)] = array(
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => array(
                'route'    => '/' . $controller . '/[:id]/' . $action,
                'constraints' => array(
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ),
                'defaults' => array(
                    'controller' => $controller,
                    'action'     => $action,
                )
            )
        );
    }
}

// Build list routes
foreach ($listRoutes as $routeName => $action) {
    $config['router']['routes'][$routeName] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/MyResearch/' . $action . '/[:id]',
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => 'MyResearch',
                'action'     => $action,
            )
        )
    );
}

// Build static routes
foreach ($staticRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/' . $route,
            'defaults' => array(
                'controller' => $controller,
                'action'     => $action,
            )
        )
    );
}

// Add the home route last
$config['router']['routes']['home'] = array(
    'type' => 'Zend\Mvc\Router\Http\Literal',
    'options' => array(
        'route'    => '/',
        'defaults' => array(
            'controller' => 'index',
            'action'     => 'Home',
        )
    )
);

return $config;
