<?php

/**
 * Primo Central connector interface.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Primo;

/**
 * Primo Central connector interface.
 *
 * @category VuFind
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
interface ConnectorInterface
{
    /**
     * Execute a search. Adds all the querystring parameters into
     * $this->client and returns the parsed response
     *
     * @param string $institution Institution
     * @param array  $terms       Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array  $params      Associative array of optional arguments:
     *     phrase      bool:   true if it's a quoted phrase (default false)
     *     onCampus    bool:   (default true)
     *     didyoumean  bool:   (default false)
     *     filterList  array:  (field, value) pairs to filter results (def null)
     *     pageNumber  string: index of first record (default 1)
     *     limit       string: number of records to return (default 20)
     *     sort        string: value to be used by for sorting (default null)
     *     highlight   bool:   whether to highlight search term matches in records
     *     highlightStart string: Prefix for a highlighted term
     *     highlightEnd   string: Suffix for a Highlighted term
     *     Anything in $params not listed here will be ignored.
     *
     * Note: some input parameters accepted by Primo are not implemented here:
     *  - dym (did you mean)
     *  - more (get more)
     *  - lang (specify input language so engine can do lang. recognition)
     *  - displayField (has to do with highlighting somehow)
     *
     * @throws \Exception
     * @return array             An array of query results
     *
     * @link http://www.exlibrisgroup.org/display/PrimoOI/Brief+Search
     */
    public function query($institution, $terms, $params = null);

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string  $recordId  The document to retrieve from the Primo API
     * @param ?string $inst_code Institution code (optional)
     * @param bool    $onCampus  Whether the user is on campus
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    public function getRecord(string $recordId, $inst_code = null, $onCampus = false);

    /**
     * Get the institution code based on user IP. If user is coming from
     * off campus return
     *
     * @return string
     */
    public function getInstitutionCode();
}
