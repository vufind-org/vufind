<?php
/**
 * Search normalizer.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search;

use minSO;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * Search normalizer.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchNormalizer
{
    /**
     * Search results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param ResultsManager $resultsManager ResultsManager
     */
    public function __construct(ResultsManager $resultsManager)
    {
        $this->resultsManager = $resultsManager;
    }

    /**
     * Normalize a search
     *
     * @param Results $results Search results object
     *
     * @return NormalizedSearch
     */
    public function normalizeSearch(Results $results): NormalizedSearch
    {
        return new NormalizedSearch($this->resultsManager, $results);
    }

    /**
     * Normalize a minified search
     *
     * @param minSO $minified Minified search results object
     *
     * @return NormalizedSearch
     */
    public function normalizeMinifiedSearch(minSO $minified): NormalizedSearch
    {
        return $this->normalizeSearch($minified->deminify($this->resultsManager));
    }
}