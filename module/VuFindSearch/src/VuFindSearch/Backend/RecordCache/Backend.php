<?php

/**
 * Record cache backend.
 *
 * PHP version 5
 *
 * Copyright (C) 2014 University of Freiburg.
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFindSearch\Backend\RecordCache;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\ParamBag;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Feature\RetrieveBatchInterface;

class Backend extends AbstractBackend
// implements RetrieveBatchInterface
{

    protected $connector;

    protected $queryBuilder = null;

    protected $recordDriverPluginManager = null;

    protected $recordCollectionFactories = array();

    protected $databaseManager = null;

    public function __construct(Connector $connector, RecordCollectionFactoryInterface $factory = null)
    {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector = $connector;
    }

    public function search(AbstractQuery $query, $offset, $limit, ParamBag $params = null)
    {
        $response = $this->connector->search($params);
        $collection = $this->createRecordCollection($response);
        return $collection;
    }

    public function retrieve($id, ParamBag $params = null)
    {
        $response = $this->connector->retrieve($id, $params);
        $collection = $this->createRecordCollection($response);
        
        return $collection;
    }

    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $result = array();
        
        $responses = $this->connector->retrieveBatch($ids, $params);
        foreach ($responses as $response) {
            $collection = $this->createRecordCollection($response);
        }
        
        return $collection;
    }

    protected function createRecordCollection($cacheEntries)
    {
        $recordCollectionFactory = $this->getRecordCollectionFactory();
        $recordCollection = $recordCollectionFactory->factory($cacheEntries);
        
        return $recordCollection;
    }

    public function getRecordCollectionFactory()
    {
        return $this->collectionFactory;
    }
}