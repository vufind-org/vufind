<?php

/**
 * Factory for Summon backends.
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

use SerialsSolutions\Summon\Zend2 as Connector;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFindSearch\Backend\Summon\Backend;
use VuFindSearch\Backend\Summon\QueryBuilder;
use VuFindSearch\Backend\Summon\Response\RecordCollectionFactory;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for Summon backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SummonBackendFactory implements FactoryInterface
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
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Summon configuration
     *
     * @var \Zend\Config\Config
     */
    protected $summonConfig;

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
        $configReader = $this->serviceLocator->get('VuFind\Config\PluginManager');
        $this->config = $configReader->get('config');
        $this->summonConfig = $configReader->get('Summon');
        if ($this->serviceLocator->has('VuFind\Log\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Log\Logger');
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the Summon backend.
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
     * Create the Summon connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Load credentials:
        $id = isset($this->config->Summon->apiId)
            ? $this->config->Summon->apiId : null;
        $key = isset($this->config->Summon->apiKey)
            ? $this->config->Summon->apiKey : null;

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFindHttp\HttpService')
            ->createClient();
        $timeout = isset($this->summonConfig->General->timeout)
            ? $this->summonConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $options = ['authedUser' => $this->isAuthed()];
        $connector = new Connector($id, $key, $options, $client);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Is the current user of the Summon connector authenticated?
     *
     * @return bool
     */
    protected function isAuthed()
    {
        return $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService')
            ->isGranted('access.SummonExtendedResults');
    }

    /**
     * Create the Summon query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        $caseSensitiveBooleans
            = isset($this->summonConfig->General->case_sensitive_bools)
            ? $this->summonConfig->General->case_sensitive_bools : true;
        $helper = new LuceneSyntaxHelper($caseSensitiveBooleans);
        $builder->setLuceneHelper($helper);
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
        $stripSnippets = !isset($this->summonConfig->General->snippets)
            || !$this->summonConfig->General->snippets;
        $callback = function ($data) use ($manager, $stripSnippets) {
            $driver = $manager->get('Summon');
            if ($stripSnippets) {
                unset($data['Snippet']);
            }
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
