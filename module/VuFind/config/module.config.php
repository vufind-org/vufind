<?php
namespace VuFind\Module\Config;

$config = array(
    'router' => array(
        'routes' => array(
            'default' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/[:controller[/:action]]',
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
            // TODO: can we address all three default route situations (install, install/, install/home) with
            // a single route definition?  Currently, we need this case to address trailing slash + missing action.
            'default-without-action' => array(
                'type'    => 'Zend\Mvc\Router\Http\Regex',
                'options' => array(
                    'regex'    => '/(?<controller>[a-zA-Z][a-zA-Z0-9_-]*)(/?)',
                    'defaults' => array(
                        'controller' => 'index',
                        'action'     => 'Home',
                    ),
                    'spec' => '/%controller%',
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
    'auth_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Auth\PluginFactory'),
        'factories' => array(
            'ils' => function ($sm) {
                return new \VuFind\Auth\ILS(
                    $sm->getServiceLocator()->get('VuFind\ILSConnection')
                );
            },
        ),
        'invokables' => array(
            'database' => 'VuFind\Auth\Database',
            'ldap' => 'VuFind\Auth\LDAP',
            'multiauth' => 'VuFind\Auth\MultiAuth',
            'shibboleth' => 'VuFind\Auth\Shibboleth',
            'sip2' => 'VuFind\Auth\SIP2',
        ),
        'aliases' => array(
            // for legacy 1.x compatibility
            'db' => 'Database',
            'sip' => 'Sip2',
        ),
    ),
    'autocomplete_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Autocomplete\PluginFactory'),
        'invokables' => array(
            'none' => 'VuFind\Autocomplete\None',
            'oclcidentities' => 'VuFind\Autocomplete\OCLCIdentities',
            'solr' => 'VuFind\Autocomplete\Solr',
            'solrauth' => 'VuFind\Autocomplete\SolrAuth',
            'solrcn' => 'VuFind\Autocomplete\SolrCN',
            'solrreserves' => 'VuFind\Autocomplete\SolrReserves',
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
    'controllers' => array(
        'invokables' => array(
            'admin' => 'VuFind\Controller\AdminController',
            'ajax' => 'VuFind\Controller\AjaxController',
            'alphabrowse' => 'VuFind\Controller\AlphabrowseController',
            'author' => 'VuFind\Controller\AuthorController',
            'authority' => 'VuFind\Controller\AuthorityController',
            'browse' => 'VuFind\Controller\BrowseController',
            'cart' => 'VuFind\Controller\CartController',
            'cover' => 'VuFind\Controller\CoverController',
            'error' => 'VuFind\Controller\ErrorController',
            'help' => 'VuFind\Controller\HelpController',
            'index' => 'VuFind\Controller\IndexController',
            'install' => 'VuFind\Controller\InstallController',
            'missingrecord' => 'VuFind\Controller\MissingrecordController',
            'my-research' => 'VuFind\Controller\MyResearchController',
            'oai' => 'VuFind\Controller\OaiController',
            'record' => 'VuFind\Controller\RecordController',
            'records' => 'VuFind\Controller\RecordsController',
            'search' => 'VuFind\Controller\SearchController',
            'summon' => 'VuFind\Controller\SummonController',
            'summonrecord' => 'VuFind\Controller\SummonrecordController',
            'tag' => 'VuFind\Controller\TagController',
            'upgrade' => 'VuFind\Controller\UpgradeController',
            'vudl' => 'VuFind\Controller\VudlController',
            'worldcat' => 'VuFind\Controller\WorldcatController',
            'worldcatrecord' => 'VuFind\Controller\WorldcatrecordController',
        ),
    ),
    'controller_plugins' => array(
        'invokables' => array(
            'db-upgrade' => 'VuFind\Controller\Plugin\DbUpgrade',
            'favorites' => 'VuFind\Controller\Plugin\Favorites',
            'followup' => 'VuFind\Controller\Plugin\Followup',
            'holds' => 'VuFind\Controller\Plugin\Holds',
            'renewals' => 'VuFind\Controller\Plugin\Renewals',
            'reserves' => 'VuFind\Controller\Plugin\Reserves',
            'result-scroller' => 'VuFind\Controller\Plugin\ResultScroller',
        )
    ),
    'db_table_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Db\Table\PluginFactory'),
        'invokables' => array(
            'changetracker' => 'VuFind\Db\Table\ChangeTracker',
            'comments' => 'VuFind\Db\Table\Comments',
            'resource' => 'VuFind\Db\Table\Resource',
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
    'ils_driver_plugin_manager' => array(
        'abstract_factories' => array('VuFind\ILS\Driver\PluginFactory'),
        'invokables' => array(
            'aleph' => 'VuFind\ILS\Driver\Aleph',
            'amicus' => 'VuFind\ILS\Driver\Amicus',
            'daia' => 'VuFind\ILS\Driver\DAIA',
            'demo' => 'VuFind\ILS\Driver\Demo',
            'evergreen' => 'VuFind\ILS\Driver\Evergreen',
            'horizon' => 'VuFind\ILS\Driver\Horizon',
            'horizonxmlapi' => 'VuFind\ILS\Driver\HorizonXMLAPI',
            'innovative' => 'VuFind\ILS\Driver\Innovative',
            'koha' => 'VuFind\ILS\Driver\Koha',
            'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
            'noils' => 'VuFind\ILS\Driver\NoILS',
            'pica' => 'VuFind\ILS\Driver\PICA',
            'sample' => 'VuFind\ILS\Driver\Sample',
            'symphony' => 'VuFind\ILS\Driver\Symphony',
            'unicorn' => 'VuFind\ILS\Driver\Unicorn',
            'virtua' => 'VuFind\ILS\Driver\Virtua',
            'voyager' => 'VuFind\ILS\Driver\Voyager',
            'voyagerrestful' => 'VuFind\ILS\Driver\VoyagerRestful',
            'xcncip' => 'VuFind\ILS\Driver\XCNCIP',
            'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
        ),
    ),
    'recommend_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Recommend\PluginFactory'),
        'invokables' => array(
            'authorfacets' => 'VuFind\Recommend\AuthorFacets',
            'authorinfo' => 'VuFind\Recommend\AuthorInfo',
            'authorityrecommend' => 'VuFind\Recommend\AuthorityRecommend',
            'catalogresults' => 'VuFind\Recommend\CatalogResults',
            'europeanaresults' => 'VuFind\Recommend\EuropeanaResults',
            'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
            'expandfacets' => 'VuFind\Recommend\ExpandFacets',
            'favoritefacets' => 'VuFind\Recommend\FavoriteFacets',
            'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
            'openlibrarysubjectsdeferred' => 'VuFind\Recommend\OpenLibrarySubjectsDeferred',
            'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
            'resultgooglemapajax' => 'VuFind\Recommend\ResultGoogleMapAjax',
            'sidefacets' => 'VuFind\Recommend\SideFacets',
            'summondatabases' => 'VuFind\Recommend\SummonDatabases',
            'summonresults' => 'VuFind\Recommend\SummonResults',
            'switchtype' => 'VuFind\Recommend\SwitchType',
            'topfacets' => 'VuFind\Recommend\TopFacets',
            'worldcatidentities' => 'VuFind\Recommend\WorldCatIdentities',
            'worldcatterms' => 'VuFind\Recommend\WorldCatTerms',
        ),
    ),
    'recorddriver_plugin_manager' => array(
        'abstract_factories' => array('VuFind\RecordDriver\PluginFactory'),
        'invokables' => array(
            'missing' => 'VuFind\RecordDriver\Missing',
            'solrauth' => 'VuFind\RecordDriver\SolrAuth',
            'solrdefault' => 'VuFind\RecordDriver\SolrDefault',
            'solrmarc' => 'VuFind\RecordDriver\SolrMarc',
            'solrreserves' => 'VuFind\RecordDriver\SolrReserves',
            'solrvudl' => 'VuFind\RecordDriver\SolrVudl',
            'summon' => 'VuFind\RecordDriver\Summon',
            'worldcat' => 'VuFind\RecordDriver\WorldCat',
        ),
    ),
    'related_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Related\PluginFactory'),
        'invokables' => array(
            'editions' => 'VuFind\Related\Editions',
            'similar' => 'VuFind\Related\Similar',
            'worldcateditions' => 'VuFind\Related\WorldCatEditions',
            'worldcatsimilar' => 'VuFind\Related\WorldCatSimilar',
        ),
    ),
    'resolver_driver_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Resolver\Driver\PluginFactory'),
        'invokables' => array(
            '360link' => 'VuFind\Resolver\Driver\Threesixtylink',
            'ezb' => 'VuFind\Resolver\Driver\Ezb',
            'sfx' => 'VuFind\Resolver\Driver\Sfx',
        ),
        'aliases' => array(
            'threesixtylink' => '360link',
        ),
    ),
    'search_manager' => array(
        'default_namespace' => 'VuFind\Search',
        'namespaces_by_id' => array(
            'EmptySet' => 'VuFind\Search\EmptySet',
            'Favorites' => 'VuFind\Search\Favorites',
            'MixedList' => 'VuFind\Search\MixedList',
            'Solr' => 'VuFind\Search\Solr',
            'SolrAuth' => 'VuFind\Search\SolrAuth',
            'SolrAuthor' => 'VuFind\Search\SolrAuthor',
            'SolrAuthorFacets' => 'VuFind\Search\SolrAuthorFacets',
            'SolrReserves' => 'VuFind\Search\SolrReserves',
            'Summon' => 'VuFind\Search\Summon',
            'Tags' => 'VuFind\Search\Tags',
            'WorldCat' => 'VuFind\Search\WorldCat',
        ),
        'aliases' => array(
            // Alias to account for "source" field in resource table,
            // which uses "VuFind" for records from Solr index.
            'VuFind' => 'Solr'
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'VuFind\DbAdapter' => function ($sm) {
                return \VuFind\Db\AdapterFactory::getAdapter();
            },
            'VuFind\ILSConnection' => function ($sm) {
                $catalog = new \VuFind\ILS\Connection();
                return $catalog
                    ->setConfig(\VuFind\Config\Reader::getConfig()->Catalog)
                    ->initWithDriverManager(
                        $sm->get('VuFind\ILSDriverPluginManager')
                    );
            },
            'VuFind\Logger' => function ($sm) {
                $logger = new \VuFind\Log\Logger();
                $logger->setServiceLocator($sm);
                $logger->setConfig(\VuFind\Config\Reader::getConfig());
                return $logger;
            },
            'VuFind\Translator' => function ($sm) {
                $factory = new \Zend\I18n\Translator\TranslatorServiceFactory();
                $translator = $factory->createService($sm);

                // Set up the ExtendedIni plugin:
                $translator->getPluginManager()->setService(
                    'extendedini', new \VuFind\I18n\Translator\Loader\ExtendedIni()
                );

                // Set up language caching for better performance:
                $translator->setCache(
                    $sm->get('VuFind\CacheManager')->getCache('language')
                );

                return $translator;
            },
        ),
        'invokables' => array(
            'VuFind\AuthManager' => 'VuFind\Auth\Manager',
            'VuFind\Cart' => 'VuFind\Cart',
            'VuFind\CacheManager' => 'VuFind\Cache\Manager',
            'VuFind\Mailer' => 'VuFind\Mailer',
            'VuFind\RecordLoader' => 'VuFind\Record\Loader',
            'VuFind\SearchSpecsReader' => 'VuFind\Config\SearchSpecsReader',
            'sessionmanager' => 'Zend\Session\SessionManager',
            'sms' => 'VuFind\Mailer\SMS',
            'worldcatconnection' => 'VuFind\Connection\WorldCat',
            'worldcatutils' => 'VuFind\Connection\WorldCatUtils',
        ),
        'initializers' => array(
            array('VuFind\ServiceManager\Initializer', 'initInstance'),
        ),
        'aliases' => array(
            'translator' => 'VuFind\Translator',
        ),
    ),
    'session_plugin_manager' => array(
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
    'statistics_driver_plugin_manager' => array(
        'abstract_factories' => array('VuFind\Statistics\Driver\PluginFactory'),
        'invokables' => array(
            'db' => 'VuFind\Statistics\Driver\Db',
            'file' => 'VuFind\Statistics\Driver\File',
            'solr' => 'VuFind\Statistics\Driver\Solr',
        ),
        'aliases' => array(
            'database' => 'db',
        ),
    ),
    'translator' => array(),
    'view_manager' => array(
        'display_not_found_reason' => APPLICATION_ENV == 'development',
        'display_exceptions'       => APPLICATION_ENV == 'development',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_path_stack'      => array(),
    ),
);

