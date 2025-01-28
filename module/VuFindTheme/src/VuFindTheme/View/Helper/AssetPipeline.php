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
     * Output the collected assets.
     *
     * @return string
     */
    public function outputAssets(): string
    {
        return ($this->getView()->plugin('headLink'))() . "\n"
            . ($this->getView()->plugin('headStyle'))();
    }
}
