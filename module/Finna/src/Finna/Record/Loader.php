<?php
/**
 * Record loader
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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

use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFindSearch\ParamBag;

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
     * Given an ID and record source, load the requested record object.
     *
     * @param string   $id              Record ID
     * @param string   $source          Record source
     * @param bool     $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     * @param ParamBag $params          Search backend parameters
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false, ParamBag $params = null
    ) {
        if ($source == 'MetaLib') {
            if ($tolerateMissing) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $id]);
                $record->setSourceIdentifier($source);
                return $record;
            }
            throw new RecordMissingException(
                'Record ' . $source . ':' . $id . ' does not exist.'
            );
        }
        $missingException = false;
        try {
            $result = parent::load($id, $source, $tolerateMissing, $params);
        } catch (RecordMissingException $e) {
            $missingException = $e;
        }
        if ($source == 'Solr'
            && ($missingException || $result instanceof \VuFind\RecordDriver\Missing)
        ) {
            if (preg_match('/\.(FIN\d+)/', $id, $matches)) {
                // Probably an old MetaLib record ID. Try to find the record using
                // its old MetaLib ID
                if ($mlRecord = $this->loadMetaLibRecord($matches[1])) {
                    return $mlRecord;
                }
            } elseif (preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
                // Old musketti record. Try to find the new record using the
                // inventory number.
                $newRecord
                    = $this->loadRecordWithIdentifier($matches[1], 'museovirasto');
                if ($newRecord) {
                    return $newRecord;
                }
            }
        }
        if ($missingException) {
            throw $missingException;
        }

        if ($this->preferredLanguage) {
            $result->tryMethod('setPreferredLanguage', [$this->preferredLanguage]);
        }

        return $result;
    }

    /**
     * Given an array of IDs and a record source, load a batch of records for
     * that source.
     *
     * @param array    $ids                       Record IDs
     * @param string   $source                    Record source
     * @param bool     $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subcscriptions
     * @param ParamBag $params                    Search backend parameters
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource($ids, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateBackendExceptions = false, ParamBag $params = null
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
            $ids, $source, $tolerateBackendExceptions
        );

        // Check the results for missing MetaLib IRD records and try to load them
        // with their old MetaLib IDs
        foreach ($records as &$record) {
            if ($record instanceof \VuFind\RecordDriver\Missing
                && $record->getSourceIdentifier() == 'Solr'
            ) {
                $id = $record->getUniqueID();
                if (preg_match('/\.(FIN\d+)/', $id, $matches)) {
                    if ($mlRecord = $this->loadMetaLibRecord($matches[1])) {
                        $record = $mlRecord;
                    }
                } elseif (preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
                    // Old musketti record. Try to find the new record using the
                    // inventory number.
                    $newRecord = $this
                        ->loadRecordWithIdentifier($matches[1], 'museovirasto');
                    if ($newRecord) {
                        $record = $newRecord;
                    }
                }
            }
        }

        return $records;
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
        $results = $this->searchService->search('Solr', $query, 0, 1, $params)
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }

    /**
     * Try to load a record using its identifier field
     *
     * @param string $identifier Identifier (e.g. SUK77:2)
     * @param string $dataSource Optional data source filter
     *
     * @return \VuFind\RecordDriver\AbstractBase|bool Record or false if not found
     */
    protected function loadRecordWithIdentifier($identifier, $dataSource = null)
    {
        $safeIdentifier = addcslashes($identifier, '"');
        $queryStr = 'identifier:"' . $safeIdentifier . '"';
        if (null !== $dataSource) {
            $queryStr .= ' AND datasource_str_mv:"' . addcslashes($dataSource, '"')
                . '"';
        }
        $query = new \VuFindSearch\Query\Query($queryStr);
        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false']
        );
        $results = $this->searchService->search('Solr', $query, 0, 1, $params)
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }
}
