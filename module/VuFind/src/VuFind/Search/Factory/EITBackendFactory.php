<?php

/**
 * Factory for EBSCO Integration Toolkit (EIT) backends.
 *
 * PHP version 8
 *
 * Copyright (C) Julia Bauder 2013.
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Psr\Container\ContainerInterface;
use VuFindSearch\Backend\EIT\Backend;
use VuFindSearch\Backend\EIT\Connector;
use VuFindSearch\Backend\EIT\QueryBuilder;
use VuFindSearch\Backend\EIT\Response\XML\RecordCollectionFactory;

/**
 * Factory for EIT backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EITBackendFactory extends AbstractBackendFactory
{
    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

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
        $this->setup($sm);
        $this->config = $this->getService(\VuFind\Config\PluginManager::class)->get('EIT');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the EIT backend.
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
     * Create the EIT connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $prof = $this->config->General->prof ?? null;
        $pwd = $this->config->General->pwd ?? null;
        $base = $this->config->General->base_url
            ?? 'https://eit.ebscohost.com/Services/SearchService.asmx/Search';
        $dbs = $this->config->General->dbs ?? null;
        $connector = new Connector(
            $base,
            $this->createHttpClient(),
            $prof,
            $pwd,
            $dbs
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the EIT query builder.
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
        $manager = $this->getService(\VuFind\RecordDriver\PluginManager::class);
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('EIT');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
