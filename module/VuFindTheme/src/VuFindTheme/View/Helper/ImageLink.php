<?php

/**
 * Image link view helper (extended for VuFind's theme system).
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTheme\View\Helper;

use Laminas\View\Helper\Url;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Image link view helper (extended for VuFind's theme system).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ImageLink
{
    use RelativePathTrait;

    /**
     * Constructor.
     *
     * @param \VuFindTheme\ThemeInfo $themeInfo Theme information service
     * @param Url                    $urlHelper Url view helper
     */
    public function __construct(
        protected \VuFindTheme\ThemeInfo $themeInfo,
        #[Autowire(container: 'ViewHelperManager')]
        protected Url $urlHelper
    ) {
    }

    /**
     * Returns an image path according the configured theme.
     *
     * @param string $image image name/path
     *
     * @return string path, null if image not found
     */
    public function __invoke($image)
    {
        // If this is an absolute path, return it as-is:
        if (!$this->isRelativePath($image)) {
            return $image;
        }
        // Otherwise, normalize href to account for themes:
        $relPath = 'images/' . $image;
        $details = $this->themeInfo->findContainingTheme(
            $relPath,
            \VuFindTheme\ThemeInfo::RETURN_ALL_DETAILS
        );

        if (null === $details) {
            return null;
        }

        $parts = explode('/', $relPath);
        $encodedRelPath = implode('/', array_map('rawurlencode', $parts));
        $url = ($this->urlHelper)('home') . "themes/{$details['theme']}/" . $encodedRelPath;
        $url .= strstr($url, '?') ? '&_=' : '?_=';
        $url .= filemtime($details['path']);

        return $url;
    }
}
