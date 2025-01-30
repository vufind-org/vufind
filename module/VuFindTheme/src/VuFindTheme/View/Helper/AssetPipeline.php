<?php

/**
 * Asset pipeline view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

/**
 * Asset pipeline view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AssetPipeline extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Array of accumulated styles.
     *
     * @var array
     */
    protected $styles = [];

    /**
     * Array of accumulated stylesheets.
     *
     * @var array
     */
    protected $stylesheets = [];

    /**
     * Add raw CSS to the pipeline.
     *
     * @param string $css        Raw CSS.
     * @param array  $attributes Extra attributes for style tag
     *
     * @return void
     */
    public function appendStyle(string $css, array $attributes = []): void
    {
        $this->styles[] = compact('css', 'attributes');
    }

    /**
     * Add an entry to the list of stylesheets.
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     *
     * @return void
     */
    public function appendStylesheet(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = []
    ): void {
        $this->stylesheets[] = compact('href', 'media', 'conditionalStylesheet', 'extras');
    }

    /**
     * Forcibly prepend a stylesheet, removing it from any existing position
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     *
     * @return void
     */
    public function forcePrependStylesheet(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = []
    ): void {
        $newSheets = [compact('href', 'media', 'conditionalStylesheet', 'extras')];
        foreach ($this->stylesheets as $sheet) {
            if ($sheet['href'] !== $newSheets[0]['href']) {
                $newSheets[] = $sheet;
            }
        }
        $this->stylesheets = $newSheets;
    }

    /**
     * Clear the list of stylesheets, re-establishing it with the provided one.
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     *
     * @return void
     */
    public function setStylesheet(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = []
    ): void {
        $this->stylesheets = [compact('href', 'media', 'conditionalStylesheet', 'extras')];
    }

    /**
     * Append raw script code.
     *
     * @param string $script              Script code
     * @param string $type                Script type
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     *
     * @return void
     */
    public function appendScript(
        string $script,
        string $type = 'text/javascript',
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header'
    ) {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $scriptHelper = $this->getView()->plugin($helperName);
        if ($allowArbitraryAttrs) {
            $scriptHelper->setAllowArbitraryAttributes(true);
        }
        $scriptHelper->appendScript($script, $type, $attrs);
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $src      Script src
     * @param string $type     Script type
     * @param array  $attrs    Array of script attributes
     * @param string $position Position to output script (header or footer)
     *
     * @return void
     */
    public function appendScriptFile(
        string $src,
        string $type = 'text/javascript',
        array $attrs = [],
        string $position = 'header'
    ): void {
        $helper = $position === 'header' ? 'headScript' : 'footScript';
        $this->getView()->plugin($helper)->appendFile($src, $type, $attrs);
    }

    /**
     * Forcibly prepend a file, removing it from any existing position.
     *
     * @param string $src      Script src
     * @param string $type     Script type
     * @param array  $attrs    Array of script attributes
     * @param string $position Position to output script (header or footer)
     *
     * @return void
     */
    public function forcePrependScriptFile(
        string $src,
        string $type = 'text/javascript',
        array $attrs = [],
        string $position = 'header'
    ): void {
        $helper = $position === 'header' ? 'headScript' : 'footScript';
        $this->getView()->plugin($helper)->forcePrependFile($src, $type, $attrs);
    }

    /**
     * Prepend raw script code.
     *
     * @param string $script              Script code
     * @param string $type                Script type
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     *
     * @return void
     */
    public function prependScript(
        string $script,
        string $type = 'text/javascript',
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header'
    ) {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $scriptHelper = $this->getView()->plugin($helperName);
        if ($allowArbitraryAttrs) {
            $scriptHelper->setAllowArbitraryAttributes(true);
        }
        $scriptHelper->prependScript($script, $type, $attrs);
    }

    /**
     * Output the collected assets for the header.
     *
     * @return string
     */
    public function outputHeaderAssets(): string
    {
        $headLink = $this->getView()->plugin('headLink');
        foreach ($this->stylesheets as $sheet) {
            $headLink->appendStylesheet(
                $sheet['href'],
                $sheet['media'],
                $sheet['conditionalStylesheet'],
                $sheet['extras']
            );
        }

        $headStyle = $this->getView()->plugin('headStyle');
        foreach ($this->styles as $style) {
            $headStyle->appendStyle($style['css'], $style['attributes']);
        }

        return ($headLink)() . "\n"
            . ($headStyle)() . "\n"
            . ($this->getView()->plugin('headScript'))();
    }

    /**
     * Output the collected assets for the footer.
     *
     * @return string
     */
    public function outputFooterAssets(): string
    {
        return ($this->getView()->plugin('footScript'))();
    }
}
