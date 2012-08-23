<?php
/**
 * VuFind Translator
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Translator;
use VuFind\Cache\Manager as CacheManager,
    VuFind\Translator\Loader\ExtendedIni as ExtendedIniLoader,
    Zend\I18n\Translator\TranslatorServiceFactory;

/**
 * Wrapper class to handle text translation.
 *
 * TODO -- eliminate this (using DI?)
 *
 * @category VuFind2
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Translator
{
    protected static $translator = null;

    /**
     * Set the translator object.
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator object.
     *
     * @return void
     */
    public static function setTranslator($translator)
    {
        static::$translator = $translator;
    }

    /**
     * Retrieve the translator object.
     *
     * @return \Zend\I18n\Translator\Translator
     */
    public static function getTranslator()
    {
        return static::$translator;
    }

    /**
     * Initialize the translator.
     *
     * @param \Zend\Mvc\MvcEvent $event    Zend MVC Event object
     * @param string             $language Selected language.
     *
     * @return void
     */
    public static function init($event, $language)
    {
        // Set up the actual translator object:
        $factory = new TranslatorServiceFactory();
        $serviceManager = $event->getApplication()->getServiceManager();
        $translator = $factory->createService($serviceManager);
        $translator->addTranslationFile(
            'ExtendedIni',
            APPLICATION_PATH  . '/languages/' . $language . '.ini',
            'default', $language
        );
        $translator->setLocale($language);
        $serviceManager->setService('translator', $translator);

        // Set up the ExtendedIni plugin:
        $pluginManager = $translator->getPluginManager();
        $pluginManager->setService('extendedini', new ExtendedIniLoader());

        /* TODO -- uncomment this when Zend translator bug is fixed (RC5?):
        // Set up language caching for better performance:
        $translator
            ->setCache(CacheManager::getInstance()->getCache('language'));
         */

        // Store the translator object in the VuFind Translator wrapper:
        self::setTranslator($translator);
    }

    /**
     * Translate a string using the Translate view helper.
     *
     * @param string $str String to translate
     *
     * @return string
     */
    public static function translate($str)
    {
        if (is_object(static::$translator)) {
            return static::$translator->translate($str);
        } else {
            throw new \Exception('Translator not initialized');
        }
    }
}