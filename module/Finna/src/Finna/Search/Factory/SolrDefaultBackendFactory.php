<?php

/**
 * Abstract factory for SOLR backends.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2019.
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
 * @category VuFind
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
     * Callback for creating a record driver.
     *
     * @var string
     */
    protected $createRecordMethod = 'getSolrRecord';

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new \FinnaSearch\Backend\Solr\Backend($connector);
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setSimilarBuilder($this->createSimilarBuilder());
        if ($this->logger) {
            $backend->setLogger($this->logger);
        }
        $manager
            = $this->serviceLocator->get(\Finna\RecordDriver\PluginManager::class);
        $factory = new RecordCollectionFactory(
            [$manager, $this->createRecordMethod],
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

        // Apply deduplication also if it's not enabled by default (could be enabled
        // by a special filter):
        $search = $this->config->get($this->searchConfig);
        if (!isset($search->Records->deduplication)) {
            $events = $this->serviceLocator->get('SharedEventManager');
            $this->getDeduplicationListener($backend, false)->attach($events);
        }

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
        $searchFilters
            = isset($config->Index->search_filters)
            ? $config->Index->search_filters : [];
        $maxSpellcheckWords = isset($search->General->max_spellcheck_words)
            ? $search->General->max_spellcheck_words : 5;
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans,
            $caseSensitiveRanges,
            $unicodeNormalizationForm,
            $searchFilters,
            $maxSpellcheckWords
        );
        $builder->setLuceneHelper($helper);

        return $builder;
    }

    /**
     * Create the similar records query builder.
     *
     * @return \VuFindSearch\Backend\Solr\SimilarBuilder
     */
    protected function createSimilarBuilder()
    {
        return new \FinnaSearch\Backend\Solr\SimilarBuilder(
            $this->config->get($this->searchConfig), $this->uniqueKey
        );
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

    /**
     * Get all hidden filter settings.
     *
     * @return array
     */
    protected function getHiddenFilters()
    {
        $hf = parent::getHiddenFilters();

        if (!isset($_ENV['VUFIND_API_CALL']) || !$_ENV['VUFIND_API_CALL']) {
            return $hf;
        }
        $search = $this->config->get($this->searchConfig);

        // API hidden filters
        if (isset($search->ApiHiddenFilters)) {
            foreach ($search->ApiHiddenFilters as $filter) {
                $hf[] = $filter;
            }
        }

        return $hf;
    }

    /**
     * Get the Solr URL.
     *
     * @param string $config name of configuration file (null for default)
     *
     * @return string|array
     */
    protected function getSolrUrl($config = null)
    {
        $url = parent::getSolrUrl();
        $config = $config ?? $this->mainConfig;
        if (is_array($url) && !empty($this->config->get($config)->Index->shuffle)) {
            shuffle($url);
        }
        return $url;
    }
}
