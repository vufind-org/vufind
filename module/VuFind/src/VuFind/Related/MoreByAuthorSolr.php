<?php

/**
 * Related Records: Solr-based "more by author"
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace VuFind\Related;

use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\Query;

use function count;

/**
 * Related Records: Solr-based "more by author"
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class MoreByAuthorSolr implements RelatedInterface
{
    /**
     * Similar records
     *
     * @var array
     */
    protected array $results = [];

    /**
     * Author being searched
     *
     * @var string
     */
    protected string $author = '';

    /**
     * Maximum number of titles to suggest
     *
     * @var int
     */
    protected int $maxRecommendations = 5;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service $searchService Search service
     */
    public function __construct(protected \VuFindSearch\Service $searchService)
    {
    }

    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        $this->results = [];
        if ($this->author = $driver->tryMethod('getPrimaryAuthor')) {
            $queryStr = '"' . addcslashes($this->author, '"') . '"';
            $query = new Query($queryStr, 'Author');
            $command = new SearchCommand(DEFAULT_SEARCH_BACKEND, $query, 0, $this->maxRecommendations + 1);
            foreach ($this->searchService->invoke($command)->getResult() as $result) {
                if (count($this->results) >= $this->maxRecommendations) {
                    break;
                }
                if ($result->getUniqueID() != $driver->getUniqueID()) {
                    $this->results[] = $result;
                }
            }
        }
    }

    /**
     * Get name of author being searched for.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->author;
    }

    /**
     * Get an array of Record Driver objects representing items similar to the one
     * passed to the constructor.
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
