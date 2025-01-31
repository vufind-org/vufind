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

use Exception;
use Laminas\Log\LoggerAwareInterface;
use MatthiasMullie\Minify\Minify;
use VuFind\Log\LoggerAwareTrait;
use VuFindTheme\ThemeInfo;

use function count;
use function defined;
use function in_array;
use function is_resource;

/**
 * Asset pipeline view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AssetPipeline extends \Laminas\View\Helper\AbstractHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;
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
     * @param ThemeInfo   $themeInfo      Theme information service
     * @param string|bool $pipelineConfig Config for current application environment
     * @param string      $cspNonce       Nonce from nonce generator (for content security policy)
     * @param ?int        $maxImportSize  Maximum imported (inlined) file size
     */
    public function __construct(
        protected ThemeInfo $themeInfo,
        protected string|bool $pipelineConfig,
        protected string $cspNonce = '',
        protected ?int $maxImportSize = null
    ) {
    }

    /**
     * Check if the pipeline is functional.
     *
     * @return bool
     */
    protected function isPipelineActive(): bool
    {
        try {
            $cacheDir = $this->getResourceCacheDir();
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
        if ($cacheDir && !is_writable($cacheDir)) {
            $this->logError("Cannot write to $cacheDir; disabling asset pipeline.");
            return false;
        }
    }

    /**
     * Check if config is enabled for the specified file type
     *
     * @param string $fileType File type to check for pipeline config
     *
     * @return bool
     */
    protected function isPipelineEnabledForType(string $fileType): bool
    {
        $config = $this->pipelineConfig;
        if ($config === false || $config == 'off') {
            return false;
        }
        if (
            $config == '*' || $config == 'on'
            || $config == 'true' || $config === true
        ) {
            return true;
        }
        $settings = array_map('trim', explode(',', $config));
        return in_array($fileType, $settings);
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
        $this->scripts[$position][] = compact('script', 'type', 'attrs', 'allowArbitraryAttrs');
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
        $this->scripts[$position][] = compact('src', 'type', 'attrs');
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
        $newScripts = [compact('src', 'type', 'attrs')];
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
        array_unshift($this->scripts[$position], compact('script', 'type', 'attrs', 'allowArbitraryAttrs'));
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
        $helperName = $position === 'header' ? 'headScript' : 'footScript';
        $scriptHelper = $this->getView()->plugin($helperName);
        foreach ($this->scripts[$position] as $script) {
            if ($script['allowArbitraryAttrs'] ?? false) {
                $scriptHelper->setAllowArbitraryAttributes(true);
            }
            // Every $script will have either a script attribute (inline JS) or a src attribute (file):
            if (isset($script['script'])) {
                $scriptHelper->appendScript($script['script'], $script['type'], $script['attrs']);
            } else {
                $scriptHelper->appendFile($script['src'], $script['type'], $script['attrs']);
            }
        }
        return ($scriptHelper)();
    }

    /**
     * Get the path to the directory where we can cache files generated by
     * this trait. The directory will be created if it does not already exist.
     *
     * @return string
     */
    protected function getResourceCacheDir(): string
    {
        if (!defined('LOCAL_CACHE_DIR')) {
            throw new \Exception(
                'Asset pipeline feature depends on the LOCAL_CACHE_DIR constant.'
            );
        }
        // TODO: it might be better to use \VuFind\Cache\Manager here.
        $cacheDir = LOCAL_CACHE_DIR . '/public/';
        if (!is_dir($cacheDir) && !file_exists($cacheDir)) {
            if (!mkdir($cacheDir)) {
                throw new \Exception("Unexpected problem creating cache directory: $cacheDir");
            }
        }
        return $cacheDir;
    }

    /**
     * Determine whether the asset is exempt from concatenation.
     *
     * @param array  $item Asset
     * @param string $type Type of asset (css or js)
     *
     * @return bool
     * @throws Exception
     */
    protected function isExcludedFromConcat(array $item, string $type): bool
    {
        if ($type === 'css') {
            return !$this->isRelativePath($item['href']);
        }
        throw new Exception("Unknown type: $type");
    }

    /**
     * Extract the file path from an asset.
     *
     * @param array  $item Asset
     * @param string $type Type of asset (css or js)
     *
     * @return string
     * @throws Exception
     */
    protected function getResourceFilePath(array $item, string $type): string
    {
        if ($type === 'css') {
            return $item['href'];
        }
        throw new Exception("Unknown type: $type");
    }

    /**
     * Get the group identification key for a specific asset.
     *
     * @param array  $item Asset
     * @param string $type Type of asset (css or js)
     *
     * @return string
     * @throws Exception
     */
    protected function getGroupType(array $item, string $type): string
    {
        if ($type === 'css') {
            $groupType = $item['media'] ?? 'all';
            if (isset($item['conditionalStylesheet'])) {
                $type .= '_' . $item['conditionalStylesheet'];
            }
        } else {
            throw new Exception("Unknown type: $type");
        }
        return $groupType;
    }

    /**
     * Sort assets into groups that can be collapsed using a minifier.
     *
     * @param array  $assets Assets to group
     * @param string $type   Type of assets (css or js)
     *
     * @return array
     * @throws Exception
     */
    protected function groupAssets(array $assets, string $type): array
    {
        $groups = [];
        $groupTypeIndex = [];

        foreach ($assets as $item) {
            if ($this->isExcludedFromConcat($item, $type)) {
                $groups[] = [
                    'other' => true,
                    'item' => $item,
                ];
                continue;
            }

            $path = $type . '/' . $this->getResourceFilePath($item, $type);
            $details = $this->themeInfo->findContainingTheme(
                $path,
                ThemeInfo::RETURN_ALL_DETAILS
            );
            // Deal with special case: $path was not found in any theme.
            if (null === $details) {
                $errorMsg = "Could not find file '$path' in theme files";
                $this->logError($errorMsg);
                $groups[] = [
                    'other' => true,
                    'item' => $item,
                ];
                continue;
            }

            $groupType = $this->getGroupType($item, $type);
            $index = $groupTypeIndex[$groupType] ?? false;
            if ($index === false) {
                $groupTypeIndex[$groupType] = count($groups);
                $groups[] = [
                    'items' => [$item],
                    'key' => $details['path'] . filemtime($details['path']),
                ];
            } else {
                $groups[$index]['items'][] = $item;
                $groups[$index]['key'] .= $details['path'] . filemtime($details['path']);
            }
        }

        return $groups;
    }

    /**
     * Check if a file is minifiable i.e. does not have a pattern that denotes it's
     * already minified
     *
     * @param string $filename File name
     *
     * @return bool
     */
    protected function isMinifiable(string $filename): bool
    {
        $basename = basename($filename);
        return preg_match('/\.min\.(js|css)/', $basename) === 0;
    }

    /**
     * Get the minifier object for the specified file type.
     *
     * @param string $type Type of assets (css or js)
     *
     * @return Minify
     * @throws Exception
     */
    protected function getMinifier(string $type): Minify
    {
        $minifier = match ($type) {
            'css' => new \VuFindTheme\Minify\CSS(),
            default => null
        };
        if (!$minifier) {
            throw new Exception("Unsupported type $type");
        }
        if (null !== $this->maxImportSize) {
            $minifier->setMaxImportSize($this->maxImportSize);
        }
        return $minifier;
    }

    /**
     * Get minified data for a file
     *
     * @param array  $details    File details
     * @param string $concatPath Target path for the resulting file (used in minifier
     * for path mapping)
     * @param string $type       Type of assets (css or js)
     *
     * @throws \Exception
     * @return string
     */
    protected function getMinifiedData(array $details, string $concatPath, string $type): string
    {
        if ($this->isMinifiable($details['path'])) {
            $minifier = $this->getMinifier($type);
            $minifier->add($details['path']);
            $data = $minifier->execute($concatPath);
        } else {
            $data = file_get_contents($details['path']);
            if (false === $data) {
                throw new \Exception(
                    "Could not read file {$details['path']}"
                );
            }
        }
        return $data;
    }

    /**
     * Create a concatenated file from the given group of files
     *
     * @param string $concatPath Resulting file path
     * @param array  $group      Object containing 'key' and stdobj file 'items'
     * @param string $type       Type of assets (css or js)
     *
     * @throws \Exception
     * @return void
     */
    protected function createConcatenatedFile(string $concatPath, array $group, string $type): void
    {
        $data = [];
        foreach ($group['items'] as $item) {
            $details = $this->themeInfo->findContainingTheme(
                $type . '/' . $this->getResourceFilePath($item, $type),
                ThemeInfo::RETURN_ALL_DETAILS
            );
            $details['path'] = realpath($details['path']);
            $data[] = $this->getMinifiedData($details, $concatPath, $type);
        }
        // Separate each file's data with a new line so that e.g. a file
        // ending in a comment doesn't cause the next one to also get commented out.
        file_put_contents($concatPath, implode("\n", $data));
    }

    /**
     * Using the concatKey, return the path of the concatenated file.
     * Generate if it does not yet exist.
     *
     * @param array  $group Grouped assets
     * @param string $type  Type of assets (css or js)
     *
     * @return string
     */
    protected function getConcatenatedFilePath(array $group, string $type): string
    {
        $urlHelper = $this->getView()->plugin('url');

        // Don't recompress individual files
        if (count($group['items']) === 1) {
            $path = $this->getResourceFilePath($group['items'][0], $type);
            $details = $this->themeInfo->findContainingTheme(
                $type . '/' . $path,
                ThemeInfo::RETURN_ALL_DETAILS
            );
            return $urlHelper('home') . 'themes/' . $details['theme']
                . '/' . $type . '/' . $path;
        }
        // Locate/create concatenated asset file
        $filename = md5($group['key']) . '.min.' . $type;
        // Minifier uses realpath, so do that here too to make sure we're not
        // pointing to a symlink. Otherwise the path converter won't find the correct
        // shared directory part.
        $concatPath = realpath($this->getResourceCacheDir()) . '/' . $filename;
        if (!file_exists($concatPath)) {
            $lockfile = "$concatPath.lock";
            $handle = fopen($lockfile, 'c+');
            if (!is_resource($handle)) {
                throw new \Exception("Could not open lock file $lockfile");
            }
            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                throw new \Exception("Could not lock file $lockfile");
            }
            // Check again if file exists after acquiring the lock
            if (!file_exists($concatPath)) {
                try {
                    $this->createConcatenatedFile($concatPath, $group, $type);
                } catch (\Exception $e) {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    throw $e;
                }
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $urlHelper('home') . 'cache/' . $filename;
    }

    /**
     * Get the key name from the asset array where a filename/path can be set.
     *
     * @param string $type Type of assets (css or js)
     *
     * @return string
     * @throws Exception
     */
    protected function getFileKeyByType(string $type): string
    {
        if ($type === 'css') {
            return 'href';
        }
        throw new Exception("Unexpected type: $type");
    }

    /**
     * Turn the output of groupAssets() into an array suitable for input to the view helpers.
     *
     * @param array  $groups Grouped assets returned by groupAssets()
     * @param string $type   Type of assets (css or js)
     *
     * @return array
     * @throws Exception
     */
    protected function processGroupedAssets(array $groups, string $type): array
    {
        $assets = [];

        foreach ($groups as $group) {
            if (isset($group['other'])) {
                $assets[] = $group['item'];
            } else {
                $item = $group['items'][0];
                $item[$this->getFileKeyByType($type)] = $this->getConcatenatedFilePath($group, $type);
                $assets[] = $item;
            }
        }

        return $assets;
    }

    /**
     * Process an array of assets through the pipeline.
     *
     * @param array  $assets Assets to process
     * @param string $type   Type of assets (css or js)
     *
     * @return array
     * @throws Exception
     */
    protected function processForPipeline(array $assets, string $type): array
    {
        if (!$this->isPipelineEnabledForType($type) || !$this->isPipelineActive()) {
            return $assets;
        }

        $groupedAssets = $this->groupAssets($assets, $type);
        return $this->processGroupedAssets($groupedAssets, $type);
    }

    /**
     * Return the HTML to output style assets.
     *
     * @return string
     */
    protected function outputStyleAssets(): string
    {
        $headLink = $this->getView()->plugin('headLink');
        $processedStylesheets = $this->processForPipeline($this->stylesheets, 'css');
        foreach ($processedStylesheets as $sheet) {
            // Account for the theme system (when appropriate):
            if ($this->isRelativePath($sheet['href'])) {
                $relPath = 'css/' . $sheet['href'];
                $details = $this->themeInfo->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);
                if (!empty($details)) {
                    $urlHelper = $this->getView()->plugin('url');
                    $url = $urlHelper('home') . "themes/{$details['theme']}/" . $relPath;
                    $url .= strstr($url, '?') ? '&_=' : '?_=';
                    $url .= filemtime($details['path']);
                    $sheet['href'] = $url;
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
     * Output the collected assets for the footer.
     *
     * @return string
     */
    public function outputFooterAssets(): string
    {
        return $this->outputScriptAssets('footer');
    }
}
