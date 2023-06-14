<?php

/**
 * Inline script view helper (extended for VuFind's theme system)
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme\View\Helper;

/**
 * Inline script view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InlineScript extends HeadScript
{
    /**
     * Return InlineScript object
     *
     * Returns InlineScript helper object; optionally, allows specifying a
     * script or script file to include.
     *
     * @param string $mode      Script or file
     * @param string $spec      Script/url
     * @param string $placement Append, prepend, or set
     * @param array  $attrs     Array of script attributes
     * @param string $type      Script type and/or array of script attributes
     *
     * @return InlineScript
     */
    public function __invoke(
        $mode = HeadScript::FILE,
        $spec = null,
        $placement = 'APPEND',
        array $attrs = [],
        $type = 'text/javascript'
    ) {
        return parent::__invoke($mode, $spec, $placement, $attrs, $type);
    }
}
