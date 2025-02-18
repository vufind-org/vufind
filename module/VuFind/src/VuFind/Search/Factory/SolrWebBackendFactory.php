<?php

/**
 * Factory for the website SOLR backend.
 *
 * PHP version 8
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

/**
 * Factory for the website SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrWebBackendFactory extends AbstractSolrBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchConfig = 'website';
        $this->searchYaml = 'websearchspecs.yaml';
        $this->facetConfig = 'website';
        $this->defaultIndexName = 'website';
    }

    /**
     * Get the callback for creating a record.
     *
     * Returns a callable or null to use RecordCollectionFactory's default method.
     *
     * @return callable|null
     */
    protected function getCreateRecordCallback(): ?callable
    {
        $manager = $this->getService(\VuFind\RecordDriver\PluginManager::class);
        return function ($data) use ($manager) {
            // Extract highlighting details injected earlier by
            // \VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory
            $hl = $data['__highlight_details'] ?? [];
            unset($data['__highlight_details']);

            $driver = $manager->get('SolrWeb');
            $driver->setRawData($data);
            $driver->setHighlightDetails($hl);
            return $driver;
        };
    }
}
