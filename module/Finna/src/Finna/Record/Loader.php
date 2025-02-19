<?php

/**
 * Record loader
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2023.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Record;

use Finna\RecordDriver\Feature\ContainerFormatInterface;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\RecordDriver\DefaultRecord;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;

use function count;
use function in_array;

/**
 * Record loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader extends \VuFind\Record\Loader
{
    /**
     * Preferred language for display strings from RecordDriver
     *
     * @var string
     */
    protected $preferredLanguage;

    /**
     * Record redirection rules (see config.ini::missing_record_redirect).
     *
     * @var array
     */
    protected $recordRedirectionRules = [];

    /**
     * Set preferred language for display strings from RecordDriver.
     *
     * @param string $language Language
     *
     * @return void
     */
    public function setPreferredLanguage($language)
    {
        $this->preferredLanguage = $language;
    }

    /**
     * Set record redirection rules.
     *
     * @param array $rules Rules.
     *
     * @return void
     */
    public function setRecordRedirectionRules($rules)
    {
        $this->recordRedirectionRules = $rules;
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string    $id              Record ID
     * @param string    $source          Record source
     * @param bool      $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     * @param ?ParamBag $params          Search backend parameters
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load(
        $id,
        $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false,
        ?ParamBag $params = null
    ) {
        if ($source == 'MetaLib') {
            if ($tolerateMissing) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $id]);
                $record->setSourceIdentifiers($source);
                return $record;
            }
            throw new RecordMissingException(
                'Record ' . $source . ':' . $id . ' does not exist.'
            );
        }
        $result = null;
        $missingException = false;

        // Check for an encapsulated record ID
        $parts = explode(
            ContainerFormatInterface::ENCAPSULATED_RECORD_ID_SEPARATOR,
            $id,
            2
        );
        if ($id !== $parts[0]) {
            // Encapsulated record ID separator was found.
            // Attempt to load parent record using the first part of the ID.
            $parentRecord = $this->load($parts[0]);
            // If the parent record implements ContainerRecordInterface
            // get encapsulated record using the second part of the ID
            if ($parentRecord instanceof ContainerFormatInterface) {
                $result = $parentRecord->getEncapsulatedRecord($parts[1]);
            }
            if (null === $result) {
                throw new RecordMissingException(
                    'Encapsulated record ' . $source . ':' . $id . ' does not exist.'
                );
            }
        }

        if (null === $result) {
            try {
                $result = parent::load($id, $source, $tolerateMissing, $params);
            } catch (RecordMissingException $e) {
                $missingException = $e;
            }
        }
        if (
            null !== $id
            && '' !== $id
            && in_array($source, ['Solr', 'SolrAuth'])
            && ($missingException || $result instanceof \VuFind\RecordDriver\Missing
            || ($result && $result->getExtraDetail('cached_record')))
        ) {
            // Check for a redirected record without overwriting $result
            if ($redirectedRecord = $this->handleMissingSolrRecord($id, $source)) {
                $missingException = false;
                $result = $redirectedRecord;
            }
        }
        if ($missingException) {
            throw $missingException;
        }
        if (null === $result) {
            throw new RecordMissingException(
                'Record ' . $source . ':' . $id . ' does not exist.'
            );
        }

        if ($this->preferredLanguage) {
            $result->tryMethod('setPreferredLanguage', [$this->preferredLanguage]);
        }

        return $result;
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * Finna: Ignores 'sources' setting in search configuration.
     *
     * @param array      $ids                       Array of associative arrays with
     * id/source keys or strings in source|id format. In associative array formats,
     * there is also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     * @param bool       $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subscriptions
     * @param ParamBag[] $params                    Associative array of search
     * backend parameters keyed with source key
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatchIgnoringSourceFilter(
        $ids,
        $tolerateBackendExceptions = false,
        $params = []
    ) {
        foreach (array_unique(array_column($ids, 'source')) as $source) {
            if (!isset($params[$source])) {
                $params[$source] = new ParamBag();
            }
            $params[$source]->set('finna.ignore_source_filter', 1);
        }
        return $this->loadBatch($ids, $tolerateBackendExceptions, $params);
    }

    /**
     * Given an array of IDs and a record source, load a batch of records for
     * that source.
     *
     * @param array     $ids                       Record IDs
     * @param string    $source                    Record source
     * @param bool      $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subscriptions
     * @param ?ParamBag $params                    Search backend parameters
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource(
        $ids,
        $source = DEFAULT_SEARCH_BACKEND,
        $tolerateBackendExceptions = false,
        ?ParamBag $params = null
    ) {
        if ('MetaLib' === $source) {
            $result = [];
            foreach ($ids as $recId) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $recId]);
                $record->setSourceIdentifier('MetaLib');
                $result[] = $record;
            }
            return $result;
        }

        $records = parent::loadBatchForSource(
            $ids,
            $source,
            $tolerateBackendExceptions,
            $params
        );

        // Check the results for missing records and try to load them with their old IDs:
        foreach ($records as &$record) {
            if ($record instanceof \VuFind\RecordDriver\Missing) {
                $sourceId = $record->getSourceIdentifier();
                if (in_array($sourceId, ['Solr', 'SolrAuth'])) {
                    // Check for a new record without overwriting the current one:
                    if ($newRecord = $this->handleMissingSolrRecord($record->getUniqueID(), $sourceId)) {
                        $record = $newRecord;
                    }
                }
            }
        }
        // Unset reference:
        unset($record);

        return $records;
    }

    /**
     * Handle missing Solr record by trying to find the record using alternative ID.
     *
     * @param string $id       Record ID
     * @param string $sourceId Source ID
     *
     * @return DefaultRecord|null Record or null if not found
     */
    protected function handleMissingSolrRecord(string $id, string $sourceId): ?DefaultRecord
    {
        if ('Solr' === $sourceId && preg_match('/\.(FIN\d+)/', $id, $matches)) {
            // Probably an old MetaLib record ID. Try to find the record using
            // its old MetaLib ID
            if ($mlRecord = $this->loadMetaLibRecord($matches[1])) {
                return $mlRecord;
            }
        } elseif ('Solr' === $sourceId && preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
            // Old musketti record. Try to find the new record using the
            // inventory number.
            if ($newRecord = $this->loadRecordWithIdentifier($matches[1], $sourceId, 'museovirasto')) {
                return $newRecord;
            }
        }
        if ('SolrAuth' === $sourceId) {
            // Try to find the record with an identifier:
            if ($newRecord = $this->loadRecordWithIdentifier($id, $sourceId, null, 'identifier_str_mv')) {
                return $newRecord;
            }
        }
        if ($this->recordRedirectionRules) {
            foreach ($this->recordRedirectionRules as $rule) {
                $data = array_map('trim', explode('###', $rule, 4));
                if (count($data) >= 3) {
                    [$pattern, $replacement, $newDatasource] = $data;
                    $field = $data[3] ?? 'ctrlnum';
                    $otherId = preg_replace($pattern, $replacement, $id, -1, $count);
                    if ($count && $otherId) {
                        // Try to find the new record by searching for the redirected
                        // ID in in ctrlnum field (possibly with prefix).
                        $newRecord = $this->loadRecordWithIdentifier(
                            $otherId,
                            $sourceId,
                            $newDatasource,
                            $field
                        );
                        if ($newRecord) {
                            $newRecord->setExtraDetail('redirectedFromId', $id);
                            return $newRecord;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Try to load a record using its old MetaLib ID
     *
     * @param string $id Record ID (e.g. FIN12345)
     *
     * @return \VuFind\RecordDriver\AbstractBase|bool Record or false if not found
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
            'Solr',
            $query,
            0,
            1,
            $params
        );
        $results = $this->searchService->invoke($command)->getResult()
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }

    /**
     * Try to load a record using its identifier field
     *
     * @param string  $identifier Identifier (e.g. SUK77:2)
     * @param string  $sourceId   Source ID
     * @param ?string $dataSource Optional data source filter
     * @param string  $field      Index field to search from
     *
     * @return DefaultRecord|bool Record or false if not found
     */
    protected function loadRecordWithIdentifier(
        string $identifier,
        string $sourceId,
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
            $sourceId,
            $query,
            0,
            1,
            $params
        );
        $results = $this->searchService->invoke($command)->getResult()
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }
}
