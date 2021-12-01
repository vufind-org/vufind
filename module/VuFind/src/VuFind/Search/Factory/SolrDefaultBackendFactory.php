<?php

/**
 * Factory for the default SOLR backend.
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

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

/**
 * Factory for the default SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrDefaultBackendFactory extends AbstractSolrBackendFactory
{
    /**
     * Method for creating a record driver.
     *
     * @var string
     */
    protected $createRecordMethod = 'getSolrRecord';

    /**
     * Record collection class for RecordCollectionFactory
     *
     * @var string
     */
    protected $recordCollectionClass = RecordCollection::class;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchConfig = 'searches';
        $this->searchYaml = 'searchspecs.yaml';
        $this->facetConfig = 'facets';
    }

    /**
     * Get the Solr core.
     *
     * @return string
     */
    protected function getSolrCore()
    {
        $config = $this->config->get($this->mainConfig);
        return $config->Index->default_core ?? 'biblio';
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = parent::createBackend($connector);
        $backend->setRecordCollectionFactory($this->createRecordCollectionFactory());
        return $backend;
    }

    /**
     * Create the record collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    protected function createRecordCollectionFactory()
        : RecordCollectionFactoryInterface
    {
        $manager = $this->serviceLocator
            ->get(\VuFind\RecordDriver\PluginManager::class);
        return new RecordCollectionFactory(
            [$manager, $this->createRecordMethod],
            $this->recordCollectionClass
        );
    }
}
