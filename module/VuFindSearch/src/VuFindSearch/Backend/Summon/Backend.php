<?php

/**
 * Summon backend.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Summon;

use SerialsSolutions\Summon\Zend2 as Connector;
use SerialsSolutions_Summon_Query as SummonQuery;
use SerialsSolutions_Summon_Exception as SummonException;

use VuFindSearch\Query\AbstractQuery;

use VuFindSearch\ParamBag;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;

use Zend\Log\LoggerInterface;

/**
 * Summon backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Backend implements BackendInterface
{
    /**
     * Record collection factory.
     *
     * @var RecordCollectionFactoryInterface
     */
    protected $collectionFactory = null;

    /**
     * Logger, if any.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Connector.
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Backend identifier.
     *
     * @var string
     */
    protected $identifier;

    /**
     * Query builder.
     *
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * Constructor.
     *
     * @param Connector                        $connector Summon connector
     * @param RecordCollectionFactoryInterface $factory   Record collection factory
     * (null for default)
     *
     * @return void
     */
    public function __construct(Connector $connector,
        RecordCollectionFactoryInterface $factory = null
    ) {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector    = $connector;
        $this->identifier   = null;
    }

    /**
     * Set the backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param integer       $offset Search offset
     * @param integer       $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(AbstractQuery $query, $offset, $limit,
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
     */
    public function retrieve($id, ParamBag $params = null)
    {
        try {
            $response   = $this->connector->getRecord($id);
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
     * Set the Logger.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     *
     * @todo Typehint QueryBuilderInterface
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Return backend identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set the record collection factory.
     *
     * @param RecordCollectionFactoryInterface $factory Factory
     *
     * @return void
     */
    public function setRecordCollectionFactory(
        RecordCollectionFactoryInterface $factory
    ) {
        $this->collectionFactory = $factory;
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
     * Inject source identifier in record collection and all contained records.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function injectSourceIdentifier(RecordCollectionInterface $response)
    {
        $response->setSourceIdentifier($this->identifier);
        foreach ($response as $record) {
            $record->setSourceIdentifier($this->identifier);
        }
        return $response;
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Log context
     *
     * @return void
     */
    protected function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
    }

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
        $query = isset($params['query'][0]) ? $params['query'][0] : null;
        unset($params['query']);

        // Convert the options:
        $options = array();
        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = array('facets', 'filters', 'groupFilters', 'rangeFilters');
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings) ? $param : $param[0];
        }

        return new SummonQuery($query, $options);
    }
}