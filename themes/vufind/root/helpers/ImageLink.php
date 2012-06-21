<?php
/**
 * Image link view helper (extended for VuFind's theme system)
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Image link view helper (extended for VuFind's theme system)
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VuFind_Theme_Root_Helper_ImageLink extends Zend_View_Helper_Abstract
{
    /**
     * Returns an image path according the configured theme
     *
     * @param string $image image name/path
     *
     * @return string path, null if image not found
     */
    public function imageLink($image)
    {
        // Normalize href to account for themes, then call the parent class:
        $session = new Zend_Session_Namespace('Theme');

        $currentTheme = $session->currentTheme;

        while (!empty($currentTheme) &&
            !file_exists(
                APPLICATION_PATH .
                "/themes/$currentTheme/images/{$image}"
            )
        ) {
            $currentTheme = $session->allThemeInfo[$currentTheme]->extends;
        }

        if (!empty($currentTheme)) {
            return Zend_Controller_Front::getInstance()->getBaseUrl() .
                "/themes/$currentTheme/images/" . $image;
        }

        // Image not found!
        return null;
    }
}