<?php

/**
 * Head link view helper (extended for VuFind's theme system)
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

use stdClass;
use VuFindTheme\ThemeInfo;

/**
 * Head link view helper (extended for VuFind's theme system)
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
class HeadLink extends \Laminas\View\Helper\HeadLink implements \Laminas\Log\LoggerAwareInterface
{
    use ConcatTrait;
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
     * Maximum import size (for inlining of e.g. images) in kilobytes
     *
     * @var int|null
     */
    protected $maxImportSize;

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
        parent::__construct();
        $this->themeInfo = $themeInfo;
        $this->usePipeline = $this->enabledInConfig($plconfig);
        $this->cspNonce = $nonce;
        $this->maxImportSize = $maxImportSize;
        $this->itemKeys[] = 'nonce';
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
     * @param stdClass $item data item
     *
     * @return string
     */
    public function itemToString(stdClass $item)
    {
        // Normalize href to account for themes (if appropriate), then call the parent class:
        if (isset($item->href) && $this->isRelativePath($item->href)) {
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
        }
        $this->addNonce($item);
        return parent::itemToString($item);
    }

    /**
     * Forcibly prepend a stylesheet removing it from any existing position
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     *
     * @return void
     */
    public function forcePrependStylesheet(
        $href,
        $media = 'screen',
        $conditionalStylesheet = '',
        $extras = []
    ) {
        // Look for existing entry and remove it if found. Comparison method
        // copied from isDuplicate().
        foreach ($this->getContainer() as $offset => $item) {
            if (($item->rel == 'stylesheet') && ($item->href == $href)) {
                $this->offsetUnset($offset);
                break;
            }
        }
        parent::prependStylesheet($href, $media, $conditionalStylesheet, $extras);
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
            || !isset($item->href) || !$this->isRelativePath($item->href);
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
        $type = $item->media ?? 'all';
        if (isset($item->conditionalStylesheet)) {
            $type .= '_' . $item->conditionalStylesheet;
        }
        return $type;
    }

    /**
     * Get the minifier that can handle these file types
     * Required by ConcatTrait
     *
     * @return \MatthiasMullie\Minify\JS
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
