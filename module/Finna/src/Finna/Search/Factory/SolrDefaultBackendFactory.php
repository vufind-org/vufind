<?php

/**
 * Abstract factory for SOLR backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use FinnaSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Backend\Solr\Backend;

use function is_array;

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
class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
    /**
     * Callback for creating a record driver.
     *
     * @var string
     */
    protected $createRecordMethod = 'getSolrRecord';

    /**
     * Solr backend class
     *
     * @var string
     */
    protected $backendClass = \FinnaSearch\Backend\Solr\Backend::class;

    /**
     * Record collection class for RecordCollectionFactory
     *
     * @var string
     */
    protected $recordCollectionClass = RecordCollection::class;

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
        $searchConfig = $this->configManager->getConfigArray($this->searchConfig);
        if (!isset($searchConfig['Records']['deduplication'])) {
            $events = $this->serviceLocator->get('SharedEventManager');
            $this->getDeduplicationListener($backend, false)->attach($events);
        }

        $events = $this->serviceLocator->get('SharedEventManager');

        // Finna Solr Extensions
        $solrExtensions = new SolrExtensionsListener(
            $backend->getIdentifier(),
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
        $specs  = $this->loadSpecs();
        $config = $this->configManager->getConfigArray('config');
        $defaultDismax = $config['Index']['default_dismax_handler'] ?? 'dismax';

        // Remove ExactSettings unless explicitly enabled:
        $searchConfig = $this->configManager->getConfigArray($this->searchConfig);
        if (!($searchConfig['General']['enable_exact_phrase_search'] ?? false)) {
            foreach ($specs as $handler => $spec) {
                if (isset($spec['ExactSettings'])) {
                    unset($specs[$handler]['ExactSettings']);
                }
            }
        }

        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $caseSensitiveBooleans = $searchConfig['General']['case_sensitive_bools'] ?? true;
        $caseSensitiveRanges = $searchConfig['General']['case_sensitive_ranges'] ?? true;
        $unicodeNormalizationForm
            = $searchConfig['General']['unicode_normalization_form'] ?? 'NFKC';
        $searchFilters = $config['Index']['search_filters'] ?? [];
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans,
            $caseSensitiveRanges,
            $unicodeNormalizationForm,
            $searchFilters
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
            $this->configManager->getConfigObject($this->searchConfig),
            $this->uniqueKey
        );
    }

    /**
     * Get a deduplication listener for the backend
     *
     * @param Backend $backend Search backend
     * @param bool    $enabled Whether deduplication is enabled
     *
     * @return Finna\Search\Solr\DeduplicationListener
     */
    protected function getDeduplicationListener(Backend $backend, $enabled)
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

        if (!getenv('VUFIND_API_CALL')) {
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
        $config ??= $this->mainConfig;
        if (is_array($url) && !empty($this->config->get($config)->Index->shuffle)) {
            shuffle($url);
        }
        return $url;
    }
}
