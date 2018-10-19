<?php
/**
 * Image link view helper (extended for VuFind's theme system)
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTheme\View\Helper;

/**
 * Image link view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ImageLink extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Theme information service
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param \VuFindTheme\ThemeInfo $themeInfo Theme information service
     */
    public function __construct(\VuFindTheme\ThemeInfo $themeInfo)
    {
        $this->themeInfo = $themeInfo;
    }

    /**
     * Returns an image path according the configured theme
     *
     * @param string $image image name/path
     *
     * @return string path, null if image not found
     */
    public function __invoke($image)
    {
        // Normalize href to account for themes:
        $relPath = 'images/' . $image;
        $currentTheme = $this->themeInfo->findContainingTheme($relPath);

        if (null === $currentTheme) {
            return null;
        }

        $urlHelper = $this->getView()->plugin('url');
        return $urlHelper('home') . "themes/$currentTheme/" . $relPath;
    }
}
