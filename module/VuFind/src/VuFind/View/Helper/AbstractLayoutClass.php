<?php

/**
 * Helper class for managing high-level (body vs. sidebar) page layout.
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

namespace VuFind\View\Helper;

/**
 * Helper class for managing high-level (body vs. sidebar) page layout.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractLayoutClass extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param bool $sidebarOnLeft Does the sidebar go on the left?
     * @param bool $offcanvas     Is offcanvas menu active?
     * @param bool $rtl           Are we in right-to-left mode?
     */
    public function __construct(
        protected bool $sidebarOnLeft = false,
        protected bool $offcanvas = false,
        protected bool $rtl = false
    ) {
    }

    /**
     * Helper to allow easily configurable page layout -- given a broad class
     * name, return appropriate CSS classes to lay out the page according to
     * the current configuration file settings.
     *
     * @param string $class Type of class to return ('mainbody' or 'sidebar')
     *
     * @return string       CSS classes to apply
     */
    abstract public function __invoke($class);
}
