<?php
/**
 * Head link view helper (extended for VuFind's theme system)
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_HeadLink extends Zend_View_Helper_HeadLink
{
    /**
     * Create HTML link element from data item
     *
     * @param stdClass $item data item
     *
     * @return string
     */
    public function itemToString(stdClass $item)
    {
        // Normalize href to account for themes, then call the parent class:
        $session = new Zend_Session_Namespace('Theme');
        
        $currentTheme = $session->currentTheme;
        
        while (!empty($currentTheme) &&
            !file_exists(
                APPLICATION_PATH .
                "/themes/$currentTheme/css/{$item->href}"
            )
        ) {
            $currentTheme = $session->allThemeInfo[$currentTheme]->extends;
        }

        if (!empty($currentTheme)) {
            $item->href = Zend_Controller_Front::getInstance()->getBaseUrl() .
                "/themes/$currentTheme/css/" . $item->href;
        }
        
        return parent::itemToString($item);
    }
}