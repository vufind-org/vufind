<?php

/**
 * Abstract factory for SOLR backends.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use Finna\Search\Solr\DeduplicationListener;
use Finna\Search\Solr\SolrExtensionsListener;

use FinnaSearch\Backend\Solr\LuceneSyntaxHelper;
use FinnaSearch\Backend\Solr\QueryBuilder;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;

/**
 * Abstract factory for SOLR backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrDefaultBackendFactory
    extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
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
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $factory = new RecordCollectionFactory(
            [$manager, 'getSolrRecord'],
            'FinnaSearch\Backend\Solr\Response\Json\RecordCollection'
        );
        $backend->setRecordCollectionFactory($factory);
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
        parent::createListeners($backend);

        $events = $this->serviceLocator->get('SharedEventManager');

        // Finna Solr Extensions
        $solrExtensions = new SolrExtensionsListener(
            $backend,
            $this->serviceLocator,
            $this->searchConfig,
            $this->facetConfig
        );
        $solrExtensions->attach($events);
    }

    /**
     * Create the query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $config = $this->config->get('config');
        $defaultDismax = isset($config->Index->default_dismax_handler)
            ? $config->Index->default_dismax_handler : 'dismax';
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $search = $this->config->get($this->searchConfig);
        $caseSensitiveBooleans
            = isset($search->General->case_sensitive_bools)
            ? $search->General->case_sensitive_bools : true;
        $caseSensitiveRanges
            = isset($search->General->case_sensitive_ranges)
            ? $search->General->case_sensitive_ranges : true;
        $unicodeNormalizationForm
            = isset($search->General->unicode_normalization_form)
            ? $search->General->unicode_normalization_form : 'NFKC';
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans, $caseSensitiveRanges, $unicodeNormalizationForm
        );
        $builder->setLuceneHelper($helper);

        return $builder;
    }

    /**
     * Get a deduplication listener for the backend
     *
     * @param BackendInterface $backend Search backend
     * @param bool             $enabled Whether deduplication is enabled
     *
     * @return Finna\Search\Solr\DeduplicationListener
     */
    protected function getDeduplicationListener(BackendInterface $backend, $enabled)
    {
        return new DeduplicationListener(
            $backend,
            $this->serviceLocator,
            $this->searchConfig,
            'datasources',
            $enabled
        );
    }

}
