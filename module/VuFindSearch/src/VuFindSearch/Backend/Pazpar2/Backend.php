<?php

/**
 * Pazpar2 backend.
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

namespace VuFindSearch\Backend\Pazpar2;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function intval;

/**
 * Pazpar2 backend.
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
     * How much search progress should be completed before returning results
     * (a value between 0 and 1).
     *
     * @var float
     */
    protected $progressTarget = 1.0;

    /**
     * The maximum amount of time to wait to reach $progressTarget (above)
     * before giving up and accepting what is currently available. (Measured
     * in seconds).
     *
     * @var int
     */
    protected $maxQueryTime = 60;

    /**
     * Constructor.
     *
     * @param Connector                        $connector Pazpar2 connector
     * @param RecordCollectionFactoryInterface $factory   Record collection factory
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
        $this->connector      = $connector;
    }

    /**
     * Set the max query time.
     *
     * @param int $time New value
     *
     * @return void
     */
    public function setMaxQueryTime($time)
    {
        $this->maxQueryTime = $time;
    }

    /**
     * Set the search progress target.
     *
     * @param float $progress New value
     *
     * @return void
     */
    public function setSearchProgressTarget($progress)
    {
        $this->progressTarget = $progress;
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
        $this->connector->search($baseParams);

        /* Pazpar2 does not return all results immediately. Rather, we need to
         * occasionally check with the Pazpar2 server on the status of the
         * search.
         *
         * This loop will continue to wait until the configured level of
         * progress is reached or until the maximum query time has passed at
         * which time the existing results will be returned.
         */
        $queryStart = time();
        $progress = $this->getSearchProgress();
        while (
            $progress < $this->progressTarget
            && (time() - $queryStart) < $this->maxQueryTime
        ) {
            sleep(1);
            $progress = $this->getSearchProgress();
        }

        $showParams = new ParamBag(
            ['block' => 1, 'num' => $limit, 'start' => $offset]
        );
        $response = $this->connector->show($showParams);

        $hits = $response->hit ?? [];
        $collection = $this->createRecordCollection(
            $hits,
            intval($response->merged),
            $offset
        );
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
        $response   = $this->connector->record($id);
        $collection = $this->createRecordCollection([$response], 1);
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
        if (!$this->collectionFactory) {
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
     * @param int   $total   Total result count
     * @param int   $offset  Search offset
     *
     * @return RecordCollectionInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function createRecordCollection($records, $total = 0, $offset = 0)
    {
        return $this->getRecordCollectionFactory()
            ->factory(compact('records', 'total', 'offset'));
    }

    /**
     * Get progress on the current search operation.
     *
     * @return float
     */
    protected function getSearchProgress()
    {
        $statResponse = $this->connector->stat();
        return (float)$statResponse->progress;
    }
}
