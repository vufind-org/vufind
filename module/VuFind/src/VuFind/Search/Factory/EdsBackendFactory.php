<?php

/**
 * Factory for EDS backends.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;

use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Backend\EDS\QueryBuilder;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;
use VuFindSearch\Backend\EDS\Zend2 as Connector;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for EDS backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * EDS configuration
     *
     * @var \Zend\Config\Config
     */
    protected $edsConfig;

    /**
     * EDS Account data
     *
     * @var array
     */
    protected $accountData;

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->serviceLocator = $sm;
        $this->edsConfig = $this->serviceLocator->get('VuFind\Config\PluginManager')
            ->get('EDS');
        if ($this->serviceLocator->has('VuFind\Log\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Log\Logger');
        }
        $connector = $this->createConnector();
        return $this->createBackend($connector);
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
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        $isGuest = !$auth->isGranted('access.EDSExtendedResults');
        $session = new \Zend\Session\Container(
            'EBSCO', $this->serviceLocator->get('Zend\Session\SessionManager')
        );
        $backend = new Backend(
            $connector, $this->createRecordCollectionFactory(),
            $this->serviceLocator->get('VuFind\Cache\Manager')->getCache('object'),
            $session, $this->edsConfig, $isGuest
        );
        $backend->setAuthManager($this->serviceLocator->get('VuFind\Auth\Manager'));
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
        $options = [];
        $id = 'EDS';
        $key = 'EDS';
        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFindHttp\HttpService')
            ->createClient();
        $timeout = isset($this->edsConfig->General->timeout)
            ? $this->edsConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);
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
        $manager = $this->serviceLocator->get('VuFind\RecordDriver\PluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('EDS');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
