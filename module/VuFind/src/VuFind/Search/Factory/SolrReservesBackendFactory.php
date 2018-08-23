<?php

/**
 * Factory for the reserves SOLR backend.
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

use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;

/**
 * Factory for the reserves SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrReservesBackendFactory extends AbstractSolrBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->solrCore = 'reserves';
        $this->searchConfig = 'reserves';
        $this->searchYaml = 'reservessearchspecs.yaml';
        $this->facetConfig = 'reserves';
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return \VuFindSearch\Backend\Solr\Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = parent::createBackend($connector);
        $manager = $this->serviceLocator->get('VuFind\RecordDriver\PluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('SolrReserves');
            $driver->setRawData($data);
            return $driver;
        };
        $factory = new RecordCollectionFactory($callback);
        $backend->setRecordCollectionFactory($factory);
        return $backend;
    }
}
