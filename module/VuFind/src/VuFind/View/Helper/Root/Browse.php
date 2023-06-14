<?php

/**
 * Browse controller view helper
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Browse controller view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Browse extends AbstractHelper
{
    /**
     * Get the Solr field associated with a particular browse action.
     *
     * @param string $action Browse action
     * @param string $backup Backup browse action if no match is found for $action
     *
     * @return string
     */
    public function getSolrField($action, $backup = null)
    {
        $action = strtolower($action);
        $backup = strtolower($backup ?? '');
        switch ($action) {
            case 'dewey':
                return 'dewey-hundreds';
            case 'lcc':
                return 'callnumber-first';
            case 'author':
                return 'author_facet';
            case 'topic':
                return 'topic_facet';
            case 'genre':
                return 'genre_facet';
            case 'region':
                return 'geographic_facet';
            case 'era':
                return 'era_facet';
        }
        if (empty($backup)) {
            return $action;
        }
        return $this->getSolrField($backup);
    }
}
