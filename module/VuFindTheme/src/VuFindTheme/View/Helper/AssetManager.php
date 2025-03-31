<?php

/**
 * Asset manager view helper (for pre-processing, combining when appropriate, etc.)
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

use Laminas\View\Helper\HeadScript;

/**
 * Asset manager view helper (for pre-processing, combining when appropriate, etc.)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AssetManager extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Should we allow arbitrary attributes on scripts by default?
     *
     * @var bool
     */
    protected bool $allowArbitraryScriptAttributesByDefault = false;

    /**
     * Add raw CSS to the pipeline.
     *
     * @param string $css        Raw CSS.
     * @param array  $attributes Extra attributes for style tag
     * @param array  $options    Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendStyleString(string $css, array $attributes = [], array $options = []): static
    {
        $this->getView()->plugin('headStyle')->appendStyle($css, $attributes);
        return $this;
    }

    /**
     * Add an entry to the list of stylesheets.
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     * @param array  $options               Additional options (not yet used; for forward-compatibility)
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendStyleLink(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = [],
        array $options = []
    ): static {
        $this->getView()->plugin('headLink')->appendStylesheet($href, $media, $conditionalStylesheet, $extras);
        return $this;
    }

    /**
     * Forcibly prepend a stylesheet, removing it from any existing position
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     * @param array  $options               Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function forcePrependStyleLink(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = [],
        array $options = []
    ): static {
        $this->getView()->plugin('headLink')->forcePrependStylesheet($href, $media, $conditionalStylesheet, $extras);
        return $this;
    }

    /**
     * Clear the list of styles and stylesheets.
     *
     * @return static
     */
    public function clearStyleList(): static
    {
        $this->getView()->plugin('headStyle')->deleteContainer();
        $this->getView()->plugin('headLink')->deleteContainer();
        return $this;
    }

    /**
     * Turn on the arbitraryAttributesAllowed behavior only if necessary.
     *
     * @param HeadScript $helper  View helper to configure (supports InlineScript and FootScript as well)
     * @param array      $options Options array to evaluate
     *
     * @return void
     */
    protected function applyArbitraryScriptAttributesOption(HeadScript $helper, array $options): void
    {
        // Because of the workflow of the 10.x code, we have to turn the setting on and leave it on if ANY
        // scripts require it. This logic will be refined and better restricted when things are refactored
        // in 11.0.
        $newValue = $options['allow_arbitrary_attributes'] ?? $this->allowArbitraryScriptAttributesByDefault;
        if ($newValue && $helper->arbitraryAttributesAllowed() !== $newValue) {
            $helper->setAllowArbitraryAttributes($newValue);
        }
    }

    /**
     * Append raw script code.
     *
     * @param string $script   Script code
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendScriptString(
        string $script,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $this->applyArbitraryScriptAttributesOption($helper, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->appendScript($script, $type, $attrs);
        return $this;
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $src      Script src
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendScriptLink(
        string $src,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $this->applyArbitraryScriptAttributesOption($helper, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->appendFile($src, $type, $attrs);
        return $this;
    }

    /**
     * Forcibly prepend a file, removing it from any existing position.
     *
     * @param string $src      Script src
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function forcePrependScriptLink(
        string $src,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $this->applyArbitraryScriptAttributesOption($helper, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->forcePrependFile($src, $type, $attrs);
        return $this;
    }

    /**
     * Prepend raw script code.
     *
     * @param string $script   Script code
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function prependScriptString(
        string $script,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $this->applyArbitraryScriptAttributesOption($helper, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->prependScript($script, $type, $attrs);
        return $this;
    }

    /**
     * Clear the list of scripts.
     *
     * @return static
     */
    public function clearScriptList(): static
    {
        $this->getView()->plugin('headScript')->deleteContainer();
        $this->getView()->plugin('footScript')->deleteContainer();
        return $this;
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
     * Output an inline script.
     *
     * @param string $script  Script code
     * @param array  $attrs   Additional attributes for the script tag
     * @param array  $options Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return string
     */
    public function outputInlineScriptString(
        string $script,
        array $attrs = [],
        array $options = []
    ): string {
        $inlineScript = $this->getView()->plugin('inlineScript');
        $this->applyArbitraryScriptAttributesOption($inlineScript, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setScript($script, $type, $attrs);
        $result = ($inlineScript)();
        return $result;
    }

    /**
     * Output an inline script file.
     *
     * @param string $src     Script src
     * @param array  $attrs   Additional attributes for the script tag
     * @param array  $options Additional options (supported option: allow_arbitrary_attributes)
     *
     * @return string
     */
    public function outputInlineScriptLink(
        string $src,
        array $attrs = [],
        array $options = []
    ): string {
        $inlineScript = $this->getView()->plugin('inlineScript');
        $this->applyArbitraryScriptAttributesOption($inlineScript, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setFile($src, $type, $attrs);
        $result = ($inlineScript)();
        return $result;
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
