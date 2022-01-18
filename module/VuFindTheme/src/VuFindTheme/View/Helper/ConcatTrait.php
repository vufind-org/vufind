<?php
/**
 * Trait to add asset pipeline functionality (concatenation / minification) to
 * a HeadLink/HeadScript-style view helper.
 *
 * PHP version 7
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
     * Get the filesystem path for the provided resource file path
     *
     * @param string $path Path
     *
     * @return ?string
     */
    protected function getResourceFileFilesystemPath(string $path): ?string
    {
        $path = $this->getFileType() . '/' . $path;
        $details = $this->themeInfo->findContainingTheme(
            $path,
            ThemeInfo::RETURN_ALL_DETAILS
        );
        return realpath($details['path']) ?? null;
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

        return parent::getConcatenatedFilePath($group);
    }
}
