<?php
/**
 * "Last search link" view helper
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
 * "Last search link" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_GetLastSearchLink extends Zend_View_Helper_Abstract
{
    /**
     * If a previous search is recorded in the session, return a link to it;
     * otherwise, return a blank string.
     *
     * @param string $link   Text to use as body of link
     * @param string $prefix Text to place in front of link
     * @param string $suffix Text to place after link
     *
     * @return string
     */
    public function getLastSearchLink($link, $prefix = '', $suffix = '')
    {
        $last = VF_Search_Memory::retrieve();
        if (!empty($last)) {
            return $prefix . '<a href="' . $this->view->escape($last) .
                '">' . $link . '</a>' . $suffix;
        }
        return '';
    }
}