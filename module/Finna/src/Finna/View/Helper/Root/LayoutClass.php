<?php
/**
 * Helper class for managing bootstrap theme's high-level (body vs. sidebar) page
 * layout.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper class for managing bootstrap theme's high-level (body vs. sidebar) page
 * layout.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class LayoutClass extends \VuFind\View\Helper\Bootstrap3\LayoutClass
{
    /**
     * Helper to allow easily configurable page layout -- given a broad class
     * name, return appropriate CSS classes to lay out the page according to
     * the current configuration file settings.
     *
     * @param string $class Type of class to return ('mainbody' or 'sidebar')
     *
     * @return string       CSS classes to apply
     */
    public function __invoke($class)
    {
        // Special styles for MyResearch to keep menu on left
        if ('mainbody-myresearch' === $class) {
            return 'mainbody right myresearch-body';
        } elseif ('sidebar-myresearch' === $class) {
            return 'sidebar left hidden-print sidebar-on-left';
        } elseif ('mainbody-myresearch-no-menu' === $class) {
            return 'mainbody myresearch-body';
        }

        $result = parent::__invoke($class);
        if ($class == 'sidebar' && $this->sidebarOnLeft) {
            $result .= ' sidebar-on-left';
        }
        return $result;
    }
}
