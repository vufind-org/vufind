<?php

/**
 * Factory for Primo Central backends.
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

use VuFindSearch\Backend\Primo\Connector;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Primo\Response\RecordCollectionFactory;
use VuFindSearch\Backend\Primo\QueryBuilder;
use VuFindSearch\Backend\Primo\Backend;

use VuFind\Search\Primo\InjectOnCampusListener;
use VuFind\Search\Primo\PrimoPermissionController;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for Primo Central backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PrimoBackendFactory implements FactoryInterface
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
     * Primo configuration
     *
     * @var \Zend\Config\Config
     */
    protected $primoConfig;

    /**
     * Primo Permission Controller
     *
     * @var PrimoPermissionController
     */
    protected $primoPermissionController = null;

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
        $this->primoConfig = $configReader->get('Primo');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }

        if (isset($this->primoConfig->InstitutionPermission)) {
            $permController = new PrimoPermissionController(
                $this->primoConfig->InstitutionPermission
            );
            $permController->setAuthorizationService(
                $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService')
            );
            $this->primoPermissionController = $permController;
        }

        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);

        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create the Primo Central backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend($connector, $this->createRecordCollectionFactory());
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        $events = $this->serviceLocator->get('SharedEventManager');

        $this->getInjectOnCampusListener()->attach($events);
    }

    /**
     * Create the Primo Central connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Load credentials and port number:
        $id = isset($this->primoConfig->General->apiId)
            ? $this->primoConfig->General->apiId : null;
        $port = isset($this->primoConfig->General->port)
            ? $this->primoConfig->General->port : 1701;
        $instCode = isset($this->primoPermissionController)
            ? $this->primoPermissionController->getInstCode()
            : $this->getInstCode();

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $timeout = isset($this->primoConfig->General->timeout)
            ? $this->primoConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector($id, $instCode, $client, $port);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Determine the institution code
     *
     * @return string
     * @depracated Use PrimoPermissionController instead!
     */
    protected function getInstCode()
    {
        $codes = isset($this->primoConfig->Institutions->code)
            ? $this->primoConfig->Institutions->code : [];
        $regex = isset($this->primoConfig->Institutions->regex)
            ? $this->primoConfig->Institutions->regex : [];
        if (empty($codes) || empty($regex) || count($codes) != count($regex)) {
            throw new \Exception('Check [Institutions] settings in Primo.ini');
        }

        $request = $this->serviceLocator->get('Request');
        $ip = $request->getServer('REMOTE_ADDR');

        for ($i = 0; $i < count($codes); $i++) {
            if (preg_match($regex[$i], $ip)) {
                return $codes[$i];
            }
        }

        throw new \Exception(
            'Could not determine institution code. [Institutions] settings '
            . 'should include a catch-all rule at the end.'
        );
    }

    /**
     * Create the Primo query builder.
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
            $driver = $manager->get('Primo');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }

    /**
     * Get a OnCampus Listener
     *
     * @return InjectOnCampusListener
     */
    protected function getInjectOnCampusListener()
    {
        $listener = new InjectOnCampusListener($this->primoPermissionController);
        return $listener;
    }
}