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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
     */
    public function __construct(ThemeInfo $themeInfo)
    {
        parent::__construct();
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
     * @param string $file                  Path to less file
     * @param string $media                 Media type
     * @param string $conditionalStylesheet Load condition for file
     *
     * @return void
     */
    public function addLessStylesheet($file, $media = 'all',
        $conditionalStylesheet = false
    ) {
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
            $this->prependStylesheet(
                $cssDirectory . $css_file_name, $media, $conditionalStylesheet
            );
        } catch (\Exception $e) {
            error_log($e->getMessage());
            list($fileName, ) = explode('.', $file);
            $this->prependStylesheet(
                $urlHelper('home') . "themes/{$currentTheme}/css/{$fileName}.css"
            );
        }
    }

    /**
     * Render link elements as string
     * Customized to minify and concatinate
     *
     * @param  string|int $indent
     * @return string
     */
    public function toString($indent = null)
    {
        try {

            $this->getContainer()->ksort();

            $items = [];
            $otherSheets = [];
            $concatkey = '';
            $template = null; // template object for our concatinated file
            $templateKey = 0;
            $keyLimit = 0;
            foreach ($this as $key => $item) {
                if ($key > $keyLimit) {
                    $keyLimit = $key;
                }
                if (isset($item->media) && $item->media != 'all') {
                    $otherSheets[$key] = $item;
                    continue;
                }
                if ($template == null) {
                    $template = $item;
                    $templateKey = $key;
                }
                $relPath = 'css/' . $item->href;
                $detail = $this->themeInfo
                    ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

                $concatkey .= $item->href . filemtime($detail['path']);
                $items[] = $detail['path'];
            }

            if (empty($items)) {
                return parent::toString($indent);
            }

            $relPath = '/' . $this->themeInfo->getTheme() . '/css/concat/'
                . md5($concatkey) . '.min.css';
            $concatPath = $this->themeInfo->getBaseDir() . $relPath;
            if (!file_exists($concatPath)) {
                $css = new \MatthiasMullie\Minify\CSS();
                for ($i = 0; $i < count($items); $i++) {
                    $css->add($items[$i]);
                }
                $css->minify($concatPath);
            }

            // Transform template sheet object into concat sheet object
            $urlHelper = $this->getView()->plugin('url');
            $template->href = $urlHelper('home') . 'themes' . $relPath;

            $output = [];
            for ($i = 0; $i <= $keyLimit; $i++) {
                if ($i == $templateKey) {
                    $output[] = parent::itemToString($template);
                }
                if (isset($otherSheets[$i])) {
                    $output[] = $this->itemToString($otherSheets[$i]);
                }
            }

            $indent = (null !== $indent)
                    ? $this->getWhitespace($indent)
                    : $this->getIndent();

            return $indent . implode(
                $this->escape($this->getSeparator()) . $indent, $output
            );

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
