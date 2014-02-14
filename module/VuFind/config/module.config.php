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
            'browse' => array('VuFind\Controller\Factory', 'getBrowseController'),
            'collection' => array('VuFind\Controller\Factory', 'getCollectionController'),
            'collections' => array('VuFind\Controller\Factory', 'getCollectionsController'),
            'record' => array('VuFind\Controller\Factory', 'getRecordController'),
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
            'error' => 'VuFind\Controller\ErrorController',
            'feedback' => 'VuFind\Controller\FeedbackController',
            'help' => 'VuFind\Controller\HelpController',
            'hierarchy' => 'VuFind\Controller\HierarchyController',
            'index' => 'VuFind\Controller\IndexController',
            'install' => 'VuFind\Controller\InstallController',
            'missingrecord' => 'VuFind\Controller\MissingrecordController',
            'my-research' => 'VuFind\Controller\MyResearchController',
            'oai' => 'VuFind\Controller\OaiController',
            'qrcode' => 'VuFind\Controller\QRCodeController',
            'pazpar2' => 'VuFind\Controller\Pazpar2Controller',
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
            'holds' => array('VuFind\Controller\Plugin\Factory', 'getHolds'),
            'storageRetrievalRequests' => array('VuFind\Controller\Plugin\Factory', 'getStorageRetrievalRequests'),
            'reserves' => array('VuFind\Controller\Plugin\Factory', 'getReserves'),
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
            'VuFind\AuthManager' => array('VuFind\Auth\Factory', 'getManager'),
            'VuFind\CacheManager' => array('VuFind\Service\Factory', 'getCacheManager'),
            'VuFind\Cart' => array('VuFind\Service\Factory', 'getCart'),
            'VuFind\DateConverter' => array('VuFind\Service\Factory', 'getDateConverter'),
            'VuFind\DbAdapter' => array('VuFind\Service\Factory', 'getDbAdapter'),
            'VuFind\DbAdapterFactory' => array('VuFind\Service\Factory', 'getDbAdapterFactory'),
            'VuFind\Export' => array('VuFind\Service\Factory', 'getExport'),
            'VuFind\Http' => array('VuFind\Service\Factory', 'getHttp'),
            'VuFind\HMAC' => array('VuFind\Service\Factory', 'getHMAC'),
            'VuFind\ILSConnection' => array('VuFind\Service\Factory', 'getILSConnection'),
            'VuFind\ILSHoldLogic' => array('VuFind\Service\Factory', 'getILSHoldLogic'),
            'VuFind\ILSHoldSettings' => array('VuFind\Service\Factory', 'getILSHoldSettings'),
            'VuFind\ILSTitleHoldLogic' => array('VuFind\Service\Factory', 'getILSTitleHoldLogic'),
            'VuFind\Logger' => array('VuFind\Service\Factory', 'getLogger'),
            'VuFind\Mailer' => 'VuFind\Mailer\Factory',
            'VuFind\RecordLoader' => array('VuFind\Service\Factory', 'getRecordLoader'),
            'VuFind\RecordRouter' => array('VuFind\Service\Factory', 'getRecordRouter'),
            'VuFind\RecordStats' => array('VuFind\Service\Factory', 'getRecordStats'),
            'VuFind\Search\BackendManager' => array('VuFind\Service\Factory', 'getSearchBackendManager'),
            'VuFind\SearchSpecsReader' => array('VuFind\Service\Factory', 'getSearchSpecsReader'),
            'VuFind\SearchStats' => array('VuFind\Service\Factory', 'getSearchStats'),
            'VuFind\SMS' => 'VuFind\SMS\Factory',
            'VuFind\Solr\Writer' => array('VuFind\Service\Factory', 'getSolrWriter'),
            'VuFind\Tags' => array('VuFind\Service\Factory', 'getTags'),
            'VuFind\Translator' => array('VuFind\Service\Factory', 'getTranslator'),
            'VuFind\WorldCatUtils' => array('VuFind\Service\Factory', 'getWorldCatUtils'),
        ),
        'invokables' => array(
            'VuFind\SessionManager' => 'Zend\Session\SessionManager',
            'VuFind\Search'         => 'VuFindSearch\Service',
            'VuFind\Search\Memory'  => 'VuFind\Search\Memory',
        ),
        'initializers' => array(
            array('VuFind\ServiceManager\Initializer', 'initInstance'),
        ),
        'aliases' => array(
            'mvctranslator' => 'VuFind\Translator',
            'translator' => 'VuFind\Translator',
        ),
    ),
    'translator' => array(),
    'view_helpers' => array(
        'initializers' => array(
            array('VuFind\ServiceManager\Initializer', 'initZendPlugin'),
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
                    'ils' => array('VuFind\Auth\Factory', 'getILS'),
                    'multiils' => array('VuFind\Auth\Factory', 'getMultiILS'),
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
                    'solr' => array('VuFind\Autocomplete\Factory', 'getSolr'),
                    'solrauth' => array('VuFind\Autocomplete\Factory', 'getSolrAuth'),
                    'solrcn' => array('VuFind\Autocomplete\Factory', 'getSolrCN'),
                    'solrreserves' => array('VuFind\Autocomplete\Factory', 'getSolrReserves'),
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
            'db_table' => array(
                'abstract_factories' => array('VuFind\Db\Table\PluginFactory'),
                'factories' => array(
                    'resource' => array('VuFind\Db\Table\Factory', 'getResource'),
                ),
                'invokables' => array(
                    'changetracker' => 'VuFind\Db\Table\ChangeTracker',
                    'comments' => 'VuFind\Db\Table\Comments',
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
                    'default' => array('VuFind\Hierarchy\Driver\Factory', 'getHierarchyDefault'),
                    'flat' => array('VuFind\Hierarchy\Driver\Factory', 'getHierarchyFlat'),
                ),
            ),
            'hierarchy_treedatasource' => array(
                'factories' => array(
                    'solr' => array('VuFind\Hierarchy\TreeDataSource\Factory', 'getSolr'),
                ),
                'invokables' => array(
                    'xmlfile' => 'VuFind\Hierarchy\TreeDataSource\XMLFile',
                ),
            ),
            'hierarchy_treerenderer' => array(
                'invokables' => array(
                    'jstree' => 'VuFind\Hierarchy\TreeRenderer\JSTree',
                )
            ),
            'ils_driver' => array(
                'abstract_factories' => array('VuFind\ILS\Driver\PluginFactory'),
                'factories' => array(
                    'aleph' => array('VuFind\ILS\Driver\Factory', 'getAleph'),
                    'demo' => array('VuFind\ILS\Driver\Factory', 'getDemo'),
                    'horizon' => array('VuFind\ILS\Driver\Factory', 'getHorizon'),
                    'horizonxmlapi' => array('VuFind\ILS\Driver\Factory', 'getHorizonXMLAPI'),
                    'multibackend' => array('VuFind\ILS\Driver\Factory', 'getMultiBackend'),
                    'noils' => array('VuFind\ILS\Driver\Factory', 'getNoILS'),
                    'unicorn' => array('VuFind\ILS\Driver\Factory', 'getUnicorn'),
                    'voyager' => array('VuFind\ILS\Driver\Factory', 'getVoyager'),
                    'voyagerrestful' => array('VuFind\ILS\Driver\Factory', 'getVoyagerRestful'),
                ),
                'invokables' => array(
                    'amicus' => 'VuFind\ILS\Driver\Amicus',
                    'daia' => 'VuFind\ILS\Driver\DAIA',
                    'evergreen' => 'VuFind\ILS\Driver\Evergreen',
                    'innovative' => 'VuFind\ILS\Driver\Innovative',
                    'koha' => 'VuFind\ILS\Driver\Koha',
                    'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
                    'pica' => 'VuFind\ILS\Driver\PICA',
                    'polaris' => 'VuFind\ILS\Driver\Polaris',
                    'sample' => 'VuFind\ILS\Driver\Sample',
                    'symphony' => 'VuFind\ILS\Driver\Symphony',
                    'virtua' => 'VuFind\ILS\Driver\Virtua',
                    'xcncip' => 'VuFind\ILS\Driver\XCNCIP',
                    'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
                ),
            ),
            'recommend' => array(
                'abstract_factories' => array('VuFind\Recommend\PluginFactory'),
                'factories' => array(
                    'authorfacets' => array('VuFind\Recommend\Factory', 'getAuthorFacets'),
                    'authorinfo' => array('VuFind\Recommend\Factory', 'getAuthorInfo'),
                    'authorityrecommend' => array('VuFind\Recommend\Factory', 'getAuthorityRecommend'),
                    'catalogresults' => array('VuFind\Recommend\Factory', 'getCatalogResults'),
                    'collectionsidefacets' => array('VuFind\Recommend\Factory', 'getCollectionSideFacets'),
                    'europeanaresults' => array('VuFind\Recommend\Factory', 'getEuropeanaResults'),
                    'expandfacets' => array('VuFind\Recommend\Factory', 'getExpandFacets'),
                    'favoritefacets' => array('VuFind\Recommend\Factory', 'getFavoriteFacets'),
                    'sidefacets' => array('VuFind\Recommend\Factory', 'getSideFacets'),
                    'summonbestbets' => array('VuFind\Recommend\Factory', 'getSummonBestBets'),
                    'summondatabases' => array('VuFind\Recommend\Factory', 'getSummonDatabases'),
                    'summonresults' => array('VuFind\Recommend\Factory', 'getSummonResults'),
                    'summontopics' => array('VuFind\Recommend\Factory', 'getSummonTopics'),
                    'switchquery' => array('VuFind\Recommend\Factory', 'getSwitchQuery'),
                    'topfacets' => array('VuFind\Recommend\Factory', 'getTopFacets'),
                    'webresults' => array('VuFind\Recommend\Factory', 'getWebResults'),
                    'worldcatidentities' => array('VuFind\Recommend\Factory', 'getWorldCatIdentities'),
                    'worldcatterms' => array('VuFind\Recommend\Factory', 'getWorldCatTerms'),
                ),
                'invokables' => array(
                    'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
                    'facetcloud' => 'VuFind\Recommend\FacetCloud',
                    'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
                    'openlibrarysubjectsdeferred' => 'VuFind\Recommend\OpenLibrarySubjectsDeferred',
                    'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
                    'resultgooglemapajax' => 'VuFind\Recommend\ResultGoogleMapAjax',
                    'summonresultsdeferred' => 'VuFind\Recommend\SummonResultsDeferred',
                    'switchtype' => 'VuFind\Recommend\SwitchType',
                ),
            ),
            'recorddriver' => array(
                'abstract_factories' => array('VuFind\RecordDriver\PluginFactory'),
                'factories' => array(
                    'missing' => array('VuFind\RecordDriver\Factory', 'getMissing'),
                    'solrauth' => array('VuFind\RecordDriver\Factory', 'getSolrAuth'),
                    'pazpar2' => array('VuFind\RecordDriver\Factory', 'getPazpar2'),
                    'solrdefault' => array('VuFind\RecordDriver\Factory', 'getSolrDefault'),
                    'solrmarc' => array('VuFind\RecordDriver\Factory', 'getSolrMarc'),
                    'solrreserves' => array('VuFind\RecordDriver\Factory', 'getSolrReserves'),
                    'solrweb' => array('VuFind\RecordDriver\Factory', 'getSolrWeb'),
                    'summon' => array('VuFind\RecordDriver\Factory', 'getSummon'),
                    'worldcat' => array('VuFind\RecordDriver\Factory', 'getWorldCat'),
                ),
            ),
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'factories' => array(
                    'collectionhierarchytree' => array('VuFind\RecordTab\Factory', 'getCollectionHierarchyTree'),
                    'collectionlist' => array('VuFind\RecordTab\Factory', 'getCollectionList'),
                    'excerpt' => array('VuFind\RecordTab\Factory', 'getExcerpt'),
                    'hierarchytree' => array('VuFind\RecordTab\Factory', 'getHierarchyTree'),
                    'holdingsils' => array('VuFind\RecordTab\Factory', 'getHoldingsILS'),
                    'map' => array('VuFind\RecordTab\Factory', 'getMap'),
                    'reviews' => array('VuFind\RecordTab\Factory', 'getReviews'),
                ),
                'invokables' => array(
                    'description' => 'VuFind\RecordTab\Description',
                    'holdingsworldcat' => 'VuFind\RecordTab\HoldingsWorldCat',
                    'staffviewarray' => 'VuFind\RecordTab\StaffViewArray',
                    'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                    'toc' => 'VuFind\RecordTab\TOC',
                    'usercomments' => 'VuFind\RecordTab\UserComments',
                ),
            ),
            'related' => array(
                'abstract_factories' => array('VuFind\Related\PluginFactory'),
                'factories' => array(
                    'editions' => function ($sm) {
                        return new \VuFind\Related\Editions(
                            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
                            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
                        );
                    },
                    'similar' => function ($sm) {
                        return new \VuFind\Related\Similar(
                            $sm->getServiceLocator()->get('VuFind\Search')
                        );
                    },
                    'worldcateditions' => function ($sm) {
                        return new \VuFind\Related\WorldCatEditions(
                            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
                            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
                        );
                    },
                    'worldcatsimilar' => function ($sm) {
                        return new \VuFind\Related\WorldCatSimilar(
                            $sm->getServiceLocator()->get('VuFind\Search')
                        );
                    },
                ),
            ),
            'resolver_driver' => array(
                'abstract_factories' => array('VuFind\Resolver\Driver\PluginFactory'),
                'factories' => array(
                    '360link' => function ($sm) {
                        return new \VuFind\Resolver\Driver\Threesixtylink(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config')->OpenURL->url,
                            $sm->getServiceLocator()->get('VuFind\Http')
                                ->createClient()
                        );
                    },
                    'ezb' => function ($sm) {
                        return new \VuFind\Resolver\Driver\Ezb(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config')->OpenURL->url,
                            $sm->getServiceLocator()->get('VuFind\Http')
                                ->createClient()
                        );
                    },
                    'sfx' => function ($sm) {
                        return new \VuFind\Resolver\Driver\Sfx(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config')->OpenURL->url,
                            $sm->getServiceLocator()->get('VuFind\Http')
                                ->createClient()
                        );
                    },
                ),
                'aliases' => array(
                    'threesixtylink' => '360link',
                ),
            ),
            'search_backend' => array(
                'factories' => array(
                    'Pazpar2' => 'VuFind\Search\Factory\Pazpar2BackendFactory',
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
            ),
            'search_params' => array(
                'abstract_factories' => array('VuFind\Search\Params\PluginFactory'),
            ),
            'search_results' => array(
                'abstract_factories' => array('VuFind\Search\Results\PluginFactory'),
                'factories' => array(
                    'solr' => function ($sm) {
                        $factory = new \VuFind\Search\Results\PluginFactory();
                        $solr = $factory->createServiceWithName($sm, 'solr', 'Solr');
                        $config = $sm->getServiceLocator()
                            ->get('VuFind\Config')->get('config');
                        $spellConfig = isset($config->Spelling)
                            ? $config->Spelling : null;
                        $solr->setSpellingProcessor(
                            new \VuFind\Search\Solr\SpellingProcessor($spellConfig)
                        );
                        return $solr;
                    },
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
                    'file' => function ($sm) {
                        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                        $folder = isset($config->Statistics->file)
                            ? $config->Statistics->file : sys_get_temp_dir();
                        return new \VuFind\Statistics\Driver\File($folder);
                    },
                    'solr' => function ($sm) {
                        return new \VuFind\Statistics\Driver\Solr(
                            $sm->getServiceLocator()->get('VuFind\Solr\Writer'),
                            $sm->getServiceLocator()->get('VuFind\Search\BackendManager')->get('SolrStats')
                        );
                    },
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
            'VuFind\RecordDriver\Pazpar2' => array(
                'tabs' => array (
                    'Details' => 'StaffViewMARC',
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
    'missingrecord' => 'MissingRecord',
    'solrauthrecord' => 'Authority',
    'summonrecord' => 'SummonRecord',
    'worldcatrecord' => 'WorldcatRecord'
);
// Record sub-routes are generally used to access tab plug-ins, but a few
// URLs are hard-coded to specific actions; this array lists those actions.
$nonTabRecordActions = array(
    'AddComment', 'DeleteComment', 'AddTag', 'Save', 'Email', 'SMS', 'Cite',
    'Export', 'RDF', 'Hold', 'BlockedHold', 'Home', 'StorageRetrievalRequest',
    'BlockedStorageRetrievalRequest'
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
    'Cover/Show', 'Cover/Unavailable', 'Error/Unavailable',
    'Feedback/Email', 'Feedback/Home', 'Help/Home',
    'Install/Done', 'Install/FixBasicConfig', 'Install/FixCache',
    'Install/FixDatabase', 'Install/FixDependencies', 'Install/FixILS',
    'Install/FixSecurity', 'Install/FixSolr', 'Install/Home',
    'Install/PerformSecurityFix', 'Install/ShowSQL',
    'MyResearch/Account', 'MyResearch/CheckedOut', 'MyResearch/Delete',
    'MyResearch/DeleteList', 'MyResearch/Edit', 'MyResearch/Email',
    'MyResearch/Favorites', 'MyResearch/Fines',
    'MyResearch/Holds', 'MyResearch/Home', 'MyResearch/Logout', 'MyResearch/Profile',
    'MyResearch/SaveSearch', 'MyResearch/StorageRetrievalRequests',
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
