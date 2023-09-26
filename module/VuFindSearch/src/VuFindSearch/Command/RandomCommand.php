<?php

/**
 * Return random records command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\QueryInterface;

use function in_array;

/**
 * Return random records command.
 *
 * @category VuFind
 * @package  Search
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RandomCommand extends CallMethodCommand
{
    /**
     * Search query.
     *
     * @var QueryInterface
     */
    protected $query;

    /**
     * Search limit.
     *
     * @var int
     */
    protected $limit;

    /**
     * RandomCommand constructor.
     *
     * @param string         $backendId Search backend identifier
     * @param QueryInterface $query     Search query
     * @param int            $limit     Search limit
     * @param ?ParamBag      $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        QueryInterface $query,
        int $limit,
        ?ParamBag $params = null
    ) {
        $this->query = $query;
        $this->limit = $limit;
        parent::__construct(
            $backendId,
            RandomInterface::class,
            'random',
            $params
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getQuery(),
            $this->getLimit(),
            $this->getSearchParameters(),
        ];
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        // If the backend implements the RetrieveRandomInterface, we can load
        // all the records at once.
        if ($backend instanceof RandomInterface) {
            return parent::execute($backend);
        }

        // Otherwise, we need to load them one at a time and aggregate them.

        $query = $this->getQuery();
        $limit = $this->getLimit();

        // offset/limit of 0 - we don't need records, just count
        $results = $backend->search($query, 0, 0, $this->params);
        $total_records = $results->getTotal();

        if (0 === $total_records) {
            // Empty result? Send back as-is:
            $response = $results;
        } elseif ($total_records < $limit) {
            // Result set smaller than limit? Get everything and shuffle:
            $response = $backend->search($query, 0, $limit, $this->params);
            $response->shuffle();
        } else {
            // Default case: retrieve n random records:
            $response = false;
            $retrievedIndexes = [];
            for ($i = 0; $i < $limit; $i++) {
                $nextIndex = rand(0, $total_records - 1);
                while (in_array($nextIndex, $retrievedIndexes)) {
                    // avoid duplicate records
                    $nextIndex = rand(0, $total_records - 1);
                }
                $retrievedIndexes[] = $nextIndex;
                $currentBatch = $backend->search(
                    $query,
                    $nextIndex,
                    1,
                    $this->params
                );
                if (!$response) {
                    $response = $currentBatch;
                } elseif ($record = $currentBatch->first()) {
                    $response->add($record);
                }
            }
        }

        return $this->finalizeExecution($response);
    }

    /**
     * Return search query.
     *
     * @return QueryInterface
     */
    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    /**
     * Return search limit.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
