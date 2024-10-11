<?php

/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindTheme\View\Helper;

use VuFindTheme\ThemeInfo;

use function count;
use function defined;
use function in_array;
use function is_resource;

/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ConcatTrait
{
    /**
     * Returns true if file should not be included in the compressed concat file
     *
     * @param stdClass $item Element object
     *
     * @return bool
     */
    abstract protected function isExcludedFromConcat($item);

    /**
     * Get the folder name and file extension
     *
     * @return string
     */
    abstract protected function getFileType();

    /**
     * Get the file path from the element object
     *
     * @param stdClass $item Element object
     *
     * @return string
     */
    abstract protected function getResourceFilePath($item);

    /**
     * Set the file path of the element object
     *
     * @param stdClass $item Element object
     * @param string   $path New path string
     *
     * @return stdClass
     */
    abstract protected function setResourceFilePath($item, $path);

    /**
     * Get the minifier that can handle these file types
     *
     * @return minifying object like \MatthiasMullie\Minify\JS
     */
    abstract protected function getMinifier();

    /**
     * Add a content security policy nonce to the item
     *
     * @param stdClass $item Item
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function addNonce($item)
    {
        // Default implementation does nothing
    }

    /**
     * Set the file path of the link object
     *
     * @param stdClass $item Link element object
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getType($item)
    {
        return 'default';
    }

    /**
     * Should we use the asset pipeline to join files together and minify them?
     *
     * @var bool
     */
    protected $usePipeline = false;

    /**
     * Array of resource items by type, contains key as well
     *
     * @var array
     */
    protected $groups = [];

    /**
     * Future order of the concatenated file
     *
     * @var number
     */
    protected $concatIndex = null;

    /**
     * Check if config is enabled for this file type
     *
     * @param string|bool $config Config for current application environment
     *
     * @return bool
     */
    protected function enabledInConfig($config)
    {
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
        return in_array($this->getFileType(), $settings);
    }

    /**
     * Initialize class properties related to concatenation of resources.
     * All of the elements to be concatenated into groups and
     * and those that need to remain on their own special group 'other'.
     *
     * @return bool True if there are items
     */
    protected function filterItems()
    {
        $this->groups = [];
        $groupTypes = [];

        $this->getContainer()->ksort();

        foreach ($this as $item) {
            if ($this->isExcludedFromConcat($item)) {
                $this->groups[] = [
                    'other' => true,
                    'item' => $item,
                ];
                $groupTypes[] = 'other';
                continue;
            }

            $path = $this->getFileType() . '/' . $this->getResourceFilePath($item);
            $details = $this->themeInfo->findContainingTheme(
                $path,
                ThemeInfo::RETURN_ALL_DETAILS
            );
            // Deal with special case: $path was not found in any theme.
            if (null === $details) {
                $errorMsg = "Could not find file '$path' in theme files";
                method_exists($this, 'logError')
                    ? $this->logError($errorMsg) : error_log($errorMsg);
                $this->groups[] = [
                    'other' => true,
                    'item' => $item,
                ];
                $groupTypes[] = 'other';
                continue;
            }

            $type = $this->getType($item);
            $index = array_search($type, $groupTypes);
            if ($index === false) {
                $this->groups[] = [
                    'items' => [$item],
                    'key' => $details['path'] . filemtime($details['path']),
                ];
                $groupTypes[] = $type;
            } else {
                $this->groups[$index]['items'][] = $item;
                $this->groups[$index]['key'] .=
                    $details['path'] . filemtime($details['path']);
            }
        }

        return count($groupTypes) > 0;
    }

    /**
     * Get the path to the directory where we can cache files generated by
     * this trait. The directory will be created if it does not already exist.
     *
     * @return string
     */
    protected function getResourceCacheDir()
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
     * Using the concatKey, return the path of the concatenated file.
     * Generate if it does not yet exist.
     *
     * @param array $group Object containing 'key' and stdobj file 'items'
     *
     * @return string
     */
    protected function getConcatenatedFilePath($group)
    {
        $urlHelper = $this->getView()->plugin('url');

        // Don't recompress individual files
        if (count($group['items']) === 1) {
            $path = $this->getResourceFilePath($group['items'][0]);
            $details = $this->themeInfo->findContainingTheme(
                $this->getFileType() . '/' . $path,
                ThemeInfo::RETURN_ALL_DETAILS
            );
            return $urlHelper('home') . 'themes/' . $details['theme']
                . '/' . $this->getFileType() . '/' . $path;
        }
        // Locate/create concatenated asset file
        $filename = md5($group['key']) . '.min.' . $this->getFileType();
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
                    $this->createConcatenatedFile($concatPath, $group);
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
     * Create a concatenated file from the given group of files
     *
     * @param string $concatPath Resulting file path
     * @param array  $group      Object containing 'key' and stdobj file 'items'
     *
     * @throws \Exception
     * @return void
     */
    protected function createConcatenatedFile($concatPath, $group)
    {
        $data = [];
        foreach ($group['items'] as $item) {
            $details = $this->themeInfo->findContainingTheme(
                $this->getFileType() . '/'
                . $this->getResourceFilePath($item),
                ThemeInfo::RETURN_ALL_DETAILS
            );
            $details['path'] = realpath($details['path']);
            $data[] = $this->getMinifiedData($details, $concatPath);
        }
        // Separate each file's data with a new line so that e.g. a file
        // ending in a comment doesn't cause the next one to also get commented out.
        file_put_contents($concatPath, implode("\n", $data));
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
        if ($this->isMinifiable($details['path'])) {
            $minifier = $this->getMinifier();
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
     * Process and return items in index order
     *
     * @param string|int $indent Amount of whitespace/string to use for indentation
     *
     * @return string
     */
    protected function outputInOrder($indent)
    {
        // Some of this logic was copied from HeadScript; it does not all apply
        // when incorporated into HeadLink, but it has no harmful side effects.
        $indent = (null !== $indent)
            ? $this->getWhitespace($indent)
            : $this->getIndent();

        if ($this->view) {
            $useCdata = $this->view->plugin('doctype')->isXhtml();
        } else {
            $useCdata = $this->useCdata ?? false;
        }

        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';

        $output = [];
        foreach ($this->groups as $group) {
            if (isset($group['other'])) {
                /**
                 * PHPStan doesn't like this because of incompatible itemToString
                 * signatures in HeadLink/HeadScript, but it is safe to use because
                 * the extra parameters will be ignored appropriately.
                 *
                 * @phpstan-ignore-next-line
                 */
                $output[] = $this->itemToString(
                    $group['item'],
                    $indent,
                    $escapeStart,
                    $escapeEnd
                );
            } else {
                // Note that we  use parent::itemToString() below instead of
                // $this->itemToString() to bypass VuFind logic that determines
                // file paths within the theme (not appropriate for concatenated
                // files, which are stored in a theme-independent cache).
                $path = $this->getConcatenatedFilePath($group);
                $item = $this->setResourceFilePath($group['items'][0], $path);
                $this->addNonce($item);
                /**
                 * PHPStan doesn't like this because of incompatible itemToString
                 * signatures in HeadLink/HeadScript, but it is safe to use because
                 * the extra parameters will be ignored appropriately.
                 *
                 * @phpstan-ignore-next-line
                 */
                $output[] = parent::itemToString(
                    $item,
                    $indent,
                    $escapeStart,
                    $escapeEnd
                );
            }
        }

        return $indent . implode(
            $this->escape($this->getSeparator()) . $indent,
            $output
        );
    }

    /**
     * Check if a file is minifiable i.e. does not have a pattern that denotes it's
     * already minified
     *
     * @param string $filename File name
     *
     * @return bool
     */
    protected function isMinifiable($filename)
    {
        $basename = basename($filename);
        return preg_match('/\.min\.(js|css)/', $basename) === 0;
    }

    /**
     * Can we use the asset pipeline?
     *
     * @return bool
     */
    protected function isPipelineActive()
    {
        if ($this->usePipeline) {
            try {
                $cacheDir = $this->getResourceCacheDir();
            } catch (\Exception $e) {
                $this->usePipeline = $cacheDir = false;
                error_log($e->getMessage());
            }
            if ($cacheDir && !is_writable($cacheDir)) {
                $this->usePipeline = false;
                error_log("Cannot write to $cacheDir; disabling asset pipeline.");
            }
        }
        return $this->usePipeline;
    }

    /**
     * Render link elements as string
     * Customized to minify and concatenate
     *
     * @param string|int $indent Amount of whitespace or string to use for indentation
     *
     * @return string
     */
    public function toString($indent = null)
    {
        // toString must not throw exception
        try {
            if (
                !$this->isPipelineActive() || !$this->filterItems()
                || count($this) == 1
            ) {
                return parent::toString($indent);
            }

            return $this->outputInOrder($indent);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return '';
    }
}
