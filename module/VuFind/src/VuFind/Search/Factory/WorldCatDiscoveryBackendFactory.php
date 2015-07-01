<?php

/**
 * Factory for WorldCatDiscovery backends.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Factory;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\WorldCatDiscovery\Response\RecordCollectionFactory;
use VuFindSearch\Backend\WorldCatDiscovery\QueryBuilder;
use VuFindSearch\Backend\WorldCatDiscovery\Backend;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for WorldCatDiscovery backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class WorldCatDiscoveryBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Create the backend.
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service manager
     *
     * @return BackendInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }
        $backend   = $this->createBackend();
        return $backend;
    }

    /**
     * Create the WorldCatDiscovery backend.
     *
     * @return Backend
     */
    protected function createBackend()
    {
        $config = $this->serviceLocator->get('VuFind\Config');
        
        $configOptions = [];
        
        if ($config->get('WorldCatDiscovery')) {
            $configOptions['wskey'] = $config->get('WorldCatDiscovery')->General->wskey;
            $configOptions['secret'] = $config->get('WorldCatDiscovery')->General->secret;
            $configOptions['institution'] = $config->get('WorldCatDiscovery')->General->institution;
            $configOptions['heldBy'] = explode(",", $config->get('WorldCatDiscovery')->General->heldBy);
            $configOptions['databaseIDs'] = explode(",", $config->get('WorldCatDiscovery')->General->databaseIDs);
        } else {
            throw new Exception('You do not have the proper properties setup in the WorldCatDiscovery ini file');
        }
        
        //TODO: need to deal with what happens if the MultiDriver is being used with WMS
        if ($config->get('config')->Catalog->driver == 'WMS') {
            $configOptions['wmsEnabled'] = true;
        } else {
            $configOptions['wmsEnabled'] = false;
        }
        $backend = new Backend(
            $this->createRecordCollectionFactory(), $configOptions
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the WorldCat query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        return new QueryBuilder();
    }

    /**
     * Create the record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('WorldCatDiscovery');
            $driver->setRawData($data['doc']);
            $driver->setOffers($data['offers']);
            return $driver;
        };
        $urlService = $this->serviceLocator
            ->get('VuFind\WorldCatKnowledgeBaseUrlService');
        return new RecordCollectionFactory($callback, null, $urlService);
    }
}