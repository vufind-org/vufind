<?php
/**
 * Factory for various top-level VuFind services.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Service;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for various top-level VuFind services.
 *
 * @category VuFind
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Account Capabilities helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Config\AccountCapabilities
     */
    public static function getAccountCapabilities(ServiceManager $sm)
    {
        return new \VuFind\Config\AccountCapabilities(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\AuthManager')
        );
    }

    /**
     * Construct the Auth Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Auth\PluginManager
     */
    public static function getAuthPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Auth');
    }

    /**
     * Construct the Autocomplete Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Autocomplete\PluginManager
     */
    public static function getAutocompletePluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Autocomplete');
    }

    /**
     * Construct the cache manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cache\Manager
     */
    public static function getCacheManager(ServiceManager $sm)
    {
        return new \VuFind\Cache\Manager(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('searches')
        );
    }

    /**
     * Construct the cart.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cart
     */
    public static function getCart(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $active = isset($config->Site->showBookBag)
            ? (bool)$config->Site->showBookBag : false;
        $size = isset($config->Site->bookBagMaxSize)
            ? $config->Site->bookBagMaxSize : 100;
        $activeInSearch = isset($config->Site->bookbagTogglesInSearch)
            ? $config->Site->bookbagTogglesInSearch : true;
        return new \VuFind\Cart(
            $sm->get('VuFind\RecordLoader'), $sm->get('VuFind\CookieManager'),
            $size, $active, $activeInSearch
        );
    }

    /**
     * Construct the Channel Provider Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ChannelProvider\PluginManager
     */
    public static function getChannelProviderPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'ChannelProvider');
    }

    /**
     * Construct the config manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Config\PluginManager
     */
    public static function getConfig(ServiceManager $sm)
    {
        $config = $sm->get('Config');
        return new \VuFind\Config\PluginManager(
            $sm, $config['vufind']['config_reader']
        );
    }

    /**
     * Construct the Content Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\PluginManager
     */
    public static function getContentPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content');
    }

    /**
     * Construct the Content\AuthorNotes Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\AuthorNotes\PluginManager
     */
    public static function getContentAuthorNotesPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\AuthorNotes');
    }

    /**
     * Construct the Content\Covers Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\Covers\PluginManager
     */
    public static function getContentCoversPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\Covers');
    }

    /**
     * Construct the Content\Excerpts Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\Excerpts\PluginManager
     */
    public static function getContentExcerptsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\Excerpts');
    }

    /**
     * Construct the Content\Reviews Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\Reviews\PluginManager
     */
    public static function getContentReviewsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\Reviews');
    }

    /**
     * Construct the cookie manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cookie\CookieManager
     */
    public static function getCookieManager(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $path = '/';
        if (isset($config->Cookies->limit_by_path)
            && $config->Cookies->limit_by_path
        ) {
            $path = $sm->get('Request')->getBasePath();
            if (empty($path)) {
                $path = '/';
            }
        }
        $secure = isset($config->Cookies->only_secure)
            ? $config->Cookies->only_secure
            : false;
        $domain = isset($config->Cookies->domain)
            ? $config->Cookies->domain
            : null;
        $session_name = isset($config->Cookies->session_name)
            ? $config->Cookies->session_name
            : null;
        return new \VuFind\Cookie\CookieManager(
            $_COOKIE, $path, $domain, $secure, $session_name
        );
    }

    /**
     * Construct the cover router.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cover\Router
     */
    public static function getCoverRouter(ServiceManager $sm)
    {
        $base = $sm->get('ControllerPluginManager')->get('url')
            ->fromRoute('cover-show');
        return new \VuFind\Cover\Router($base);
    }

    /**
     * Construct the date converter.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Date\Converter
     */
    public static function getDateConverter(ServiceManager $sm)
    {
        return new \VuFind\Date\Converter(
            $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the date converter.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    public static function getDbAdapter(ServiceManager $sm)
    {
        return $sm->get('VuFind\DbAdapterFactory')->getAdapter();
    }

    /**
     * Construct the date converter.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Db\AdapterFactory
     */
    public static function getDbAdapterFactory(ServiceManager $sm)
    {
        return new \VuFind\Db\AdapterFactory(
            $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the Db\Row Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Db\Row\PluginManager
     */
    public static function getDbRowPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Db\Row');
    }

    /**
     * Construct the Db\Table Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    public static function getDbTablePluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Db\Table');
    }

    /**
     * Construct the export helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Export
     */
    public static function getExport(ServiceManager $sm)
    {
        return new \VuFind\Export(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('export')
        );
    }

    /**
     * Generic plugin manager factory (support method).
     *
     * @param ServiceManager $sm Service manager.
     * @param string         $ns VuFind namespace containing plugin manager
     *
     * @return object
     */
    public static function getGenericPluginManager(ServiceManager $sm, $ns)
    {
        $className = 'VuFind\\' . $ns . '\PluginManager';
        $configKey = strtolower(str_replace('\\', '_', $ns));
        $config = $sm->get('Config');
        return new $className(
            $sm, $config['vufind']['plugin_managers'][$configKey]
        );
    }

    /**
     * Construct the Hierarchy\Driver Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Hierarchy\Driver\PluginManager
     */
    public static function getHierarchyDriverPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Hierarchy\Driver');
    }

    /**
     * Construct the Hierarchy\TreeDataFormatter Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Hierarchy\TreeDataFormatter\PluginManager
     */
    public static function getHierarchyTreeDataFormatterPluginManager(
        ServiceManager $sm
    ) {
        return static::getGenericPluginManager($sm, 'Hierarchy\TreeDataFormatter');
    }

    /**
     * Construct the Hierarchy\TreeDataSource Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Hierarchy\TreeDataSource\PluginManager
     */
    public static function getHierarchyTreeDataSourcePluginManager(
        ServiceManager $sm
    ) {
        return static::getGenericPluginManager($sm, 'Hierarchy\TreeDataSource');
    }

    /**
     * Construct the Hierarchy\TreeRenderer Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Hierarchy\TreeRenderer\PluginManager
     */
    public static function getHierarchyTreeRendererPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Hierarchy\TreeRenderer');
    }

    /**
     * Construct the HTTP service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFindHttp\HttpService
     */
    public static function getHttp(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $options = [];
        if (isset($config->Proxy->host)) {
            $options['proxy_host'] = $config->Proxy->host;
            if (isset($config->Proxy->port)) {
                $options['proxy_port'] = $config->Proxy->port;
            }
            if (isset($config->Proxy->type)) {
                $options['proxy_type'] = $config->Proxy->type;
            }
        }
        $defaults = isset($config->Http)
            ? $config->Http->toArray() : [];
        return new \VuFindHttp\HttpService($options, $defaults);
    }

    /**
     * Construct the HMAC service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Crypt\HMAC
     */
    public static function getHMAC(ServiceManager $sm)
    {
        return new \VuFind\Crypt\HMAC(
            $sm->get('VuFind\Config')->get('config')->Security->HMACkey
        );
    }

    /**
     * Construct the ILS connection.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Connection
     */
    public static function getILSConnection(ServiceManager $sm)
    {
        $catalog = new \VuFind\ILS\Connection(
            $sm->get('VuFind\Config')->get('config')->Catalog,
            $sm->get('VuFind\ILSDriverPluginManager'),
            $sm->get('VuFind\Config')
        );
        return $catalog->setHoldConfig($sm->get('VuFind\ILSHoldSettings'));
    }

    /**
     * Construct the ILS\Driver Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Driver\PluginManager
     */
    public static function getILSDriverPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'ILS\Driver');
    }

    /**
     * Construct the ILS hold logic.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Logic\Holds
     */
    public static function getILSHoldLogic(ServiceManager $sm)
    {
        return new \VuFind\ILS\Logic\Holds(
            $sm->get('VuFind\ILSAuthenticator'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the ILS hold settings helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\HoldSettings
     */
    public static function getILSHoldSettings(ServiceManager $sm)
    {
        return new \VuFind\ILS\HoldSettings(
            $sm->get('VuFind\Config')->get('config')->Catalog
        );
    }

    /**
     * Construct the ILS title hold logic.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Logic\TitleHolds
     */
    public static function getILSTitleHoldLogic(ServiceManager $sm)
    {
        return new \VuFind\ILS\Logic\TitleHolds(
            $sm->get('VuFind\ILSAuthenticator'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the ProxyManager configuration.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \ProxyManager\Configuration
     */
    public static function getProxyConfig(ServiceManager $sm)
    {
        $config = new \ProxyManager\Configuration();
        $cacheManager = $sm->get('VuFind\CacheManager');
        $dir = $cacheManager->getCacheDir() . 'objects';
        $config->setProxiesTargetDir($dir);
        if (APPLICATION_ENV != 'development') {
            spl_autoload_register($config->getProxyAutoloader());
        }
        return $config;
    }

    /**
     * Construct the recaptcha helper
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Record\Loader
     */
    public static function getRecaptcha(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $siteKey = isset($config->Captcha->siteKey)
            ? $config->Captcha->siteKey
            : (isset($config->Captcha->publicKey)
                ? $config->Captcha->publicKey
                : '');
        $secretKey = isset($config->Captcha->secretKey)
            ? $config->Captcha->secretKey
            : (isset($config->Captcha->privateKey)
                ? $config->Captcha->privateKey
                : '');
        $httpClient = $sm->get('VuFind\Http')->createClient();
        $translator = $sm->get('VuFind\Translator');
        $options = ['lang' => $translator->getLocale()];
        if (isset($config->Captcha->theme)) {
            $options['theme'] = $config->Captcha->theme;
        }
        $recaptcha = new \VuFind\Service\ReCaptcha(
            $siteKey, $secretKey, ['ssl' => true], $options, null, $httpClient
        );

        return $recaptcha;
    }

    /**
     * Construct the Recommend Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Recommend\PluginManager
     */
    public static function getRecommendPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Recommend');
    }

    /**
     * Construct the record cache.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Record\Cache
     */
    public static function getRecordCache(ServiceManager $sm)
    {
        return new \VuFind\Record\Cache(
            $sm->get('VuFind\RecordDriverPluginManager'),
            $sm->get('VuFind\Config')->get('RecordCache'),
            $sm->get('VuFind\DbTablePluginManager')->get('Record')
        );
    }

    /**
     * Construct the PermissionDeniedManager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Role\PermissionDeniedManager
     */
    public static function getPermissionDeniedManager(ServiceManager $sm)
    {
        return new \VuFind\Role\PermissionDeniedManager(
            $sm->get('VuFind\Config')->get('permissionBehavior')
        );
    }

    /**
     * Construct the PermissionManager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Role\PermissionManager
     */
    public static function getPermissionManager(ServiceManager $sm)
    {
            $permManager = new \VuFind\Role\PermissionManager(
                $sm->get('VuFind\Config')->get('permissions')->toArray()
            );
            $permManager->setAuthorizationService(
                $sm->get('ZfcRbac\Service\AuthorizationService')
            );
            return $permManager;
    }

    /**
     * Construct the RecordDriver Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\RecordDriver\PluginManager
     */
    public static function getRecordDriverPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'RecordDriver');
    }

    /**
     * Construct the record loader.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Record\Loader
     */
    public static function getRecordLoader(ServiceManager $sm)
    {
        return new \VuFind\Record\Loader(
            $sm->get('VuFind\Search'),
            $sm->get('VuFind\RecordDriverPluginManager'),
            $sm->get('VuFind\RecordCache')
        );
    }

    /**
     * Construct the record router.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Record\Router
     */
    public static function getRecordRouter(ServiceManager $sm)
    {
        return new \VuFind\Record\Router(
            $sm->get('VuFind\RecordLoader'),
            $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the RecordTab Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\RecordTab\PluginManager
     */
    public static function getRecordTabPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'RecordTab');
    }

    /**
     * Construct the Related Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Related\PluginManager
     */
    public static function getRelatedPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Related');
    }

    /**
     * Construct the Resolver\Driver Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Resolver\Driver\PluginManager
     */
    public static function getResolverDriverPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Resolver\Driver');
    }

    /**
     * Construct the search backend manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\BackendManager
     */
    public static function getSearchBackendManager(ServiceManager $sm)
    {
        $config = $sm->get('config');
        $smConfig = new \Zend\ServiceManager\Config(
            $config['vufind']['plugin_managers']['search_backend']
        );
        $registry = $sm->createScopedServiceManager();
        $smConfig->configureServiceManager($registry);
        $manager = new \VuFind\Search\BackendManager($registry);

        return $manager;
    }

    /**
     * Construct the search memory helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Memory
     */
    public static function getSearchMemory(ServiceManager $sm)
    {
        return new \VuFind\Search\Memory(
            new \Zend\Session\Container('Search', $sm->get('VuFind\SessionManager'))
        );
    }

    /**
     * Construct the Search\Options Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Options\PluginManager
     */
    public static function getSearchOptionsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Search\Options');
    }

    /**
     * Construct the Search\Params Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Params\PluginManager
     */
    public static function getSearchParamsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Search\Params');
    }

    /**
     * Construct the Search\Results Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    public static function getSearchResultsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Search\Results');
    }

    /**
     * Construct the Search runner.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\SearchRunner
     */
    public static function getSearchRunner(ServiceManager $sm)
    {
        return new \VuFind\Search\SearchRunner(
            $sm->get('VuFind\SearchResultsPluginManager'),
            new \Zend\EventManager\EventManager($sm->get('SharedEventManager'))
        );
    }

    /**
     * Construct the search service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFindSearch\Service
     */
    public static function getSearchService(ServiceManager $sm)
    {
        return new \VuFindSearch\Service(
            new \Zend\EventManager\EventManager($sm->get('SharedEventManager'))
        );
    }

    /**
     * Construct the search specs reader.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Config\SearchSpecsReader
     */
    public static function getSearchSpecsReader(ServiceManager $sm)
    {
        return new \VuFind\Config\SearchSpecsReader(
            $sm->get('VuFind\CacheManager')
        );
    }

    /**
     * Construct the SearchTabs helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\SearchTabsHelper
     */
    public static function getSearchTabsHelper(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $tabConfig = isset($config->SearchTabs)
            ? $config->SearchTabs->toArray() : [];
        $filterConfig = isset($config->SearchTabsFilters)
            ? $config->SearchTabsFilters->toArray() : [];
        $permissionConfig = isset($config->SearchTabsPermissions)
            ? $config->SearchTabsPermissions->toArray() : [];
        return new \VuFind\Search\SearchTabsHelper(
            $sm->get('VuFind\SearchResultsPluginManager'),
            $tabConfig, $filterConfig,
            $sm->get('Application')->getRequest(), $permissionConfig
        );
    }

    /**
     * Construct the Session Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Session\PluginManager
     */
    public static function getSessionPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Session');
    }

    /**
     * Construct the Solr writer.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Solr\Writer
     */
    public static function getSolrWriter(ServiceManager $sm)
    {
        return new \VuFind\Solr\Writer(
            $sm->get('VuFind\Search\BackendManager'),
            $sm->get('VuFind\DbTablePluginManager')->get('changetracker')
        );
    }

    /**
     * Construct the tag helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Tags
     */
    public static function getTags(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $maxLength = isset($config->Social->max_tag_length)
            ? $config->Social->max_tag_length : 64;
        return new \VuFind\Tags($maxLength);
    }

    /**
     * Construct the translator.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\I18n\Translator\TranslatorInterface
     */
    public static function getTranslator(ServiceManager $sm)
    {
        $factory = new \Zend\Mvc\Service\TranslatorServiceFactory();
        $translator = $factory->createService($sm);

        // Set up the ExtendedIni plugin:
        $config = $sm->get('VuFind\Config')->get('config');
        $pathStack = [
            APPLICATION_PATH . '/languages',
            LOCAL_OVERRIDE_DIR . '/languages'
        ];
        $fallbackLocales = $config->Site->language == 'en'
            ? 'en'
            : [$config->Site->language, 'en'];
        try {
            $pm = $translator->getPluginManager();
        } catch (\Zend\Mvc\Exception\BadMethodCallException $ex) {
            // If getPluginManager is missing, this means that the user has
            // disabled translation in module.config.php or PHP's intl extension
            // is missing. We can do no further configuration of the object.
            return $translator;
        }
        $pm->setService(
            'extendedini',
            new \VuFind\I18n\Translator\Loader\ExtendedIni(
                $pathStack, $fallbackLocales
            )
        );

        // Set up language caching for better performance:
        try {
            $translator->setCache(
                $sm->get('VuFind\CacheManager')->getCache('language')
            );
        } catch (\Exception $e) {
            // Don't let a cache failure kill the whole application, but make
            // note of it:
            $logger = $sm->get('VuFind\Logger');
            $logger->debug(
                'Problem loading cache: ' . get_class($e) . ' exception: '
                . $e->getMessage()
            );
        }

        return $translator;
    }

    /**
     * Construct the WorldCat helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Connection\WorldCatUtils
     */
    public static function getWorldCatUtils(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $client = $sm->get('VuFind\Http')->createClient();
        $ip = $sm->get('Request')->getServer()->get('SERVER_ADDR');
        return new \VuFind\Connection\WorldCatUtils(
            isset($config->WorldCat) ? $config->WorldCat : null,
            $client, true, $ip
        );
    }

    /**
     * Construct the YAML reader.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Config\YamlReader
     */
    public static function getYamlReader(ServiceManager $sm)
    {
        return new \VuFind\Config\YamlReader(
            $sm->get('VuFind\CacheManager')
        );
    }
}
