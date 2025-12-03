<?php

/**
 * VuFind Helper - Reserves Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search;

use VuFind\ILS\Connection;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Service;

/**
 * Helper to perform reserves-related actions
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ReservesHelper
{
    /**
     * Do we need to use the Solr index for reserves (true) or the ILS driver
     * (false)?
     *
     * @var bool
     */
    protected bool $useIndex;

    /**
     * Constructor
     *
     * @param bool       $useIndex      Do we need to use the Solr index for reserves
     * (true) or the ILS driver (false)?
     * @param ?Service   $searchService Search service (only required when $useIndex
     * is true).
     * @param Connection $catalog       ILS connection
     */
    public function __construct(
        bool $useIndex,
        protected ?Service $searchService,
        protected Connection $catalog
    ) {
        
        $this->useIndex = $useIndex;
        if ($useIndex && null === $searchService) {
            throw new \Exception('Missing required search service');
        }
    }

    /**
     * Do we need to use the Solr index for reserves (true) or the ILS driver
     * (false)?
     *
     * @return bool
     */
    public function useIndex(): bool
    {
        return $this->useIndex;
    }

    /**
     * Get reserve info from the catalog or Solr reserves index.
     *
     * @param ?string $course Course ID to use as limit (optional)
     * @param ?string $inst   Instructor ID to use as limit (optional)
     * @param ?string $dept   Department ID to use as limit (optional)
     *
     * @return array
     */
    public function findReserves(?string $course = null, ?string $inst = null, ?string $dept = null): array
    {
        // Special case -- process reserves info using index
        if ($this->useIndex()) {
            // get the selected reserve record from reserves index
            // and extract the bib IDs from it
            $command = new RetrieveCommand(
                'SolrReserves',
                $course . '|' . $inst . '|' . $dept
            );
            $result = $this->searchService
                ->invoke($command)->getResult();
            $bibs = [];
            if ($result->getTotal() < 1) {
                return $bibs;
            }
            $record = current($result->getRecords());
            $instructor = $record->getInstructor();
            $course = $record->getCourse();
            foreach ($record->getItemIds() as $bib_id) {
                $bibs[] = [
                    'BIB_ID' => $bib_id,
                    'bib_id' => $bib_id,
                    'course' => $course,
                    'instructor' => $instructor,
                ];
            }
            return $bibs;
        }

        // Default case -- find reserves info from the catalog
        return $this->catalog->findReserves($course, $inst, $dept);
    }
}
