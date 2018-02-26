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
     * Construct the Content\Summaries Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\Summaries\PluginManager
     */
    public static function getContentSummariesPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\Summaries');
    }

    /**
     * Construct the Content\TOC Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Content\TOC\PluginManager
     */
    public static function getContentTOCPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Content\TOC');
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
     * Construct the CSRF validator.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Validator\Csrf
     */
    public static function getCsrfValidator(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $sessionManager = $sm->get('VuFind\SessionManager');
        return new \VuFind\Validator\Csrf(
            [
                'session' => new \Zend\Session\Container('csrf', $sessionManager),
                'salt' => isset($config->Security->HMACkey)
                    ? $config->Security->HMACkey : 'VuFindCsrfSalt'
            ]
        );
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
        return $sm->get('VuFind\Db\AdapterFactory')->getAdapter();
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
     * Construct the translator.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\Mvc\I18n\Translator
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
}
