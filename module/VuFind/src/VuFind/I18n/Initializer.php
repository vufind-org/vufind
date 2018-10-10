<?php
/**
 * VuFind I18n Initializer
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\I18n;

use VuFind\Cookie\CookieManager;
use VuFind\I18n\Translator\Loader\ExtendedIni;
use Zend\Cache\Storage\StorageInterface;
use Zend\Config\Config;
use Zend\Http\PhpEnvironment\Request;
use Zend\I18n\Translator\Translator as I18nTranslator;
use Zend\Mvc\I18n\Translator as MvcTranslator;
use Zend\View\Model\ViewModel;

/**
 * Handles I18n initialization.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Initializer
{
    const KEY_TEXT_DOMAINS = 'TEXT_DOMAINS';

    /**
     * Translator.
     *
     * @var I18nTranslator
     */
    protected $translator;

    /**
     * Language base directories.
     *
     * @var string[]
     */
    protected $baseDirs = [];

    /**
     * File loader.
     *
     * @var ExtendedIni
     */
    protected $loader;

    /**
     * Initializer constructor.
     *
     * @param Request          $request       Request
     * @param Config           $config        Configuration
     * @param CookieManager    $cookies       Cookie manager
     * @param StorageInterface $cache         Language cache
     * @param MvcTranslator    $mvcTranslator Translator
     * @param ViewModel        $viewModel     ViewModel
     */
    public function __construct(
        Request $request,
        Config $config,
        CookieManager $cookies,
        StorageInterface $cache,
        MvcTranslator $mvcTranslator,
        ViewModel $viewModel
    ) {
        $language = $this->detectLanguage($config, $request, $cookies);

        $this->translator = $mvcTranslator->getTranslator();
        $this->loader = $this->translator->getPluginManager()
            ->get(ExtendedIni::class);

        $this->translator->setLocale($language);
        $this->translator->setCache($cache);

        $this->loader->setFallbacks($this->parseFallbacks($config));

        $rtlLangs = isset($config->LanguageSettings->rtl_langs)
            ? array_map(
                'trim', explode(',', $config->LanguageSettings->rtl_langs)
            ) : [];

        $viewModel->setVariable('userLang', $language);
        $viewModel->setVariable('allLangs', $config->Languages);
        $viewModel->setVariable('rtl', in_array($language, $rtlLangs));

        $this->addBaseDir(APPLICATION_PATH . '/languages');
        $this->addBaseDir(LOCAL_OVERRIDE_DIR . '/languages');
    }

    /**
     * Initializes the translator and loader.
     *
     * @return void
     */
    public function init()
    {
        $cache = $this->translator->getCache();
        $namespace = md5(implode('', $this->baseDirs));
        $cache->getOptions()->setNamespace($namespace);

        if (!$cache->hasItem(self::KEY_TEXT_DOMAINS)) {
            $cache->setItem(self::KEY_TEXT_DOMAINS, $this->detectTextDomains());
        }

        foreach ($cache->getItem(self::KEY_TEXT_DOMAINS) as $textDomain) {
            $this->translator->addRemoteTranslations(
                ExtendedIni::class, $textDomain
            );
        }

        $this->loader->setDirs(array_reverse($this->baseDirs));
    }

    /**
     * Parses the configured language fallbacks.
     *
     * @param Config $config Configuration
     *
     * @return array
     */
    protected function parseFallbacks(Config $config)
    {
        preg_match_all(
            "#([*a-z-]+):([a-z-]+)#",
            $config->LanguageSettings->fallbacks,
            $matches,
            PREG_SET_ORDER
        );

        return iterator_to_array(
            (function () use ($matches) {
                foreach ($matches as list(, $locale, $fallback)) {
                    yield $locale => $fallback;
                }
            })()
        );
    }

    /**
     * Adds a language directory which will have higher priority
     * than all directories added before.
     *
     * @param string $baseDir Path to directory.
     *
     * @return void
     */
    public function addBaseDir(string $baseDir)
    {
        $this->baseDirs[] = realpath($baseDir);
    }

    /**
     * Detects text domains in all added base directories.
     *
     * @return array
     */
    protected function detectTextDomains()
    {
        $textDomains = ['default'];
        foreach ($this->baseDirs as $baseDir) {
            foreach (glob("$baseDir/*", GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
                $textDomains[] = basename($dir);
            }
        }

        return array_unique($textDomains);
    }

    /**
     * Detects the currently active language.
     *
     * @param Config        $config  Configuration
     * @param Request       $request Request
     * @param CookieManager $cookies Cookie manager
     *
     * @return bool|mixed|string The detected language.
     */
    protected function detectLanguage(
        Config $config,
        Request $request,
        CookieManager $cookies
    ) {
        $browserLanguage = $this->detectBrowserLanguage($config, $request);

        if (($language = $request->getPost()->get('mylang', false))
            || ($language = $request->getQuery()->get('lng', false))
        ) {
            $cookies->set('language', $language);
        } elseif (!empty($request->getCookie()->language)) {
            $language = $request->getCookie()->language;
        } else {
            $language = (false !== $browserLanguage)
                ? $browserLanguage : $config->Site->language;
        }

        // Make sure language code is valid, reset to default if bad:
        if (!in_array($language, array_keys($config->Languages->toArray()))) {
            $language = $config->Site->language;
        }

        return $language;
    }

    /**
     * Support method for detectLanguage: process HTTP_ACCEPT_LANGUAGE value.
     * Returns browser-requested language string or false if none found.
     *
     * @param Config  $config  Configuration
     * @param Request $request Request
     *
     * @return string|bool
     */
    protected function detectBrowserLanguage(Config $config, Request $request)
    {
        if (isset($config->Site->browserDetectLanguage)
            && false == $config->Site->browserDetectLanguage
        ) {
            return false;
        }

        // break up string into pieces (languages and q factors)
        preg_match_all(
            '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $request->getServer()->get('HTTP_ACCEPT_LANGUAGE'),
            $langParse
        );

        if (!count($langParse[1])) {
            return false;
        }

        // create a list like "en" => 0.8
        $langs = array_combine($langParse[1], $langParse[4]);

        // set default to 1 for any without q factor
        foreach ($langs as $lang => $val) {
            if (empty($val)) {
                $langs[$lang] = 1;
            }
        }

        // sort list based on value
        arsort($langs, SORT_NUMERIC);

        $validLanguages = array_keys($config->Languages->toArray());

        // return first valid language
        foreach (array_keys($langs) as $language) {
            // Make sure language code is valid
            $language = strtolower($language);
            if (in_array($language, $validLanguages)) {
                return $language;
            }

            // Make sure language code is valid, reset to default if bad:
            $langStrip = current(explode("-", $language));
            if (in_array($langStrip, $validLanguages)) {
                return $langStrip;
            }
        }

        return false;
    }
}
