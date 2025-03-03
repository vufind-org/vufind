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
    protected $scripts = ['header' => [], 'footer' => []];

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
    }

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
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     *
     * @return void
     */
    public function appendScript(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header'
    ) {
        $this->scripts[$position][] = compact('script', 'attrs', 'allowArbitraryAttrs');
    }

    /**
     * Add an entry to the list of script files.
     *
     * @param string $src      Script src
     * @param array  $attrs    Array of script attributes
     * @param string $position Position to output script (header or footer)
     *
     * @return void
     */
    public function appendScriptFile(
        string $src,
        array $attrs = [],
        string $position = 'header'
    ): void {
        $this->scripts[$position][] = compact('src', 'attrs');
    }

    /**
     * Forcibly prepend a file, removing it from any existing position.
     *
     * @param string $src      Script src
     * @param array  $attrs    Array of script attributes
     * @param string $position Position to output script (header or footer)
     *
     * @return void
     */
    public function forcePrependScriptFile(
        string $src,
        array $attrs = [],
        string $position = 'header'
    ): void {
        $newScripts = [compact('src', 'attrs')];
        foreach ($this->scripts[$position] as $script) {
            if ($script['src'] ?? null !== $newScripts[0]['src']) {
                $newScripts[] = $script;
            }
        }
        $this->scripts[$position] = $newScripts;
    }

    /**
     * Prepend raw script code.
     *
     * @param string $script              Script code
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     * @param string $position            Position to output script (header or footer)
     *
     * @return void
     */
    public function prependScript(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
        string $position = 'header'
    ) {
        array_unshift($this->scripts[$position], compact('script', 'attrs', 'allowArbitraryAttrs'));
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
        $scriptHelper = $this->getView()->plugin('inlineScript');
        $processedScripts = $this->pipeline->process($this->scripts[$position], 'js');
        foreach ($processedScripts as $i => $script) {
            if ($script['allowArbitraryAttrs'] ?? false) {
                $scriptHelper->setAllowArbitraryAttributes(true);
            }
            // Every $script will have either a script attribute (inline JS) or a src attribute (file):
            if (isset($script['script'])) {
                $output[] = $this->outputInlineScript($script['script'], $script['attrs']);
            } else {
                if ($this->isRelativePath($script['src'])) {
                    if ($themePath = $this->applyThemeToRelativePath('js/' . $script['src'])) {
                        $script['src'] = $themePath;
                    }
                }
                $output[] = $this->outputInlineScriptFile($script['src'], $script['attrs']);
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
     * @param string $script              Script code
     * @param array  $attrs               Additional attributes for the script tag
     * @param bool   $allowArbitraryAttrs Should we allow arbitrary attributes in $attrs?
     *
     * @return string
     */
    public function outputInlineScript(
        string $script,
        array $attrs = [],
        bool $allowArbitraryAttrs = false,
    ): string {
        if (!empty($this->cspNonce)) {
            $attrs['nonce'] = $this->cspNonce;
        }
        $inlineScript = $this->getView()->plugin('inlineScript');
        if ($allowArbitraryAttrs) {
            $inlineScript->setAllowArbitraryAttributes(true);
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setScript($script, $type, $attrs);
        return ($inlineScript)();
    }

    /**
     * Output an inline script file.
     *
     * @param string $src   Script src
     * @param array  $attrs Array of script attributes
     *
     * @return string
     */
    public function outputInlineScriptFile(
        string $src,
        array $attrs = [],
    ): string {
        if (!empty($this->cspNonce)) {
            $attrs['nonce'] = $this->cspNonce;
        }
        $inlineScript = $this->getView()->plugin('inlineScript');
        if ($this->isRelativePath($src)) {
            $src = $this->applyThemeToRelativePath('js/' . $src) ?? $src;
        }
        $type = $attrs['type'] ?? 'text/javascript';
        unset($attrs['type']);
        $inlineScript->setFile($src, $type, $attrs);
        return ($inlineScript)();
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
