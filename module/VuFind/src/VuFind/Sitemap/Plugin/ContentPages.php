<?php
/**
 * Content pages generator plugin
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\Sitemap\Plugin;

use Laminas\Config\Config;
use Laminas\Router\RouteStackInterface;
use VuFindTheme\ThemeInfo;

/**
 * Content pages generator plugin
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class ContentPages extends AbstractGeneratorPlugin
{
    /**
     * Theme informations
     *
     * @var ThemeInfo
     */
    protected $themeInfo;

    /**
     * Router
     *
     * @var RouteStackInterface
     */
    protected $router;

    /**
     * Base URL for site
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Main VuFind configuration (config.ini)
     *
     * @var Config
     */
    protected $config;

    /**
     * Patterns of files to be included
     *
     * @var array
     */
    protected $includedFiles = [
        'templates/content/*.phtml',
        'templates/content/*.md',
    ];

    /**
     * Files to be ignored when searching for content pages
     *
     * @var array
     */
    protected $excludedFiles = [
        'templates/content/content.phtml', // Content main page
        'templates/content/markdown.phtml', // Content main page for Markdown
    ];

    /**
     * Constructor
     *
     * @param ThemeInfo           $themeInfo Theme info
     * @param RouteStackInterface $router    Router
     * @param Config              $config    Main VuFind configuration
     */
    public function __construct(
        ThemeInfo $themeInfo,
        RouteStackInterface $router,
        Config $config
    ) {
        $this->themeInfo = $themeInfo;
        $this->router = $router;
        $this->config = $config;
    }

    /**
     * Set plugin options.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        parent::setOptions($options);
        $this->baseUrl = $options['baseUrl'] ?? '';
    }

    /**
     * Get the name of the sitemap used to create the sitemap file. This will be
     * appended to the configured base name, and may be blank to use the base
     * name without a suffix.
     *
     * @return string
     */
    public function getSitemapName(): string
    {
        return 'pages';
    }

    /**
     * Generate urls for the sitemap.
     *
     * @return \Generator
     */
    public function getUrls(): \Generator
    {
        $files = $this->themeInfo->findInThemes($this->includedFiles);
        $nonLanguageFiles = [];
        $languages = isset($this->config->Languages)
            ? array_keys($this->config->Languages->toArray())
            : [];
        // Check each file for language suffix and combine the files into a
        // non-language specific array
        foreach ($files as $fileInfo) {
            if (in_array($fileInfo['relativeFile'], $this->excludedFiles)
            ) {
                continue;
            }
            $baseName = pathinfo($fileInfo['relativeFile'], PATHINFO_FILENAME);
            // Check the filename for a known language suffix
            $p = strrpos($baseName, '_');
            if ($p > 0) {
                $fileLanguage = substr($baseName, $p + 1);
                if (in_array($fileLanguage, $languages)) {
                    $baseName = substr($baseName, 0, $p);
                }
            }
            $nonLanguageFiles[$baseName] = true;
        }

        foreach (array_keys($nonLanguageFiles) as $fileName) {
            $url = $this->baseUrl . $this->router->assemble(
                ['page' => $fileName],
                ['name' => 'content-page']
            );
            $this->verboseMsg("Adding content page $url");
            yield $url;
        }
    }
}
