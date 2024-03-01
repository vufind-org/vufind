<?php

/**
 * Primo backend.
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

namespace VuFindSearch\Backend\Primo;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function in_array;

/**
 * Primo Central backend.
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
     * @var ConnectorInterface
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
     * @param ConnectorInterface               $connector Primo connector
     * @param RecordCollectionFactoryInterface $factory   Record collection factory
     * (null for default)
     *
     * @return void
     */
    public function __construct(
        ConnectorInterface $connector,
        RecordCollectionFactoryInterface $factory = null
    ) {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector    = $connector;
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
        $baseParams->set('limit', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $primoQuery = $this->paramBagToPrimoQuery($baseParams);
        try {
            $response = $this->connector->query(
                $this->connector->getInstitutionCode(),
                $primoQuery['query'],
                $primoQuery
            );
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
        $onCampus = (null !== $params) ? $params->get('onCampus') : [false];
        $onCampus = $onCampus ? $onCampus[0] : false;
        try {
            $response   = $this->connector
                ->getRecord(
                    $id,
                    $this->connector->getInstitutionCode(),
                    $onCampus
                );
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
     * Convert a ParamBag to a Primo query array.
     *
     * @param ParamBag $params ParamBag to convert
     *
     * @return array
     */
    protected function paramBagToPrimoQuery(ParamBag $params)
    {
        $params = $params->getArrayCopy();

        // Convert the options:
        $options = [];

        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = [
            'query', 'facets', 'filterList', 'groupFilters', 'rangeFilters',
        ];
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings) ? $param : $param[0];
        }

        // Use special pcAvailability filter if it has been set:
        foreach ($options['filterList'] ?? [] as $i => $filter) {
            if ('pcAvailability' === $filter['field']) {
                $value = reset($filter['values']);
                // Note that '' is treated as true for the simple case with no value
                $options['pcAvailability'] = !in_array($value, [false, 0, '0', 'false'], true);
                unset($options['filterList'][$i]);
                break;
            }
        }

        return $options;
    }
}
