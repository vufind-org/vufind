<?php

/**
 * Search query adapter interface
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search;

use Laminas\Stdlib\Parameters;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\WorkKeysQuery;

/**
 * Search query adapter interface
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface QueryAdapterInterface
{
    /**
     * Return a Query or QueryGroup based on minified search arguments.
     *
     * @param array $search Minified search arguments
     *
     * @return Query|QueryGroup|WorkKeysQuery
     */
    public function deminify(array $search);

    /**
     * Convert a Query or QueryGroup into a human-readable display query.
     *
     * @param AbstractQuery $query     Query to convert
     * @param callable      $translate Callback to translate strings
     * @param callable      $showName  Callback to translate field names
     *
     * @return string
     */
    public function display(AbstractQuery $query, $translate, $showName);

    /**
     * Convert user request parameters into a query (currently for advanced searches
     * and work keys searches only).
     *
     * @param Parameters $request        User-submitted parameters
     * @param string     $defaultHandler Default search handler
     *
     * @return Query|QueryGroup|WorkKeysQuery
     */
    public function fromRequest(Parameters $request, $defaultHandler);

    /**
     * Convert a Query or QueryGroup into minified search arguments.
     *
     * @param AbstractQuery $query    Query to minify
     * @param bool          $topLevel Is this a top-level query? (Used for recursion)
     *
     * @return array
     */
    public function minify(AbstractQuery $query, $topLevel = true);
}
