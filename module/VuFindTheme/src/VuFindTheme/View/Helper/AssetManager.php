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
use VuFindTheme\AssetPipeline;
use VuFindTheme\ThemeInfo;

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
    use RelativePathTrait;

    /**
     * Array of accumulated scripts, indexed by position (header/footer).
     *
     * @var array
     */
    protected $scripts;

    /**
     * Array of accumulated styles.
     *
     * @var array
     */
    protected $styles;

    /**
     * Array of accumulated stylesheets.
     *
     * @var array
     */
    protected $stylesheets;

    /**
     * Should we allow arbitrary attributes on scripts by default?
     *
     * @var bool
     */
    protected bool $allowArbitraryScriptAttributesByDefault = false;

    /**
     * Constructor
     *
     * @param ThemeInfo     $themeInfo Theme information service
     * @param AssetPipeline $pipeline  Asset pipeline helper
     * @param string        $cspNonce  Nonce from nonce generator (for content security policy)
     */
    public function __construct(
        protected ThemeInfo $themeInfo,
        protected AssetPipeline $pipeline,
        protected string $cspNonce = ''
    ) {
        $this->clearScriptList();
        $this->clearStyleList();
    }

    /**
     * Add raw CSS to the pipeline.
     *
     * @param string $css        Raw CSS.
     * @param array  $attributes Extra attributes for style tag
     * @param array  $options    Additional options (supported: exclude_from_pipeline)
     *
     * @return static
     */
    public function appendStyleString(string $css, array $attributes = [], array $options = []): static
    {
        $this->styles[] = compact('css', 'attributes', 'options');
        return $this;
    }

    /**
     * Add an entry to the list of stylesheets.
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     * @param array  $options               Additional options (supported: exclude_from_pipeline)
     *
     * @return void
     */
    public function appendStyleLink(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = [],
        array $options = []
    ): static {
        $this->stylesheets[] = compact('href', 'media', 'conditionalStylesheet', 'extras', 'options');
        return $this;
    }

    /**
     * Forcibly prepend a stylesheet, removing it from any existing position
     *
     * @param string $href                  Stylesheet href
     * @param string $media                 Media
     * @param string $conditionalStylesheet Any conditions
     * @param array  $extras                Array of extra attributes
     * @param array  $options               Additional options (supported: exclude_from_pipeline)
     *
     * @return static
     */
    public function forcePrependStyleLink(
        string $href,
        string $media = 'screen',
        string $conditionalStylesheet = '',
        array $extras = [],
        array $options = []
    ): static {
        $newSheets = [compact('href', 'media', 'conditionalStylesheet', 'extras', 'options')];
        foreach ($this->stylesheets as $sheet) {
            if ($sheet['href'] !== $newSheets[0]['href']) {
                $newSheets[] = $sheet;
            }
        }
        $this->stylesheets = $newSheets;
        return $this;
    }

    /**
     * Clear the list of styles and stylesheets.
     *
     * @return static
     */
    public function clearStyleList(): static
    {
        $this->styles = [];
        $this->stylesheets = [];
        return $this;
    }

    /**
     * Apply the appropriate arbitraryAttributesAllowed value to the provided view helper, using global
     * default and any override options. If the value was changed, return the original value that should be
     * restored after processing.
     *
     * @param HeadScript $helper  View helper to configure (note that InlineScript is a child of HeadScript)
     * @param array      $options Options array to evaluate
     *
     * @return ?bool
     */
    protected function applyArbitraryScriptAttributesOption(HeadScript $helper, array $options): ?bool
    {
        $newValue = $options['allow_arbitrary_attributes'] ?? $this->allowArbitraryScriptAttributesByDefault;
        $resetValue = null;
        if ($helper->arbitraryAttributesAllowed() !== $newValue) {
            $helper->setAllowArbitraryAttributes($newValue);
            $resetValue = !$newValue;
        }
        return $resetValue;
    }

    /**
     * Append raw script code.
     *
     * @param string $script   Script code
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported: allow_arbitrary_attributes, exclude_from_pipeline)
     *
     * @return static
     */
    public function appendScriptString(
        string $script,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $this->scripts[$position][] = compact('script', 'attrs', 'options');
        return $this;
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $src      Script src
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported: allow_arbitrary_attributes, exclude_from_pipeline)
     *
     * @return static
     */
    public function appendScriptLink(
        string $src,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $this->scripts[$position][] = compact('src', 'attrs', 'options');
        return $this;
    }

    /**
     * Forcibly prepend a file, removing it from any existing position.
     *
     * @param string $src      Script src
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported: allow_arbitrary_attributes, exclude_from_pipeline)
     *
     * @return static
     */
    public function forcePrependScriptLink(
        string $src,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        $newScripts = [compact('src', 'attrs', 'options')];
        foreach ($this->scripts[$position] as $script) {
            if (($script['src'] ?? null) !== $newScripts[0]['src']) {
                $newScripts[] = $script;
            }
        }
        $this->scripts[$position] = $newScripts;
        return $this;
    }

    /**
     * Prepend raw script code.
     *
     * @param string $script   Script code
     * @param array  $attrs    Additional attributes for the script tag
     * @param string $position Position to output script (header or footer)
     * @param array  $options  Additional options (supported: allow_arbitrary_attributes, exclude_from_pipeline)
     *
     * @return static
     */
    public function prependScriptString(
        string $script,
        array $attrs = [],
        string $position = 'header',
        array $options = []
    ): static {
        array_unshift($this->scripts[$position], compact('script', 'attrs', 'options'));
        return $this;
    }

    /**
     * Clear the list of scripts.
     *
     * @return static
     */
    public function clearScriptList(): static
    {
        $this->scripts = ['header' => [], 'footer' => []];
        return $this;
    }

    /**
     * Given a relative JS or CSS path, apply appropriate theme prefixing if possible; return null if
     * the resource could not be found in a theme.
     *
     * @param string $relPath Relative path to find in theme
     *
     * @return ?string
     */
    protected function applyThemeToRelativePath(string $relPath): ?string
    {
        $details = $this->themeInfo->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);
        if (!empty($details)) {
            $urlHelper = $this->getView()->plugin('url');
            $url = $urlHelper('home') . "themes/{$details['theme']}/" . $relPath;
            $url .= strstr($url, '?') ? '&_=' : '?_=';
            $url .= filemtime($details['path']);
            return $url;
        }
        // Cannot find in theme? Return null.
        return null;
    }

    /**
     * Return the HTML to output script assets in the requested position.
     *
     * @param mixed $position Position of assets (header or footer)
     *
     * @return string
     */
    protected function outputScriptAssets($position): string
    {
        $output = [];
        $processedScripts = $this->pipeline->process($this->scripts[$position], 'js');
        foreach ($processedScripts as $script) {
            $options = $script['options'] ?? [];
            // Every $script will have either a script attribute (inline JS) or a src attribute (file):
            if (isset($script['script'])) {
                $output[] = $this->outputInlineScriptString($script['script'], $script['attrs'], $options);
            } else {
                if ($this->isRelativePath($script['src'])) {
                    if ($themePath = $this->applyThemeToRelativePath('js/' . $script['src'])) {
                        $script['src'] = $themePath;
                    }
                }
                $output[] = $this->outputInlineScriptLink($script['src'], $script['attrs'], $options);
            }
        }
        return implode("\n", $output);
    }

    /**
     * Return the HTML to output style assets.
     *
     * @return string
     */
    protected function outputStyleAssets(): string
    {
        $headLink = $this->getView()->plugin('headLink');
        $processedStylesheets = $this->pipeline->process($this->stylesheets, 'css');
        foreach ($processedStylesheets as $sheet) {
            // Account for the theme system (when appropriate):
            if ($this->isRelativePath($sheet['href'])) {
                if ($themePath = $this->applyThemeToRelativePath('css/' . $sheet['href'])) {
                    $sheet['href'] = $themePath;
                }
            }

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

        return ($headLink)() . "\n" . ($headStyle)();
    }

    /**
     * Output the collected assets for the header.
     *
     * @return string
     */
    public function outputHeaderAssets(): string
    {
        return $this->outputStyleAssets() . "\n" . $this->outputScriptAssets('header');
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
        if (!empty($this->cspNonce)) {
            $attrs['nonce'] = $this->cspNonce;
        }
        $inlineScript = $this->getView()->plugin('inlineScript');
        $resetArbitraryAttributes = $this->applyArbitraryScriptAttributesOption($inlineScript, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setScript($script, $type, $attrs);
        $result = ($inlineScript)();
        if ($resetArbitraryAttributes !== null) {
            $inlineScript->setAllowArbitraryAttributes($resetArbitraryAttributes);
        }
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
        if (!empty($this->cspNonce)) {
            $attrs['nonce'] = $this->cspNonce;
        }
        $inlineScript = $this->getView()->plugin('inlineScript');
        if ($this->isRelativePath($src)) {
            $src = $this->applyThemeToRelativePath('js/' . $src) ?? $src;
        }
        $resetArbitraryAttributes = $this->applyArbitraryScriptAttributesOption($inlineScript, $options);
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setFile($src, $type, $attrs);
        $result = ($inlineScript)();
        if ($resetArbitraryAttributes !== null) {
            $inlineScript->setAllowArbitraryAttributes($resetArbitraryAttributes);
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
        return $this->outputScriptAssets('footer');
    }
}
