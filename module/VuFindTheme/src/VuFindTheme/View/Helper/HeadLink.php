<?php
/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * PHP version 7
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
class HeadLink extends \VuFind\View\Helper\HeadLink
    implements \Laminas\Log\LoggerAwareInterface
{
    use ConcatTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Theme information service
     *
     * @var ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param ThemeInfo   $themeInfo     Theme information service
     * @param string|bool $plconfig      Config for current application environment
     * @param string      $nonce         Nonce from nonce generator
     * @param int         $maxImportSize Maximum imported (inlined) file size
     */
    public function __construct(
        ThemeInfo $themeInfo,
        $plconfig = false,
        $nonce = '',
        $maxImportSize = null
    ) {
        parent::__construct($plconfig, $nonce, $maxImportSize);
        $this->themeInfo = $themeInfo;
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
            [$fileName, ] = explode('.', $file);
            return $urlHelper('home') . "themes/{$currentTheme}/css/{$fileName}.css";
        }
    }

    /**
     * Get the minifier that can handle these file types
     * Required by ConcatTrait
     *
     * @return \MatthiasMullie\Minify\CSS
     */
    protected function getMinifier()
    {
        $minifier = new \VuFindTheme\Minify\CSS();
        if (null !== $this->maxImportSize) {
            $minifier->setMaxImportSize($this->maxImportSize);
        }
        return $minifier;
    }
}