// Define record view routes -- route name => controller
$recordRoutes = array(
    'record' => 'Record',
    'missingrecord' => 'MissingRecord',
    'summonrecord' => 'SummonRecord',
    'worldcatrecord' => 'WorldcatRecord'
);
$nonTabRecordActions = array(
    'AddComment', 'DeleteComment', 'AddTag', 'Save', 'Email', 'SMS', 'Cite',
    'Export', 'RDF', 'Hold', 'BlockedHold', 'Home'
);

// Define list-related routes -- route name => MyResearch action
$listRoutes = array('userList' => 'MyList', 'editList' => 'EditList');

// Define static routes -- Controller/Action strings
$staticRoutes = array(
    'Admin/Config', 'Admin/DeleteExpiredSearches', 'Admin/EnableAutoConfig',
    'Admin/Home', 'Admin/Maintenance', 'Admin/Statistics', 'Alphabrowse/Home',
    'Author/Home', 'Author/Search',
    'Authority/Home', 'Authority/Record', 'Authority/Search',
    'Browse/Author', 'Browse/Dewey', 'Browse/Era', 'Browse/Genre', 'Browse/Home',
    'Browse/LCC', 'Browse/Region', 'Browse/Tag', 'Browse/Topic',
    'Cart/doExport', 'Cart/Email', 'Cart/Export', 'Cart/Home', 'Cart/MyResearchBulk',
    'Cart/Save',
    'Cover/Show', 'Cover/Unavailable', 'Error/Unavailable', 'Help/Home',
    'Install/Done', 'Install/FixBasicConfig', 'Install/FixCache',
    'Install/FixDatabase', 'Install/FixDependencies', 'Install/FixILS',
    'Install/FixSolr', 'Install/Home', 'Install/ShowSQL',
    'MyResearch/Account', 'MyResearch/CheckedOut', 'MyResearch/Delete',
    'MyResearch/DeleteList', 'MyResearch/Edit', 'MyResearch/Email',
    'MyResearch/Export', 'MyResearch/Favorites', 'MyResearch/Fines',
    'MyResearch/Holds', 'MyResearch/Home', 'MyResearch/Logout', 'MyResearch/Profile',
    'MyResearch/SaveSearch',
    'OAI/Server', 'Records/Home',
    'Search/Advanced', 'Search/Email', 'Search/History', 'Search/Home',
    'Search/NewItem', 'Search/OpenSearch', 'Search/Reserves', 'Search/Results',
    'Search/Suggest',
    'Summon/Advanced', 'Summon/Home', 'Summon/Search',
    'Tag/Home',
    'Upgrade/Home', 'Upgrade/FixAnonymousTags', 'Upgrade/FixConfig',
    'Upgrade/FixDatabase', 'Upgrade/FixMetadata', 'Upgrade/GetDBCredentials',
    'Upgrade/GetSourceDir', 'Upgrade/Reset', 'Upgrade/ShowSQL',
    'VuDL/Browse', 'VuDL/DSRecord', 'VuDL/Record',
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