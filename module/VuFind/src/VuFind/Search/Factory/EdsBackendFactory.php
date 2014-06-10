<?php

/**
 * Factory for EDS backends.
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

use VuFindSearch\Backend\EDS\Zend2 as Connector;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;
use VuFindSearch\Backend\EDS\QueryBuilder;
use VuFindSearch\Backend\EDS\Backend;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for EDS backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class EdsBackendFactory implements FactoryInterface
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
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * EDS configuration
     *
     * @var \Zend\Config\Config
     */
    protected $edsConfig;

    /**
     * EDS Account data
     * @var array
     */
    protected $accountData;


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
        $configReader = $this->serviceLocator->get('VuFind\Config');
        $this->config = $configReader->get('config');
        $this->edsConfig = $configReader->get('EDS');
        $this->accountData = $this->getAccountData();
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        $backend->setAuthManager($this->serviceLocator->get('VuFind\AuthManager'));
        return $backend;
    }

    /**
     * Create the EDS backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend(
            $connector, $this->createRecordCollectionFactory(), $this->accountData
        );
        $backend->setServiceLocator($this->serviceLocator);
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the EDS connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $options = array();
        $id = 'EDS';
        $key = 'EDS';
        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $timeout = isset($this->edsConfig->General->timeout)
            ? $this->edsConfig->General->timeout : 30;
        $client->setOptions(array('timeout' => $timeout));
        $connector = new Connector($id, $key, $options, $client);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the EDS query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        return $builder;
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
            $driver = $manager->get('EDS');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }


    /**
     * Set the default account data for use with the EDS API
     *
     * @return array Default account settings
     */
    protected function getAccountData()
    {
        $accountData = array();
        if (isset($this->edsConfig->EBSCO_Account->user_name)) {
            $accountData['username'] = $this->edsConfig->EBSCO_Account->user_name;
        }
        if (isset($this->edsConfig->EBSCO_Account->password)) {
            $accountData['password'] = $this->edsConfig->EBSCO_Account->password;
        }
        if (isset($this->edsConfig->EBSCO_Account->ip_auth)) {
            $accountData['ipauth'] = $this->edsConfig->EBSCO_Account->ip_auth;
        }
        if (isset($this->edsConfig->EBSCO_Account->profile)) {
            $accountData['profile'] = $this->edsConfig->EBSCO_Account->profile;
        }
        if (isset($this->edsConfig->EBSCO_Account->organization_id)) {
            $acountData['orgid'] = $this->edsConfig->EBSCO_Account->organization_id;
        }
        return $accountData;

    }
}