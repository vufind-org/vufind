<?php

/**
 * Factory for RecordCache backends
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

namespace VuFind\Search\Factory;

use VuFind\RecordDriver\PluginManager;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use VuFindSearch\Backend\RecordCache\Connector;
use VuFindSearch\Backend\RecordCache\Backend;
use VuFindSearch\Backend\RecordCache\Response\RecordCollectionFactory;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory as SolrRecordCollectionFactory;
use VuFindSearch\Backend\WorldCat\Response\XML\RecordCollectionFactory as WorldCatRecordCollectionFactory;

class RecordCacheBackendFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        
        $databaseManager = $this->serviceLocator->get('VuFind\DbTablePluginManager');
        $connector = $this->createConnector($databaseManager);
        return $this->createBackend($connector);
    }
    
    protected function createConnector($databaseManager)
    {
        $connector = new Connector($databaseManager);
        return $connector;
    }
    
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend($connector);
        $recordCollectionFactory = $this->createRecordCollectionFactory();
        $backend->setRecordCollectionFactory($recordCollectionFactory);
        
        return $backend;
    }
    
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        
        $recordFactories = array();
        
        $recordFactories['VuFind'] = array($manager, 'getSolrRecord');
        
        $recordFactories['WorldCat'] = function ($data) use ($manager) {
            $driver = $manager->get('WorldCat');
            $driver->setRawData($data);
            $driver->setSourceIdentifier('WorldCat');
            return $driver;
        };
        
        return new RecordCollectionFactory($recordFactories);
    }
    
    
}