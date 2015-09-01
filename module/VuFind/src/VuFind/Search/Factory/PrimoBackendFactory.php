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

        // Determines, if the OnCampusListener is necessary for the user
        // if this returns false, the listener is not necessary
        if ($this->needsOnCampusRange()) {
            $this->getInjectOnCampusListener()->attach($events);
        }
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

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $timeout = isset($this->primoConfig->General->timeout)
            ? $this->primoConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector($id, $this->getInstCode(), $client, $port);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Determine the institution code
     *
     * @return string
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
     * Determine the institution campusrange
     *
     * @return string
     */
    protected function getOnCampusRange()
    {
        $onCampusPermission
            = isset($this->primoConfig->Institutions->onCampusPermission)
            ? $this->primoConfig->Institutions->onCampusPermission : false;

        if (false !== $onCampusPermission) {
            return $onCampusPermission;
        }

        // If primoConfig->Institutions->onCampusPermission is not set
        // no rule can get applied.
        // So return null to indicate that nothing can get matched.
        return null;
    }

    /**
     * Determine if a campusrange from configuration is needed
     *
     * @return bool
     */
    protected function needsOnCampusRange()
    {
        $regex = isset($this->primoConfig->Institutions->regex)
            ? $this->primoConfig->Institutions->regex : [];
        $onCampusPermission
            = isset($this->primoConfig->Institutions->onCampusPermission)
            ? $this->primoConfig->Institutions->onCampusPermission : false;

        // Configuration options should always get checked
        if (in_array('/.*/', $regex) && false === $onCampusPermission) {
            throw new \Exception(
                'You are using a catch-all rule in your [Institutions] settings without '
                . 'having set the onCampusPermission-Parameter. This configuration will '
                . 'only show PrimoCentral results for onCampus=false, if the user is not '
                . 'inside one [Institutions] IP range.'
            );
        }

        $request = $this->serviceLocator->get('Request');
        $ip = $request->getServer('REMOTE_ADDR');

        // if the user has an IP, which is configured for a special Institution Range
        // there is no additional check necessary
        for ($i = 0; $i < count($regex); $i++) {
            if (preg_match($regex[$i], $ip)
                && $regex[$i] != '/.*/'
            ) {
                return false;
            }
        }

        return true;
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
        $listener = new InjectOnCampusListener($this->getOnCampusRange());
        $listener->setAuthorizationService(
            $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService')
        );
        return $listener;
    }
}