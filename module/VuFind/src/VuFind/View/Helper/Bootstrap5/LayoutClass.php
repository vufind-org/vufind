<?php

/**
 * Helper class for managing bootstrap theme's high-level (body vs. sidebar) page
 * layout.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Bootstrap5;

/**
 * Helper class for managing bootstrap theme's high-level (body vs. sidebar) page
 * layout.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LayoutClass extends \VuFind\View\Helper\AbstractLayoutClass
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
        switch ($class) {
            case 'mainbody':
                if (!$hasSidebar) {
                    $side = $this->rtl ? 'right' : 'left';
                } else {
                    $side = $this->sidebarOnLeft ? 'right' : 'left';
                }
                return "mainbody $side";
            case 'sidebar':
                return $this->sidebarOnLeft
                    ? 'sidebar left hidden-print'
                    : 'sidebar right hidden-print';
            case 'offcanvas-row':
                if (!$this->offcanvas) {
                    return '';
                }
                return $this->sidebarOnLeft
                    ? 'vufind-offcanvas vufind-offcanvas-left'
                    : 'vufind-offcanvas vufind-offcanvas-right';
        }
        throw new \Exception('Unexpected class: ' . $class);
    }
}
