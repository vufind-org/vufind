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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Service;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for various top-level VuFind services.
 *
 * @category VuFind2
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Factory
{
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
        return new \VuFind\Cart(
            $sm->get('VuFind\RecordLoader'), $size, $active
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
     * Construct the HTTP service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFindHttp\HttpService
     */
    public static function getHttp(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        $options = array();
        if (isset($config->Proxy->host)) {
            $options['proxy_host'] = $config->Proxy->host;
            if (isset($config->Proxy->port)) {
                $options['proxy_port'] = $config->Proxy->port;
            }
        }
        $defaults = isset($config->Http)
            ? $config->Http->toArray() : array();
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
     * Construct the ILS hold logic.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Logic\Holds
     */
    public static function getILSHoldLogic(ServiceManager $sm)
    {
        return new \VuFind\ILS\Logic\Holds(
            $sm->get('VuFind\AuthManager'), $sm->get('VuFind\ILSConnection'),
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
            $sm->get('VuFind\AuthManager'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the logger.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Log\Logger
     */
    public static function getLogger(ServiceManager $sm)
    {
        $logger = new \VuFind\Log\Logger();
        $logger->setServiceLocator($sm);
        $logger->setConfig($sm->get('VuFind\Config')->get('config'));
        return $logger;
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
        $recaptcha = new \ZendService\ReCaptcha\ReCaptcha(
            isset($config->Captcha->publicKey) ? $config->Captcha->publicKey : '',
            isset($config->Captcha->privateKey) ? $config->Captcha->privateKey : ''
        );
        if (isset($config->Captcha->theme)) {
            $recaptcha->setOption('theme', $config->Captcha->theme);
            $recaptcha->setOption('custom_theme_widget', 'custom_recaptcha_widget');
            $translator = $sm->get('VuFind\Translator');
            $recaptcha->setOption(
                'custom_translations',
                array(
                    'audio_challenge' =>
                        $translator->translate('recaptcha_audio_challenge'),
                    'cant_hear_this' =>
                        $translator->translate('recaptcha_cant_hear_this'),
                    'help_btn' =>
                        $translator->translate('recaptcha_help_btn'),
                    'image_alt_text' =>
                        $translator->translate('recaptcha_image_alt_text'),
                    'incorrect_try_again' =>
                        $translator->translate('recaptcha_incorrect_try_again'),
                    'instructions_audio' =>
                        $translator->translate('recaptcha_instructions_audio'),
                    'instructions_visual' =>
                        $translator->translate('recaptcha_instructions_visual'),
                    'play_again' =>
                        $translator->translate('recaptcha_play_again'),
                    'privacy_and_terms' =>
                        $translator->translate('recaptcha_privacy_and_terms'),
                    'refresh_btn' =>
                        $translator->translate('recaptcha_refresh_btn'),
                    'visual_challenge' =>
                        $translator->translate('recaptcha_visual_challenge')
                )
            );
        }
        return $recaptcha;
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
            $sm->get('VuFind\RecordDriverPluginManager')
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
     * Construct the record stats helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Statistics\Record
     */
    public static function getRecordStats(ServiceManager $sm)
    {
        return new \VuFind\Statistics\Record(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\StatisticsDriverPluginManager'),
            $sm->get('VuFind\SessionManager')->getId()
        );
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
        $manager  = new \VuFind\Search\BackendManager($registry);

        return $manager;
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
     * Construct the search stats helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Statistics\Search
     */
    public static function getSearchStats(ServiceManager $sm)
    {
        return new \VuFind\Statistics\Search(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\StatisticsDriverPluginManager'),
            $sm->get('VuFind\SessionManager')->getId()
        );
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
     * @return \Zend\I18n\Translator\Translator
     */
    public static function getTranslator(ServiceManager $sm)
    {
        $factory = new \Zend\I18n\Translator\TranslatorServiceFactory();
        $translator = $factory->createService($sm);

        // Set up the ExtendedIni plugin:
        $config = $sm->get('VuFind\Config')->get('config');
        $pathStack = array(
            APPLICATION_PATH  . '/languages',
            LOCAL_OVERRIDE_DIR . '/languages'
        );
        $translator->getPluginManager()->setService(
            'extendedini',
            new \VuFind\I18n\Translator\Loader\ExtendedIni(
                $pathStack, $config->Site->language
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
        $wcId = isset($config->WorldCat->id)
            ? $config->WorldCat->id : false;
        return new \VuFind\Connection\WorldCatUtils($wcId);
    }
}