<?php

/**
 * Fetch alphabrowse data from the backend (currently only supported by Solr)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Command;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\ParamBag;

/**
 * Fetch alphabrowse data from the backend (currently only supported by Solr)
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class AlphabeticBrowseCommand extends CallMethodCommand
{
    /**
     * Constructor.
     *
     * @param string   $backend     Search backend identifier
     * @param string   $source      Name of index to search
     * @param string   $from        Starting point for browse results
     * @param int      $page        Result page to return (starts at 0)
     * @param int      $limit       Number of results to return on each page
     * @param ParamBag $params      Additional parameters
     * @param int      $offsetDelta Delta to use when calculating page
     * offset (useful for showing a few results above the highlighted row)
     */
    public function __construct(
        string $backend,
        $source,
        $from,
        $page,
        $limit = 20,
        $params = null,
        $offsetDelta = 0
    ) {
        parent::__construct(
            $backend,
            Backend::class, // we should define interface, if needed in more places
            'alphabeticBrowse',
            [$source, $from, $page, $limit, $params, $offsetDelta],
            $params,
            false
        );
    }
}
