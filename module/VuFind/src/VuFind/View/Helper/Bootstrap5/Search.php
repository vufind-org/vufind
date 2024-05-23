<?php

/**
 * Helper class for displaying search-related HTML chunks.
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
 * Helper class for displaying search-related HTML chunks.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Search extends \VuFind\View\Helper\AbstractSearch
{
    /**
     * Get the CSS classes for the container holding the suggestions.
     *
     * @return string
     */
    protected function getContainerClass()
    {
        return 'alert alert-info';
    }

    /**
     * Render an expand link.
     *
     * @param string                             $url  Link href
     * @param \Laminas\View\Renderer\PhpRenderer $view View renderer object
     *
     * @return string
     */
    protected function renderExpandLink($url, $view)
    {
        return ' <a href="' . $url
            . '" title="' . $view->transEsc('spell_expand_alt')
            . '">(' . $view->transEsc('spell_expand_alt') . ')</a>';
    }
}
