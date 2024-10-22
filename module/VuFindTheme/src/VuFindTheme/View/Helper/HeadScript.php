<?php

/**
 * Head script view helper (extended for VuFind's theme system)
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindTheme\View\Helper;

use VuFindTheme\ThemeInfo;

use function array_key_exists;

/**
 * Head script view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @method getWhitespace(string|int $indent)
 * @method getIndent()
 * @method getSeparator()
 */
class HeadScript extends \Laminas\View\Helper\HeadScript implements \Laminas\Log\LoggerAwareInterface
{
    use ConcatTrait {
        getMinifiedData as getBaseMinifiedData;
    }
    use RelativePathTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Theme information service
     *
     * @var ThemeInfo
     */
    protected $themeInfo;

    /**
     * CSP nonce
     *
     * @var string
     */
    protected $cspNonce;

    /**
     * Constructor
     *
     * @param ThemeInfo   $themeInfo Theme information service
     * @param string|bool $plconfig  Config for current application environment
     * @param string      $nonce     Nonce from nonce generator
     */
    public function __construct(ThemeInfo $themeInfo, $plconfig = false, $nonce = '')
    {
        parent::__construct();
        $this->themeInfo = $themeInfo;
        $this->usePipeline = $this->enabledInConfig($plconfig);
        $this->cspNonce = $nonce;
        $this->optionalAttributes[] = 'nonce';
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
        // Normalize href to account for themes (if appropriate):
        if (!empty($item->attributes['src']) && $this->isRelativePath($item->attributes['src'])) {
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

        $this->addNonce($item);
        return parent::itemToString($item, $indent, $escapeStart, $escapeEnd);
    }

    /**
     * Forcibly prepend a file removing it from any existing position
     *
     * @param string $src   Script src
     * @param string $type  Script type
     * @param array  $attrs Array of script attributes
     *
     * @return void
     */
    public function forcePrependFile(
        $src = null,
        $type = 'text/javascript',
        array $attrs = []
    ) {
        // Look for existing entry and remove it if found. Comparison method
        // copied from isDuplicate().
        foreach ($this->getContainer() as $offset => $item) {
            if (
                ($item->source === null)
                && array_key_exists('src', $item->attributes)
                && ($src === $item->attributes['src'])
            ) {
                $this->offsetUnset($offset);
                break;
            }
        }
        parent::prependFile($src, $type, $attrs);
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
            || !$this->isRelativePath($item->attributes['src']);
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

    /**
     * Get minified data for a file
     *
     * @param array  $details    File details
     * @param string $concatPath Target path for the resulting file (used in minifier
     * for path mapping)
     *
     * @throws \Exception
     * @return string
     */
    protected function getMinifiedData($details, $concatPath)
    {
        $data = $this->getBaseMinifiedData($details, $concatPath);
        // Play it safe by terminating a script with a semicolon
        if (!str_ends_with(trim($data), ';')) {
            $data .= ';';
        }
        return $data;
    }

    /**
     * Add a nonce to the item
     *
     * @param stdClass $item Item
     *
     * @return void
     */
    protected function addNonce($item)
    {
        $item->attributes['nonce'] = $this->cspNonce;
    }
}
