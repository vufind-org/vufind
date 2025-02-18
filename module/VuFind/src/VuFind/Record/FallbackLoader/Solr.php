<?php

/**
 * Solr record fallback loader
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record\FallbackLoader;

use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\RecordIdUpdater;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Service;

/**
 * Solr record fallback loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Solr extends AbstractFallbackLoader
{
    /**
     * Record source
     *
     * @var string
     */
    protected $source = 'Solr';

    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService Resource database service
     * @param RecordIdUpdater          $recordIdUpdater Record ID updater service
     * @param Service                  $searchService   Search service
     * @param ?string                  $legacyIdField   Solr field containing legacy IDs (null to
     * disable lookups)
     */
    public function __construct(
        ResourceServiceInterface $resourceService,
        RecordIdUpdater $recordIdUpdater,
        Service $searchService,
        protected ?string $legacyIdField = 'previous_id_str_mv'
    ) {
        parent::__construct($resourceService, $recordIdUpdater, $searchService);
    }

    /**
     * Fetch a single record (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function fetchSingleRecord($id)
    {
        // If there is no legacy ID field defined, short circuit the lookup:
        if (null === $this->legacyIdField) {
            return new \VuFindSearch\Backend\Solr\Response\Json\RecordCollection(
                ['recordCount' => 0]
            );
        }
        $query = new \VuFindSearch\Query\Query(
            $this->legacyIdField . ':"' . addcslashes($id, '"') . '"'
        );
        $command = new SearchCommand('Solr', $query);
        return $this->searchService->invoke($command)->getResult();
    }
}
