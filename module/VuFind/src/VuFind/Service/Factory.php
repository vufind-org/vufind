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
     * Construct the date converter.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    public static function getDbAdapter(ServiceManager $sm)
    {
        return $sm->get('VuFind\Db\AdapterFactory')->getAdapter();
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
     * Construct the HTTP service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFindHttp\HttpService
     */
    public static function getHttp(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
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
     * Construct the ProxyManager configuration.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \ProxyManager\Configuration
     */
    public static function getProxyConfig(ServiceManager $sm)
    {
        $config = new \ProxyManager\Configuration();
        $cacheManager = $sm->get('VuFind\Cache\Manager');
        $dir = $cacheManager->getCacheDir() . 'objects';
        $config->setProxiesTargetDir($dir);
        if (APPLICATION_ENV != 'development') {
            spl_autoload_register($config->getProxyAutoloader());
        }
        return $config;
    }

    /**
     * Construct the search history helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\History
     */
    public static function getSearchHistory(ServiceManager $sm)
    {
        $searchTable = $sm->get('VuFind\Db\Table\PluginManager')
            ->get("Search");
        $resultsManager = $sm->get('VuFind\Search\Results\PluginManager');
        $sessionId = $sm->get('VuFind\SessionManager')->getId();
        return new \VuFind\Search\History($searchTable, $sessionId, $resultsManager);
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
     * Construct the Search runner.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\SearchRunner
     */
    public static function getSearchRunner(ServiceManager $sm)
    {
        return new \VuFind\Search\SearchRunner(
            $sm->get('VuFind\Search\Results\PluginManager'),
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
            $sm->get('VuFind\Cache\Manager')
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $tabConfig = isset($config->SearchTabs)
            ? $config->SearchTabs->toArray() : [];
        $filterConfig = isset($config->SearchTabsFilters)
            ? $config->SearchTabsFilters->toArray() : [];
        $permissionConfig = isset($config->SearchTabsPermissions)
            ? $config->SearchTabsPermissions->toArray() : [];
        return new \VuFind\Search\SearchTabsHelper(
            $sm->get('VuFind\Search\Results\PluginManager'),
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
            $sm->get('VuFind\Db\Table\PluginManager')->get('changetracker')
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
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
        $factory = new \Zend\Mvc\I18n\TranslatorFactory();
        $translator = $factory->createService($sm);

        // Set up the ExtendedIni plugin:
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $pathStack = [
            APPLICATION_PATH . '/languages',
            LOCAL_OVERRIDE_DIR . '/languages'
        ];
        $fallbackLocales = $config->Site->language == 'en'
            ? 'en'
            : [$config->Site->language, 'en'];
        try {
            $pm = $translator->getPluginManager();
        } catch (\Zend\Mvc\I18n\Exception\BadMethodCallException $ex) {
            // If getPluginManager is missing, this means that the user has
            // disabled translation in module.config.php or PHP's intl extension
            // is missing. We can do no further configuration of the object.
            return $translator;
        }
        $pm->setService(
            'ExtendedIni',
            new \VuFind\I18n\Translator\Loader\ExtendedIni(
                $pathStack, $fallbackLocales
            )
        );

        // Set up language caching for better performance:
        try {
            $translator->setCache(
                $sm->get('VuFind\Cache\Manager')->getCache('language')
            );
        } catch (\Exception $e) {
            // Don't let a cache failure kill the whole application, but make
            // note of it:
            $logger = $sm->get('VuFind\Log\Logger');
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
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        $client = $sm->get('VuFindHttp\HttpService')->createClient();
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
            $sm->get('VuFind\Cache\Manager')
        );
    }
}
