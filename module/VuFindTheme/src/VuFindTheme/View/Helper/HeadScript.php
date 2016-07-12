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
     * @param string|int $indent Amount of whitespace or string to use for indention
     *
     * @return string
     */
    public function toString($indent = null)
    {
        // toString must not throw exception
        try {

            $concatkey = '';
            $concatItems = [];
            $otherScripts = [];
            $template = null; // template object for our concatinated file
            $templateKey = 0;
            $keyLimit = 0;

            $this->getContainer()->ksort();

            foreach ($this as $key => $item) {
                if ($key > $keyLimit) {
                    $keyLimit = $key;
                }
                if (empty($item->attributes['src'])
                    || isset($item->attributes['conditional'])
                ) {
                    $otherScripts[$key] = $item;
                    continue;
                }
                if ($template == null) {
                    $template = $item;
                    $templateKey = $key;
                }

                $relPath = 'js/' . $item->attributes['src'];
                $details = $this->themeInfo
                    ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

                $concatkey .= $item->attributes['src'] . filemtime($details['path']);
                $concatItems[] = $details['path'];
            }

            if (empty($concatItems)) {
                return parent::toString($indent);
            }

            // Locate/create concatinated js file
            $relPath = '/' . $this->themeInfo->getTheme() . '/js/concat/'
                . md5($concatkey) . '.min.js';
            $concatPath = $this->themeInfo->getBaseDir() . $relPath;
            if (!file_exists($concatPath)) {
                $js = new \MatthiasMullie\Minify\JS();
                foreach ($concatItems as $script) {
                    $js->add($script);
                }
                $js->minify($concatPath);
            }

            // Transform template script object into concat script object
            $urlHelper = $this->getView()->plugin('url');
            $template->attributes['src'] = $urlHelper('home') . 'themes' . $relPath;

            return $this->outputInOrder(
                $template, $templateKey, $otherScripts, $keyLimit, $indent
            );

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
