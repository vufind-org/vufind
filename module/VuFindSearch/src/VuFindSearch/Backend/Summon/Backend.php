<?php

/**
 * Summon backend.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Summon;

use SerialsSolutions\Summon\Laminas as Connector;
use SerialsSolutions_Summon_Exception as SummonException;
use SerialsSolutions_Summon_Query as SummonQuery;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function count;
use function in_array;

/**
 * Summon backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend implements RetrieveBatchInterface
{
    /**
     * Connector.
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Query builder.
     *
     * @var QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * Constructor.
     *
     * @param Connector                        $connector Summon connector
     * @param RecordCollectionFactoryInterface $factory   Record collection factory
     * (null for default)
     *
     * @return void
     */
    public function __construct(
        Connector $connector,
        RecordCollectionFactoryInterface $factory = null
    ) {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector    = $connector;
        $this->identifier   = null;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        $baseParams = $this->getQueryBuilder()->build($query);
        if (null !== $params) {
            $baseParams->mergeWith($params);
        }
        $baseParams->set('pageSize', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $summonQuery = $this->paramBagToSummonQuery($baseParams);
        try {
            $response = $this->connector->query($summonQuery);
        } catch (SummonException $e) {
            throw new BackendException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     * @throws RecordMissingException
     */
    public function retrieve($id, ParamBag $params = null)
    {
        $finalParams = $params ?: new ParamBag();
        // We normally look up by ID, but we occasionally need to use bookmarks:
        $idType = $finalParams->get('summonIdType')[0] ?? Connector::IDENTIFIER_ID;
        try {
            $response = $this->connector->getRecord($id, false, $idType);
        } catch (SummonException $e) {
            throw new BackendException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        if (empty($response['documents'])) {
            throw new RecordMissingException('Record does not exist.');
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Retrieve a batch of documents.
     *
     * @param array    $ids    Array of document identifiers
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieveBatch($ids, ParamBag $params = null)
    {
        // Load 50 records at a time; this is the limit for Summon.
        $pageSize = 50;

        // Retrieve records a page at a time:
        $results = false;
        while (count($ids) > 0) {
            $currentPage = array_splice($ids, 0, $pageSize, []);
            $query = new SummonQuery(
                null,
                [
                    'idsToFetch' => $currentPage,
                    'pageNumber' => 1,
                    'pageSize' => $pageSize,
                ]
            );
            try {
                $batch = $this->connector->query($query);
            } catch (SummonException $e) {
                throw new BackendException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
            $next = $this->createRecordCollection($batch);
            if (!$results) {
                $results = $next;
            } else {
                foreach ($next->getRecords() as $record) {
                    $results->add($record);
                }
            }
        }
        $this->injectSourceIdentifier($results);
        return $results;
    }

    /**
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Return query builder.
     *
     * Lazy loads an empty QueryBuilder if none was set.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $this->queryBuilder = new QueryBuilder();
        }
        return $this->queryBuilder;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory()
    {
        if ($this->collectionFactory === null) {
            $this->collectionFactory = new Response\RecordCollectionFactory();
        }
        return $this->collectionFactory;
    }

    /**
     * Return the Summon connector.
     *
     * @return Connector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /// Internal API

    /**
     * Create record collection.
     *
     * @param array $records Records to process
     *
     * @return RecordCollectionInterface
     */
    protected function createRecordCollection($records)
    {
        return $this->getRecordCollectionFactory()->factory($records);
    }

    /**
     * Convert a ParamBag to a Summon query object.
     *
     * @param ParamBag $params ParamBag to convert
     *
     * @return SummonQuery
     */
    protected function paramBagToSummonQuery(ParamBag $params)
    {
        $params = $params->getArrayCopy();

        // Extract the query:
        $query = $params['query'][0] ?? null;
        unset($params['query']);

        // Convert the options:
        $options = [];
        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = ['facets', 'filters', 'groupFilters', 'rangeFilters'];
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings) ? $param : $param[0];
        }

        return new SummonQuery($query, $options);
    }
}
