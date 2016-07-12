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
     * Retrieve string representation
     * Customized to minify and hash files
     *
     * @param  string|int $indent Amount of whitespaces or string to use for indention
     * @return string
     */
    public function toString($indent = null)
    {
        $items = []; // files to be minified together
        $scripts = []; // all scripts being outputted: conditional, concat, inline
        $concatkey = ''; // concat of combined file names and mod dates, to be hashed
        $inlineScripts = [];
        $template = null; //
        foreach ($this as $item) {
            if (empty($item->attributes['src'])) {
                $inlineScripts[] = $item;
                continue;
            }
            if (isset($item->attributes['conditional'])) {
                $scripts[] = $item;
                continue;
            }
            if ($template == null) {
                $template = $item;
            }

            $relPath = 'js/' . $item->attributes['src'];
            $details = $this->themeInfo
                ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

            $concatkey .= $item->attributes['src'] . filemtime($details['path']);
            $items[] = $details['path'];
        }


        if (empty($items) && empty($scripts)) {
            return parent::toString($indent);
        }

        // Locate/create concatinated js file
        $relPath = '/' . $this->themeInfo->getTheme() . '/js/concat/'
            . md5($concatkey) . '.min.js';
        $concatPath = $this->themeInfo->getBaseDir() . $relPath;
        if (!file_exists($concatPath)) {
            $js = new \MatthiasMullie\Minify\JS();
            for ($i = 0; $i < count($items); $i++) {
                $js->add($items[$i]);
            }
            $js->minify($concatPath);
        }

        // Transform template script object into concat script object
        $urlHelper = $this->getView()->plugin('url');
        $template->attributes['src'] = $urlHelper('home') . 'themes' . $relPath;
        unset($template->attributes['conditional']);
        $scripts[] = $template;

        // Copied from parent
        $indent = (null !== $indent)
            ? $this->getWhitespace($indent)
            : $this->getIndent();

        if ($this->view) {
            $useCdata = $this->view->plugin('doctype')->isXhtml();
        } else {
            $useCdata = $this->useCdata;
        }

        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';

        $output = [];
        $scripts = array_merge($scripts, $inlineScripts);
        foreach ($scripts as $script) {
            $output[] = parent::itemToString($script, $indent, $escapeStart, $escapeEnd);
        }

        return implode($this->getSeparator(), $output);
    }
}
