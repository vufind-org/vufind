<?php
/**
 * Factory for MetaLib backends.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use FinnaSearch\Backend\MetaLib\Connector;
use VuFindSearch\Backend\BackendInterface;
use FinnaSearch\Backend\MetaLib\Response\RecordCollectionFactory;
use FinnaSearch\Backend\MetaLib\QueryBuilder;
use FinnaSearch\Backend\MetaLib\Backend;
use FinnaSearch\Backend\Solr\LuceneSyntaxHelper;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for MetaLib backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MetaLibBackendFactory implements FactoryInterface
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
     * MetaLib configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

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
        $this->config = $configReader->get('MetaLib');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }

        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the MetaLib backend.
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
     * Create the MetaLib connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $institution = $this->config->General->institution ?: null;
        $host = $this->config->General->url ?: null;
        $user = $this->config->General->x_user ?: null;
        $pass = $this->config->General->x_password ?: null;
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $table
            = $this->serviceLocator->get('VuFind\DbTablePluginManager')
                ->get('metalibSearch');
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');

        $configReader = $this->serviceLocator->get('VuFind\Config');
        $sets = $configReader->get('MetaLibSets')->toArray();
        $timeout = isset($this->config->General->timeout)
            ? $this->config->General->timeout : 60;
        $client->setOptions(['timeout' => $timeout]);

        /**
         * Should boolean operators in the search string be treated as
         * case-insensitive (false), or must they be ALL UPPERCASE (true)?
         */
        $luceneHelper
            = isset($this->config->General->case_sensitive_bools)
            && !$this->config->General->case_sensitive_bools
            ? new LuceneSyntaxHelper()
            : null
        ;

        $connector = new Connector(
            $institution, $host, $user, $pass,
            $client, $table, $auth, $sets, $luceneHelper
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the MetaLib query builder.
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
            $driver = $manager->get('MetaLib');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
