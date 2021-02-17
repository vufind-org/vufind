<?php
/**
 * Trait for a shared createQueryBuilder method in Solr backend factories
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2020.
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

use FinnaSearch\Backend\Solr\LuceneSyntaxHelper;
use FinnaSearch\Backend\Solr\QueryBuilder;

/**
 * Trait for a shared createQueryBuilder method in Solr backend factories
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait SolrCreateQueryBuilderTrait
{
    /**
     * Create the query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $specs  = $this->loadSpecs();
        $config = $this->config->get('config');
        $defaultDismax = $config->Index->default_dismax_handler ?? 'dismax';
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $search = $this->config->get($this->searchConfig);
        $caseSensitiveBooleans
            = $search->General->case_sensitive_bools ?? true;
        $caseSensitiveRanges
            = $search->General->case_sensitive_ranges ?? true;
        $unicodeNormalizationForm
            = $search->General->unicode_normalization_form ?? 'NFKC';
        $searchFilters
            = isset($config->Index->search_filters)
            ? $config->Index->search_filters->toArray() : [];
        // Add user messages to search filters from configuration:
        $searchFiltersMessages
            = isset($config->Index->search_filters_messages)
            ? $config->Index->search_filters_messages->toArray() : [];
        foreach ($searchFiltersMessages as $i => $message) {
            if (isset($searchFilters[$i])) {
                $searchFilters[$i] = [
                    'filter' => $searchFilters[$i],
                    'message' => $message
                ];
            }
        }
        // Add user messages to search filters with not configured message:
        foreach ($searchFilters as &$filter) {
            if (!is_array($filter)) {
                $filter = [
                    'filter' => $filter,
                    'message' => 'search_filter_invalid_query'
                ];
            }
        }
        unset($filter);
        $maxSpellcheckWords = $search->General->max_spellcheck_words ?? 5;
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
}
