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
     * Append raw script code.
     *
     * @param string $script              Script code
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     * @param array  $options             Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendScriptString(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$helper->arbitraryAttributesAllowed()) {
            $helper->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->appendScript($script, $type, $attrs);
        if ($resetArbitraryAttributes) {
            $helper->setAllowArbitraryAttributes(false);
        }
        return $this;
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $src                 Script src
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     * @param array  $options             Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function appendScriptLink(
        string $src,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$helper->arbitraryAttributesAllowed()) {
            $helper->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->appendFile($src, $type, $attrs);
        if ($resetArbitraryAttributes) {
            $helper->setAllowArbitraryAttributes(false);
        }
        return $this;
    }

    /**
     * Forcibly prepend a file, removing it from any existing position.
     *
     * @param string $src                 Script src
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     * @param array  $options             Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function forcePrependScriptLink(
        string $src,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$helper->arbitraryAttributesAllowed()) {
            $helper->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->forcePrependFile($src, $type, $attrs);
        if ($resetArbitraryAttributes) {
            $helper->setAllowArbitraryAttributes(false);
        }
        return $this;
    }

    /**
     * Prepend raw script code.
     *
     * @param string $script              Script code
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     * @param array  $options             Additional options (not yet used; for forward-compatibility)
     *
     * @return static
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function prependScriptString(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header',
        array $options = []
    ): static {
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $helper = $this->getView()->plugin($helperName);
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$helper->arbitraryAttributesAllowed()) {
            $helper->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $helper->prependScript($script, $type, $attrs);
        if ($resetArbitraryAttributes) {
            $helper->setAllowArbitraryAttributes(false);
        }
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
     * @param string $script              Script code
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     *
     * @return string
     */
    public function outputInlineScriptString(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false
    ): string {
        $inlineScript = $this->getView()->plugin('inlineScript');
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$inlineScript->arbitraryAttributesAllowed()) {
            $inlineScript->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setScript($script, $type, $attrs);
        $result = ($inlineScript)();
        if ($resetArbitraryAttributes) {
            $inlineScript->setAllowArbitraryAttributes(false);
        }
        return $result;
    }

    /**
     * Output an inline script file.
     *
     * @param string $src                 Script src
     * @param array  $attrs               Array of script attributes
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     *
     * @return string
     */
    public function outputInlineScriptLink(
        string $src,
        array $attrs = [],
        bool $allowArbitraryAttrs = false
    ): string {
        $inlineScript = $this->getView()->plugin('inlineScript');
        $resetArbitraryAttributes = false;
        if ($allowArbitraryAttrs && !$inlineScript->arbitraryAttributesAllowed()) {
            $inlineScript->setAllowArbitraryAttributes(true);
            $resetArbitraryAttributes = true;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setFile($src, $type, $attrs);
        $result = ($inlineScript)();
        if ($resetArbitraryAttributes) {
            $inlineScript->setAllowArbitraryAttributes(false);
        }
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
