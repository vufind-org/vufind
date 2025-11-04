<?php

/**
 * Helper class for managing bootstrap theme's high-level (body vs. sidebar) page
 * layout.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
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
 * @link     https://vufind.org/wiki/development Wiki
 */
class LayoutClass extends \VuFind\View\Helper\Bootstrap5\LayoutClass
{
    /**
     * Helper to allow easily configurable page layout -- given a broad class
     * name, return appropriate CSS classes to lay out the page according to
     * the current configuration file settings.
     *
     * @param string $class      Type of class to return ('mainbody' or 'sidebar')
     * @param bool   $hasSidebar Whether sidebar is available
     *
     * @return string       CSS classes to apply
     */
    public function __invoke($class, $hasSidebar = true)
    {
        // Special styles for MyResearch to keep menu on left
        if ('mainbody-myresearch' === $class) {
            return 'mainbody right myresearch-body';
        } elseif ('sidebar-myresearch' === $class) {
            return 'sidebar left hidden-print sidebar-on-left';
        } elseif ('mainbody-myresearch-no-menu' === $class) {
            return 'mainbody myresearch-body';
        }

        $result = parent::__invoke($class, $hasSidebar);
        if ($class == 'sidebar' && $this->sidebarOnLeft) {
            $result .= ' sidebar-on-left';
        }
        return $result;
    }
}
