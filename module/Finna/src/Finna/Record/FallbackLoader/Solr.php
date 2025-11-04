<?php

/**
 * Solr record fallback loader
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Record\FallbackLoader;

use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\RecordIdUpdater;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Service;

/**
 * Solr record fallback loader
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Solr extends \VuFind\Record\FallbackLoader\Solr
{
    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService Resource database service
     * @param RecordIdUpdater          $recordIdUpdater Record ID updater service
     * @param Service                  $searchService   Search service
     * @param ?string                  $legacyIdField   Solr field containing legacy IDs (null to
     * disable lookups)
     * @param array                    $config          Main configuration
     */
    public function __construct(
        ResourceServiceInterface $resourceService,
        RecordIdUpdater $recordIdUpdater,
        Service $searchService,
        ?string $legacyIdField = 'previous_id_str_mv',
        protected array $config = []
    ) {
        parent::__construct($resourceService, $recordIdUpdater, $searchService, $legacyIdField);
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
        $result = parent::fetchSingleRecord($id);
        if ($result->first()) {
            return $result;
        }

        if (preg_match('/\.(FIN\d+)/', $id, $matches)) {
            // Probably an old MetaLib record ID. Try to find the record using
            // its old MetaLib ID
            $result = $this->loadMetaLibRecord($matches[1]);
            if ($result->first()) {
                return $result;
            }
        } elseif (preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
            // Old musketti record. Try to find the new record using the
            // inventory number.
            $result = $this->loadRecordWithIdentifier($matches[1], 'museovirasto');
            if ($result->first()) {
                return $result;
            }
        }
        // Check redirection rules
        if ($redirectionRules = $this->config['Record']['missing_record_redirect'] ?? []) {
            foreach ($redirectionRules as $rule) {
                $data = array_map('trim', explode('###', $rule, 4));
                if (!isset($data[2])) {
                    continue;
                }
                [$pattern, $replacement, $newDatasource] = $data;
                $field = $data[3] ?? 'ctrlnum';
                $otherId = preg_replace($pattern, $replacement, $id, -1, $count);
                if ($count && $otherId) {
                    // Try to find the new record by searching for the redirected
                    // ID in the specified field (possibly with prefix).
                    $result = $this->loadRecordWithIdentifier($otherId, $newDatasource, $field);
                    if ($record = $result->first()) {
                        $record->setExtraDetail('redirectedFromId', $id);
                        return $result;
                    }
                }
            }
        }

        return new \VuFindSearch\Backend\Solr\Response\Json\RecordCollection(['recordCount' => 0]);
    }

    /**
     * Try to load a record using its identifier field
     *
     * @param string  $identifier Identifier (e.g. SUK77:2)
     * @param ?string $dataSource Optional data source filter
     * @param string  $field      Index field to search from
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function loadRecordWithIdentifier(
        string $identifier,
        ?string $dataSource = null,
        string $field = 'identifier'
    ) {
        $safeIdentifier = addcslashes($identifier, '"');
        $queryStr = $field . ':"' . $safeIdentifier . '"';
        if (null !== $dataSource) {
            $queryStr .= ' AND datasource_str_mv:"' . addcslashes($dataSource, '"')
                . '"';
        }
        $query = new \VuFindSearch\Query\Query($queryStr);
        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false']
        );
        $command = new SearchCommand(
            $this->source,
            $query,
            0,
            1,
            $params
        );
        return $this->searchService->invoke($command)->getResult();
    }

    /**
     * Try to load a record using its old MetaLib ID
     *
     * @param string $id Record ID (e.g. FIN12345)
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function loadMetalibRecord($id)
    {
        $safeId = addcslashes($id, '"');
        $query = new \VuFindSearch\Query\Query(
            'original_id_str_mv:"' . $safeId . '"'
        );
        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false']
        );
        $command = new SearchCommand(
            $this->source,
            $query,
            0,
            1,
            $params
        );
        return $this->searchService->invoke($command)->getResult();
    }
}
