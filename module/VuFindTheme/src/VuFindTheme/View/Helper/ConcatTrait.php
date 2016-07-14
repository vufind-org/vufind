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
     * Required methods to use ConcatTrait
     *
     * * protected function isResourceOtherItem($item)
     * * Returns true if file should not be included in the compressed concat file
     * * - param stdClass $item Link element object
     * * - return bool
     *
     * * protected function getResourceFilePath($item)
     * * Get the file path from the link object
     * * - param stdClass $item Link element object
     * * - return string
     *
     * * protected function setResourceFilePath($item, $path)
     * * Set the file path of the link object
     * * - param stdClass $item Link element object
     * * - param string   $path New path string
     * * - return void
     *
     * * protected function getMinifier()
     * * Get the minifier that can handle these file types
     * * - return minifying object like \MatthiasMullie\Minify\JS
     */

    /**
     * Should we use the asset pipeline to join files together and minify them?
     *
     * @var bool
     */
    protected $usePipeline = false;

    /**
     * String of all filenames and mod dates
     *
     * @var string
     */
    protected $concatKey = '';

    /**
     * Items to be concatenated
     *
     * @var array
     */
    protected $concatItems = [];

    /**
     * Items to be rendered separately
     *
     * @var array
     */
    protected $otherItems = [];

    /**
     * Element object to be adapted later
     *
     * @var stdClass
     */
    protected $concatTemplate = null;

    /**
     * Future order of the concatenated file
     *
     * @var number
     */
    protected $concatIndex = 0;

    /**
     * Number of files to be out in order
     *
     * @var number
     */
    protected $keyLimit = 0;

    /**
     * Divide all of the elements into those to be concatenated ($this->concatItems) and
     * those that need to remain on their own ($otherItems). Return by reference.
     *
     * @return void
     */
    protected function filterItems()
    {
        $this->getContainer()->ksort();

        foreach ($this as $key => $item) {
            if ($key > $this->keyLimit) {
                $this->keyLimit = $key;
            }
            if ($this->isResourceOtherItem($item)) {
                $this->otherItems[$key] = $item;
                continue;
            }
            if ($this->concatTemplate == null) {
                $this->concatTemplate = $item;
                $this->concatIndex = $key;
            }

            $details = $this->themeInfo->findContainingTheme(
                $this->fileType . '/' . $this->getResourceFilePath($item),
                ThemeInfo::RETURN_ALL_DETAILS
            );

            $this->concatKey .= $details['path'] . filemtime($details['path']);
            $this->concatItems[] = $details['path'];
        }
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
        // Copied from parent
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
        for ($i = 0; $i <= $this->keyLimit; $i++) {
            if ($i == $this->concatIndex) {
                $output[] = parent::itemToString(
                    $this->concatTemplate, $indent, $escapeStart, $escapeEnd
                );
            }
            if (isset($this->otherItems[$i])) {
                $output[] = $this->itemToString(
                    $this->otherItems[$i], $indent, $escapeStart, $escapeEnd
                );
            }
        }

        return $indent . implode(
            $this->escape($this->getSeparator()) . $indent, $output
        );
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

            if (!$this->usePipeline) {
                return parent::toString($indent);
            }

            // Returned by reference
            $this->filterItems();

            if (empty($this->concatItems)) {
                return parent::toString($indent);
            }

            // Locate/create concatenated css file
            $relPath = '/' . $this->themeInfo->getTheme()
                . '/' . $this->fileType . '/concat/'
                . md5($this->concatKey) . '.min.' . $this->fileType;
            $concatPath = $this->themeInfo->getBaseDir() . $relPath;
            if (!file_exists($concatPath)) {
                $minifier = $this->getMinifier();
                for ($i = 0; $i < count($this->concatItems); $i++) {
                    $minifier->add($this->concatItems[$i]);
                }
                $minifier->minify($concatPath);
            }

            // Transform template sheet object into concat sheet object
            $urlHelper = $this->getView()->plugin('url');
            $this->setResourceFilePath(
                $this->concatTemplate, $urlHelper('home') . 'themes' . $relPath
            );

            return $this->outputInOrder($indent);

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
