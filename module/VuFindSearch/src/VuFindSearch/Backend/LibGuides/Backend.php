<?php

/**
 * LibGuides backend.
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

namespace VuFindSearch\Backend\LibGuides;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function in_array;

/**
 * LibGuides backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend
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
     * Default search (performed if another search has 0 results; null to disable.
     *
     * @var string
     */
    protected $defaultSearch;

    /**
     * Constructor.
     *
     * @param Connector                        $connector     LibGuides connector
     * @param RecordCollectionFactoryInterface $factory       Record collection
     * factory (null for default)
     * @param string                           $defaultSearch Default search query
     *
     * @return void
     */
    public function __construct(
        Connector $connector,
        RecordCollectionFactoryInterface $factory = null,
        $defaultSearch = null
    ) {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector    = $connector;
        $this->identifier   = null;
        $this->defaultSearch = $defaultSearch;
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
        $args = $this->paramBagToArray($baseParams);
        try {
            $response = $this->connector->query($args, $offset, $limit);
            // Apply default search if necessary:
            if ($response['recordCount'] < 1 && isset($this->defaultSearch)) {
                $args['search'] = $this->defaultSearch;
                $response = $this->connector->query($args, $offset, $limit);
            }
        } catch (\Exception $e) {
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
     */
    public function retrieve($id, ParamBag $params = null)
    {
        throw new \Exception('retrieve() not supported by LibGuides.');
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
     * Return the Primo connector.
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
     * Convert a ParamBag to a query array.
     *
     * @param ParamBag $params ParamBag to convert
     *
     * @return array
     */
    protected function paramBagToArray(ParamBag $params)
    {
        $params = $params->getArrayCopy();

        // Convert the options:
        $options = [];

        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = [/* TODO: determine if values are needed here. */];
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings) ? $param : $param[0];
        }

        return $options;
    }
}
