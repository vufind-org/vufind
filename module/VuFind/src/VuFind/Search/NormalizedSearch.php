<?php

/**
 * Normalized search object.
 *
 * PHP version 8
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

use function get_class;

/**
 * Normalized search object.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NormalizedSearch
{
    /**
     * Search results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Raw search object provided to constructor
     *
     * @var Results
     */
    protected $raw;

    /**
     * Minified version of search
     *
     * @var Minified
     */
    protected $minified;

    /**
     * Normalized search object
     *
     * @var Results
     */
    protected $normalized;

    /**
     * Search URL from normalized search object
     *
     * @var string
     */
    protected $url;

    /**
     * Checksum of normalized search URL
     *
     * @var string
     */
    protected $checksum;

    /**
     * Constructor
     *
     * @param ResultsManager $resultsManager ResultsManager
     * @param Results        $results        Search results object
     */
    public function __construct(ResultsManager $resultsManager, Results $results)
    {
        $this->resultsManager = $resultsManager;
        $this->raw = $results;
        // Normalize the URL params by minifying and deminifying the search object;
        // note that we use the "minSO" subclass of the Minified class so that it
        // serializes as small as possible in the database.
        $this->minified = new minSO($results);
        $this->normalized = $this->minified->deminify($resultsManager);
        $this->url = $this->normalized->getUrlQuery()->getParams();
        // Use crc32 as the checksum but get rid of highest bit so that we don't
        // need to care about signed/unsigned issues
        // (note: the checksum doesn't need to be unique)
        $this->checksum = crc32($this->url) & 0xFFFFFFF;
    }

    /**
     * Get raw search object provided to constructor.
     *
     * @return Results
     */
    public function getRawResults(): Results
    {
        return $this->raw;
    }

    /**
     * Get minified version of search.
     *
     * @return Minified
     */
    public function getMinified(): Minified
    {
        return $this->minified;
    }

    /**
     * Get normalized version of search object.
     *
     * @return Results
     */
    public function getNormalizedResults(): Results
    {
        return $this->normalized;
    }

    /**
     * Get search URL from normalized search object.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get checksum of normalized search URL.
     *
     * @return string
     */
    public function getChecksum(): string
    {
        return $this->checksum;
    }

    /**
     * Is this search equivalent to the provided minified search?
     *
     * @param Minified $otherSearch Search to compare against
     *
     * @return bool
     */
    public function isEquivalentToMinifiedSearch(Minified $otherSearch): bool
    {
        // Deminify the other search:
        $searchToCheck = $otherSearch->deminify($this->resultsManager);
        // Check if classes and URLs match:
        return $searchToCheck::class === get_class($this->raw)
            && $this->url === $searchToCheck->getUrlQuery()->getParams();
    }
}
