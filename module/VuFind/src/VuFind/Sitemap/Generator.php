<?php

/**
 * VuFind Sitemap
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Sitemap;

use Laminas\Config\Config;

use function call_user_func;
use function in_array;
use function is_callable;
use function is_string;

/**
 * Class for generating sitemaps
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Generator
{
    /**
     * Base URL for sitemap
     *
     * @var string
     */
    protected $baseSitemapUrl;

    /**
     * Languages enabled for sitemaps
     *
     * @var array
     */
    protected $languages;

    /**
     * Frequency of URL updates (always, daily, weekly, monthly, yearly, never)
     *
     * @var string
     */
    protected $frequency;

    /**
     * URL entries per sitemap
     *
     * @var int
     */
    protected $countPerPage;

    /**
     * Output file path
     *
     * @var string
     */
    protected $fileLocation;

    /**
     * Base path to sitemap files, including base filename
     *
     * @var string
     */
    protected $fileStart;

    /**
     * Filename of sitemap index
     *
     * @var string
     */
    protected $indexFile = false;

    /**
     * Warnings thrown during sitemap generation
     *
     * @var array
     */
    protected $warnings = [];

    /**
     * Verbose callback
     *
     * @var callable
     */
    protected $verbose = null;

    /**
     * Constructor
     *
     * @param string        $baseUrl       VuFind base URL
     * @param Config        $config        Sitemap configuration settings
     * @param array         $locales       Enabled locales
     * @param PluginManager $pluginManager Generator plugin manager
     */
    public function __construct(
        protected $baseUrl,
        protected Config $config,
        array $locales,
        protected PluginManager $pluginManager
    ) {
        $this->languages = $this->getSitemapLanguages($locales);

        $this->baseSitemapUrl = empty($this->config->SitemapIndex->baseSitemapUrl)
            ? $this->baseUrl : $this->config->SitemapIndex->baseSitemapUrl;

        $this->frequency = $this->config->Sitemap->frequency ?? 'weekly';
        $this->countPerPage = $this->config->Sitemap->countPerPage ?? 10000;
        $this->fileLocation = $this->config->Sitemap->fileLocation ?? '/tmp';
        $this->fileStart = $this->config->Sitemap->fileName ?? 'sitemap';
        if (isset($this->config->SitemapIndex->indexFileName)) {
            $this->indexFile = $this->config->SitemapIndex->indexFileName . '.xml';
        }
    }

    /**
     * Get/set verbose callback
     *
     * @param callable|null $newMode Callback for writing verbose messages (or null
     * to disable them)
     *
     * @return callable|null Current verbose callback (null if disabled)
     */
    public function setVerbose($newMode = null)
    {
        if (null !== $newMode) {
            $this->verbose = $newMode;
        }
        return $this->verbose;
    }

    /**
     * Write a verbose message (if configured to do so)
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function verboseMsg($msg)
    {
        if (is_callable($this->verbose)) {
            call_user_func($this->verbose, $msg);
        }
    }

    /**
     * Get/set base url
     *
     * @param string $newUrl New base url
     *
     * @return string Current or new base url
     */
    public function setBaseUrl($newUrl = null)
    {
        if (null !== $newUrl) {
            $this->baseUrl = $newUrl;
        }
        return $this->baseUrl;
    }

    /**
     * Get/set base sitemap url
     *
     * @param string $newUrl New base sitemap url
     *
     * @return string Current or new base sitemap url
     */
    public function setBaseSitemapUrl($newUrl = null)
    {
        if (null !== $newUrl) {
            $this->baseSitemapUrl = $newUrl;
        }
        return $this->baseSitemapUrl;
    }

    /**
     * Get/set output file path
     *
     * @param string $newLocation New path
     *
     * @return string Current or new path
     */
    public function setFileLocation(?string $newLocation = null): string
    {
        if (null !== $newLocation) {
            $this->fileLocation = $newLocation;
        }
        return $this->fileLocation;
    }

    /**
     * Get the current microtime, formatted to a number.
     *
     * @return float
     */
    protected function getTime()
    {
        $time = explode(' ', microtime());
        return $time[1] + $time[0];
    }

    /**
     * Generate the sitemaps based on settings established by the constructor.
     *
     * @return void
     */
    public function generate()
    {
        // Start timer:
        $startTime = $this->getTime();

        // Set-up Sitemap Index
        $this->buildIndex($this->generateWithPlugins());

        // Display total elapsed time in verbose mode:
        $this->verboseMsg(
            'Elapsed time (in seconds): ' . round($this->getTime() - $startTime)
        );
    }

    /**
     * Generate sitemaps from all mandatory and configured plugins
     *
     * @return array
     */
    protected function generateWithPlugins(): array
    {
        $sitemapFiles = [];
        $sitemapIndexes = [];
        $writeMap = function (
            $sitemap,
            $name
        ) use (
            &$sitemapFiles,
            &$sitemapIndexes
        ) {
            $index = ($sitemapIndexes[$name] ?? 0) + 1;
            $sitemapIndexes[$name] = $index;
            $pageName = empty($name) ? $index : "$name-$index";
            $filePath = $this->getFilenameForPage($pageName);
            if (false === $sitemap->write($filePath)) {
                throw new \Exception("Problem writing $filePath.");
            }
            $sitemapFiles[] = $this->getFilenameForPage($pageName, false);
        };

        // If no plugins are defined, use the Index plugin by default:
        $plugins = isset($this->config->Sitemap->plugins)
            ? $this->config->Sitemap->plugins->toArray() : ['Index'];
        $pluginSitemaps = [];
        foreach ($plugins as $pluginName) {
            $plugin = $this->getPlugin($pluginName);
            $sitemapName = $plugin->getSitemapName();
            $msgName = empty($sitemapName)
                ? 'core sitemap' : "sitemap '$sitemapName'";
            $this->verboseMsg(
                "Generating $msgName with '$pluginName'"
            );
            if (!isset($pluginSitemaps[$sitemapName])) {
                $pluginSitemaps[$sitemapName] = $this->getNewSitemap();
            }
            $languages = $plugin->supportsVuFindLanguages()
                ? $this->languages : [];
            $frequency = $plugin->getFrequency();
            $sitemap = &$pluginSitemaps[$sitemapName];
            $count = $sitemap->getCount();
            foreach ($plugin->getUrls() as $url) {
                ++$count;
                if ($count > $this->countPerPage) {
                    // Write the current sitemap and clear all entries from it:
                    $writeMap($sitemap, $sitemapName);
                    $sitemap->clear();
                    $count = 1;
                }
                $dataToAdd = (($languages || $frequency) && is_string($url))
                    ? compact('url', 'languages', 'frequency') : $url;
                $sitemap->addUrl($dataToAdd);
            }
            // Unset the reference:
            unset($sitemap);
        }
        // Write remaining sitemaps:
        foreach ($pluginSitemaps as $sitemapName => $sitemap) {
            if (!$sitemap->isEmpty()) {
                $writeMap($sitemap, $sitemapName);
            }
        }
        return $sitemapFiles;
    }

    /**
     * Get array of warning messages thrown during build.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Write a sitemap index if requested.
     *
     * @param array $sitemaps Sitemaps to add to the index.
     *
     * @return void
     */
    protected function buildIndex(array $sitemaps)
    {
        // Only build index file if requested:
        if ($this->indexFile !== false) {
            $smf = $this->getNewSitemapIndex();
            $baseUrl = $this->getBaseSitemapIndexUrl();

            // Add a <sitemap /> group for a static sitemap file.
            // See sitemap.ini for more information on this option.
            $indexSettings = $this->config->SitemapIndex->toArray();
            $baseSitemapFileNames = (array)($indexSettings['baseSitemapFileName'] ?? []);
            foreach ($baseSitemapFileNames as $baseSitemapFileName) {
                // Is the value already a fully-formed URL? If so, use it as-is; otherwise,
                // turn it into a URL and validate that it exists.
                if (str_contains($baseSitemapFileName, '://')) {
                    $smf->addUrl($baseSitemapFileName);
                } else {
                    $baseSitemapFileName .= '.xml';
                    $baseSitemapFilePath = $this->fileLocation . '/'
                        . $baseSitemapFileName;
                    // Only add the <sitemap /> group if the file exists
                    // in the directory where the other sitemap files
                    // are saved, i.e. ['Sitemap']['fileLocation']
                    if (file_exists($baseSitemapFilePath)) {
                        $smf->addUrl($baseUrl . '/' . $baseSitemapFileName);
                    } else {
                        $this->warnings[] = "WARNING: Can't open file "
                            . $baseSitemapFilePath . '. '
                            . 'The sitemap index will be generated '
                            . 'without this sitemap file.';
                    }
                }
            }

            foreach ($sitemaps as $sitemap) {
                $smf->addUrl($baseUrl . '/' . $sitemap);
            }

            if (
                false === $smf->write($this->fileLocation . '/' . $this->indexFile)
            ) {
                throw new \Exception("Problem writing $this->indexFile.");
            }
        }
    }

    /**
     * Get a fresh SitemapIndex object.
     *
     * @return SitemapIndex
     */
    protected function getNewSitemapIndex()
    {
        return new SitemapIndex();
    }

    /**
     * Get a fresh Sitemap object.
     *
     * @return Sitemap
     */
    protected function getNewSitemap()
    {
        return new Sitemap($this->frequency);
    }

    /**
     * Get the filename for the specified page number or name.
     *
     * @param int|string $page        Page number or name
     * @param bool       $includePath Whether to include the path name
     *
     * @return string
     */
    protected function getFilenameForPage($page, $includePath = true)
    {
        return ($includePath ? $this->fileLocation . '/' : '')
            . $this->fileStart . ($page == 1 ? '' : '-' . $page) . '.xml';
    }

    /**
     * Get the base URL for sitemap index files
     *
     * @return string
     */
    protected function getBaseSitemapIndexUrl()
    {
        // Pick the appropriate base URL based on the configuration files:
        return $this->baseSitemapUrl;
    }

    /**
     * Create and setup a plugin
     *
     * @param string $pluginName Plugin name
     *
     * @return Plugin\GeneratorPluginInterface
     */
    protected function getPlugin(string $pluginName): Plugin\GeneratorPluginInterface
    {
        $plugin = $this->pluginManager->get($pluginName);
        $verboseCallback = function (string $msg): void {
            $this->verboseMsg($msg);
        };
        $plugin->setOptions(
            [
                'baseUrl' => $this->baseUrl,
                'baseSitemapUrl' => $this->baseSitemapUrl,
                'verboseMessageCallback' => $verboseCallback,
            ]
        );
        return $plugin;
    }

    /**
     * Get languages for a sitemap
     *
     * Returns an array with sitemap languages as keys and VuFind languages as
     * values.
     *
     * @param array $locales Enabled VuFind locales
     *
     * @return array
     */
    protected function getSitemapLanguages(array $locales): array
    {
        if (empty($this->config->Sitemap->indexLanguageVersions)) {
            return [];
        }
        if (trim($this->config->Sitemap->indexLanguageVersions) === '*') {
            $filter = [];
        } else {
            $filter = array_map(
                'trim',
                explode(',', $this->config->Sitemap->indexLanguageVersions)
            );
        }
        $result = [];
        // Add languages and fallbacks for non-locale specific languages:
        if ($filter) {
            $locales = array_intersect($locales, $filter);
        }
        foreach ($locales as $locale) {
            $parts = explode('-', $locale, 2);
            $langPart = $parts[0];
            $regionPart = $parts[1] ?? '';
            if (!$regionPart) {
                $result[$locale] = $locale;
            } else {
                $sitemapLocale = $langPart . '-' . strtoupper($regionPart);
                $result[$sitemapLocale] = $locale;
                // If the fallback language is not enabled in VuFind, add the
                // locale-specific language as the fallback:
                if (!in_array($langPart, $locales)) {
                    $result[$langPart] = $locale;
                }
            }
        }
        // If any languages are active, add the sitemap default language without a
        // target language code to the list as well:
        if ($result) {
            $result['x-default'] = null;
        }

        return $result;
    }
}
