<?php
/**
 * Head link view helper (extended for VuFind's theme system)
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindTheme\View\Helper;
use VuFindTheme\ThemeInfo;

/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadLink extends \Zend\View\Helper\HeadLink
{
    use ConcatTrait;

    /**
     * Theme information service
     *
     * @var ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo Theme information service
     * @param string    $plconfig  Config for current application environment
     */
    public function __construct(ThemeInfo $themeInfo, $plconfig = false)
    {
        parent::__construct();
        $this->themeInfo = $themeInfo;
        $this->usePipeline = $this->enabledInConfig($plconfig);
    }

    /**
     * Folder name and file extension for trait
     *
     * @return string
     */
    protected function getFileType()
    {
        return 'css';
    }

    /**
     * Create HTML link element from data item
     *
     * @param \stdClass $item data item
     *
     * @return string
     */
    public function itemToString(\stdClass $item)
    {
        // Normalize href to account for themes, then call the parent class:
        $relPath = 'css/' . $item->href;
        $details = $this->themeInfo
            ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

        if (!empty($details)) {
            $urlHelper = $this->getView()->plugin('url');
            $url = $urlHelper('home') . "themes/{$details['theme']}/" . $relPath;
            $url .= strstr($url, '?') ? '&_=' : '?_=';
            $url .= filemtime($details['path']);
            $item->href = $url;
        }

        return parent::itemToString($item);
    }

    /**
     * Compile a less file to css and add to css folder
     *
     * @param string $file Path to less file
     *
     * @return string
     */
    public function addLessStylesheet($file)
    {
        $relPath = 'less/' . $file;
        $urlHelper = $this->getView()->plugin('url');
        $currentTheme = $this->themeInfo->findContainingTheme($relPath);
        $helperHome = $urlHelper('home');
        $home = APPLICATION_PATH . '/themes/' . $currentTheme . '/';
        $cssDirectory = $helperHome . 'themes/' . $currentTheme . '/css/less/';

        try {
            $less_files = [
                APPLICATION_PATH . '/themes/' . $currentTheme . '/' . $relPath
                    => $cssDirectory
            ];
            $themeParents = array_keys($this->themeInfo->getThemeInfo());
            $directories = [];
            foreach ($themeParents as $theme) {
                $directories[APPLICATION_PATH . '/themes/' . $theme . '/less/']
                    = $helperHome . 'themes/' . $theme . '/css/less/';
            }
            $css_file_name = \Less_Cache::Get(
                $less_files,
                [
                    'cache_dir' => $home . 'css/less/',
                    'cache_method' => false,
                    'compress' => true,
                    'import_dirs' => $directories,
                    'output' => str_replace('.less', '.css', $file)
                ]
            );
            return $cssDirectory . $css_file_name;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            list($fileName, ) = explode('.', $file);
            return $urlHelper('home') . "themes/{$currentTheme}/css/{$fileName}.css";
        }
    }

    /**
     * Returns true if file should not be included in the compressed concat file
     * Required by ConcatTrait
     *
     * @param stdClass $item Link element object
     *
     * @return bool
     */
    protected function isExcludedFromConcat($item)
    {
        return !isset($item->rel) || $item->rel != 'stylesheet'
            || strpos($item->href, '://');
    }

    /**
     * Get the file path from the link object
     * Required by ConcatTrait
     *
     * @param stdClass $item Link element object
     *
     * @return string
     */
    protected function getResourceFilePath($item)
    {
        return $item->href;
    }

    /**
     * Set the file path of the link object
     * Required by ConcatTrait
     *
     * @param stdClass $item Link element object
     * @param string   $path New path string
     *
     * @return stdClass
     */
    protected function setResourceFilePath($item, $path)
    {
        $item->href = $path;
        return $item;
    }

    /**
     * Get the file type
     *
     * @param stdClass $item Link element object
     *
     * @return string
     */
    public function getType($item)
    {
        return isset($item->media) ? $item->media : 'all';
    }

    /**
     * Get the minifier that can handle these file types
     * Required by ConcatTrait
     *
     * @return \MatthiasMullie\Minify\JS
     */
    protected function getMinifier()
    {
        return new \MatthiasMullie\Minify\CSS();
    }
}
