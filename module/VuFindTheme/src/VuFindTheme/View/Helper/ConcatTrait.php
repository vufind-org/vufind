<?php

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTheme\View\Helper;
use VuFindTheme\ThemeInfo;

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ConcatTrait
{
    /**
     * Divide all of the elements into those to be concaninated ($concatItems) and
     * those that need to remain on their own ($otherItems). Return by reference.
     *
     * @param string    $concatkey   String of all filenames and mod dates
     * @param array     $concatItems Items to be concaninated
     * @param array     $otherItems  Items to be rendered separately
     * @param stdObject $template    Element object to be adapted later
     * @param integer   $templateKey Future order of the concatinated file
     * @param integer   $keyLimit    Number of files to be out in order
     *
     * @return void
     */
    protected function filterItems(
        &$concatkey, &$concatItems, &$otherItems,
        &$template, &$templateKey, &$keyLimit
    ) {
        $this->getContainer()->ksort();

        foreach ($this as $key => $item) {
            if ($key > $keyLimit) {
                $keyLimit = $key;
            }
            if ($this->isResourceOtherItem($item)) {
                $otherItems[$key] = $item;
                continue;
            }
            if ($template == null) {
                $template = $item;
                $templateKey = $key;
            }

            $details = $this->themeInfo->findContainingTheme(
                $this->fileType . '/' . $this->getResourceFilePath($item),
                ThemeInfo::RETURN_ALL_DETAILS
            );

            $concatkey .= $details['path'] . filemtime($details['path']);
            $concatItems[] = $details['path'];
        }
    }

    /**
     * Process and return items in index order
     *
     * @param \stdClass  $concat    Concatinated data item
     * @param string     $concatkey Index of the concaninated file
     * @param array      $other     Concatination-excempt data items, keyed by index
     * @param string|int $limit     Highest index present
     * @param string|int $indent    Amount of whitespace/string to use for indention
     *
     * @return string
     */
    protected function outputInOrder($concat, $concatkey, $other, $limit, $indent)
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
        for ($i = 0; $i <= $limit; $i++) {
            if ($i == $concatkey) {
                $output[] = parent::itemToString(
                    $concat, $indent, $escapeStart, $escapeEnd
                );
            }
            if (isset($other[$i])) {
                $output[] = $this->itemToString(
                    $other[$i], $indent, $escapeStart, $escapeEnd
                );
            }
        }

        return $indent . implode(
            $this->escape($this->getSeparator()) . $indent, $output
        );
    }

    /**
     * Render link elements as string
     * Customized to minify and concatinate
     *
     * @param string|int $indent Amount of whitespace or string to use for indention
     *
     * @return string
     */
    public function toString($indent = null)
    {
        // toString must not throw exception
        try {

            $concatkey = '';
            $concatItems = [];
            $otherItems = [];
            $template = null; // template object for our concatinated file
            $templateKey = 0;
            $keyLimit = 0;

            // Returned by reference
            $this->filterItems(
                $concatkey, $concatItems, $otherItems,
                $template, $templateKey, $keyLimit
            );

            if (empty($concatItems)) {
                return parent::toString($indent);
            }

            // Locate/create concatinated css file
            $relPath = '/' . $this->themeInfo->getTheme()
                . '/' . $this->fileType . '/concat/'
                . md5($concatkey) . '.min.' . $this->fileType;
            $concatPath = $this->themeInfo->getBaseDir() . $relPath;
            if (!file_exists($concatPath)) {
                $minifier = $this->getMinifier();
                for ($i = 0; $i < count($concatItems); $i++) {
                    $minifier->add($concatItems[$i]);
                }
                $minifier->minify($concatPath);
            }

            // Transform template sheet object into concat sheet object
            $urlHelper = $this->getView()->plugin('url');
            $this->setResourceFilePath(
                $template, $urlHelper('home') . 'themes' . $relPath
            );

            return $this->outputInOrder(
                $template, $templateKey, $otherItems, $keyLimit, $indent
            );

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
