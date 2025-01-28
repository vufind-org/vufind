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
     * Add raw CSS to the pipeline.
     *
     * @param string $css Raw CSS.
     *
     * @return void
     */
    public function appendStyle(string $css): void
    {
        $this->getView()->plugin('headStyle')->appendStyle($css);
    }

    /**
     * Add an entry to the list of stylesheets.
     *
     * @param string $href Stylesheet href
     *
     * @return void
     */
    public function appendStylesheet(string $href): void
    {
        $this->getView()->plugin('headLink')->appendStylesheet($href);
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
        $this->getView()->plugin('headLink')->forcePrependStylesheet($href, $media, $conditionalStylesheet, $extras);
    }

    /**
     * Clear the list of stylesheets, re-establishing it with the provided one.
     *
     * @param string $href Stylesheet href
     *
     * @return void
     */
    public function setStylesheet(string $href): void
    {
        $this->getView()->plugin('headLink')->setStylesheet($href);
    }

    /**
     * Append raw script code.
     *
     * @param string $script              Script code
     * @param string $type                Script type
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     *
     * @return void
     */
    public function appendScript(
        string $script,
        string $type = 'text/javascript',
        array $attrs = [],
        bool $allowArbitraryAttrs = false
    ) {
        $headScript = $this->getView()->plugin('headScript');
        if ($allowArbitraryAttrs) {
            $headScript->setAllowArbitraryAttributes(true);
        }
        $headScript->appendScript($script, $type, $attrs);
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $href     Script href
     * @param string $position Position to output script (header or footer)
     *
     * @return void
     */
    public function appendScriptFile(string $href, string $position = 'header'): void
    {
        $helper = $position === 'header' ? 'headScript' : 'footScript';
        $this->getView()->plugin($helper)->appendFile($href);
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
     *
     * @return void
     */
    public function prependScript(
        string $script,
        string $type = 'text/javascript',
        array $attrs = [],
        bool $allowArbitraryAttrs = false
    ) {
        $headScript = $this->getView()->plugin('headScript');
        if ($allowArbitraryAttrs) {
            $headScript->setAllowArbitraryAttributes(true);
        }
        $headScript->prependScript($script, $type, $attrs);
    }

    /**
     * Output the collected assets for the header.
     *
     * @return string
     */
    public function outputHeaderAssets(): string
    {
        return ($this->getView()->plugin('headLink'))() . "\n"
            . ($this->getView()->plugin('headStyle'))() . "\n"
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
