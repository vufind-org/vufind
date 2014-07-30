<?php
/**
 * Helper class for displaying search-related HTML chunks.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Bootstrap3;

/**
 * Helper class for displaying search-related HTML chunks.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * @param string                          $url  Link href
     * @param \Zend\View\Renderer\PhpRenderer $view View renderer object
     *
     * @return string
     */
    protected function renderExpandLink($url, $view)
    {
        return '<a href="' . $url . '" title="' . $view->transEsc('spell_expand_alt')
            . '"><i class="icon-circle-arrow-right"></i></a>';
    }
}