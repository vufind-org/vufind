<?php
/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTheme\View\Helper;
use VuFindTheme\ThemeInfo;

/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
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

     * @return void
     */
    abstract protected function setResourceFilePath($item, $path);

    /**
     * Get the minifier that can handle these file types
     *
     * @return minifying object like \MatthiasMullie\Minify\JS
     */
    abstract protected function getMinifier();

    /**
     * Set the file path of the link object
     *
     * @param stdClass $item Link element object
     *
     * @return string
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
     * Check if config is enamled for this file type
     *
     * @param string $config Config for current application environment
     *
     * @return boolean
     */
    protected function enabledInConfig($config)
    {
        if ($config === false || $config == 'off') {
            return false;
        }
        if ($config == '*' || $config == 'on'
            || $config == 'true' || $config === true
        ) {
            return true;
        }
        $settings = array_map('trim', explode(',', $config));
        return in_array($this->getFileType(), $settings);
    }

    /**
     * Initialize class properties related to concatenation of resources.
     * All of the elements to be concatenated into ($this->concatItems)
     * and those that need to remain on their own ($this->otherItems).
     *
     * @return void
     */
    protected function filterItems()
    {
        $this->groups = [];
        $groupTypes = [];

        $this->getContainer()->ksort();

        foreach ($this as $key => $item) {
            if ($this->isExcludedFromConcat($item)) {
                $this->groups[] = [
                    'other' => true,
                    'item' => $item
                ];
                $groupTypes[] = 'other';
                continue;
            }

            $details = $this->themeInfo->findContainingTheme(
                $this->getFileType() . '/' . $this->getResourceFilePath($item),
                ThemeInfo::RETURN_ALL_DETAILS
            );

            $type = $this->getType($item);
            $index = array_search($type, $groupTypes);
            if ($index === false) {
                $this->groups[] = [
                    'items' => [$item],
                    'key' => $details['path'] . filemtime($details['path'])
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
     * this trait.
     *
     * @return string
     */
    protected function getResourceCacheDir()
    {
        return $this->themeInfo->getBaseDir() . '/../local/cache/public/';
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
        $concatPath = $this->getResourceCacheDir() . $filename;
        if (!file_exists($concatPath)) {
            $minifier = $this->getMinifier();
            foreach ($group['items'] as $item) {
                $details = $this->themeInfo->findContainingTheme(
                    $this->getFileType() . '/' . $this->getResourceFilePath($item),
                    ThemeInfo::RETURN_ALL_DETAILS
                );
                $minifier->add($details['path']);
            }
            $minifier->minify($concatPath);
        }

        return $urlHelper('home') . 'cache/' . $filename;
    }

    /**
     * Process and return items in index order
     *
     * @param string|int $indent Amount of whitespace/string to use for indention
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
            $useCdata = $this->useCdata;
        }

        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';

        $output = [];
        foreach ($this->groups as $group) {
            if (isset($group['other'])) {
                $output[] = $this->itemToString(
                    $group['item'], $indent, $escapeStart, $escapeEnd
                );
            } else {
                // Note that we  use parent::itemToString() below instead of
                // $this->itemToString() to bypass VuFind logic that determines
                // file paths within the theme (not appropriate for concatenated
                // files, which are stored in a theme-independent cache).
                $path = $this->getConcatenatedFilePath($group);
                $item = $this->setResourceFilePath($group['items'][0], $path);
                $output[] = parent::itemToString(
                    $item, $indent, $escapeStart, $escapeEnd
                );
            }
        }

        return $indent . implode(
            $this->escape($this->getSeparator()) . $indent, $output
        );
    }

    /**
     * Can we use the asset pipeline?
     *
     * @return bool
     */
    protected function isPipelineActive()
    {
        if ($this->usePipeline) {
            $cacheDir = $this->getResourceCacheDir();
            if (!is_writable($cacheDir)) {
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
     * @param string|int $indent Amount of whitespace or string to use for indention
     *
     * @return string
     */
    public function toString($indent = null)
    {
        // toString must not throw exception
        try {
            if (!$this->isPipelineActive() || !$this->filterItems()
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
