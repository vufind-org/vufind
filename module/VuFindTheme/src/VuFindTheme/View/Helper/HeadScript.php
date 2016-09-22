<?php
/**
 * Head script view helper (extended for VuFind's theme system)
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
 * Head script view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HeadScript extends \Zend\View\Helper\HeadScript
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
     * @param boolean   $plconfig  Whether or not to concatenate
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
        return 'js';
    }

    /**
     * Create script HTML
     *
     * @param mixed  $item        Item to convert
     * @param string $indent      String to add before the item
     * @param string $escapeStart Starting sequence
     * @param string $escapeEnd   Ending sequence
     *
     * @return string
     */
    public function itemToString($item, $indent, $escapeStart, $escapeEnd)
    {
        // Normalize href to account for themes:
        if (!empty($item->attributes['src'])) {
            $relPath = 'js/' . $item->attributes['src'];
            $details = $this->themeInfo
                ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

            if (!empty($details)) {
                $urlHelper = $this->getView()->plugin('url');
                $url = $urlHelper('home') . "themes/{$details['theme']}/" . $relPath;
                $url .= strstr($url, '?') ? '&_=' : '?_=';
                $url .= filemtime($details['path']);
                $item->attributes['src'] = $url;
            }
        }

        return parent::itemToString($item, $indent, $escapeStart, $escapeEnd);
    }

    /**
     * Returns true if file should not be included in the compressed concat file
     * Required by ConcatTrait
     *
     * @param stdClass $item Script element object
     *
     * @return bool
     */
    protected function isExcludedFromConcat($item)
    {
        return empty($item->attributes['src'])
            || isset($item->attributes['conditional'])
            || strpos($item->attributes['src'], '://');
    }

    /**
     * Get the file path from the script object
     * Required by ConcatTrait
     *
     * @param stdClass $item Script element object
     *
     * @return string
     */
    protected function getResourceFilePath($item)
    {
        return $item->attributes['src'];
    }

    /**
     * Set the file path of the script object
     * Required by ConcatTrait
     *
     * @param stdClass $item Script element object
     * @param string   $path New path string
     *
     * @return stdClass
     */
    protected function setResourceFilePath($item, $path)
    {
        $item->attributes['src'] = $path;
        return $item;
    }

    /**
     * Get the minifier that can handle these file types
     * Required by ConcatTrait
     *
     * @return \MatthiasMullie\Minify\JS
     */
    protected function getMinifier()
    {
        return new \MatthiasMullie\Minify\JS();
    }
}
