<?php

namespace TueFind\Search\SolrAuthorFacets;

use VuFindSearch\ParamBag;

class Params extends \VuFind\Search\SolrAuthorFacets\Params
{
    public function getBackendParametersAuthorAndIdFacet()
    {
        $backendParams = new ParamBag();

        // Spellcheck
        $backendParams->set(
            'spellcheck', $this->getOptions()->spellcheckEnabled() ? 'true' : 'false'
        );

        // Facets
        $facets = $this->getFacetSettings();
        if (!empty($facets)) {
            $backendParams->add('facet', 'true');

            foreach ($facets as $key => $value) {
                // prefix keys with "facet" unless they already have a "f." prefix:
                $fullKey = substr($key, 0, 2) == 'f.' ? $key : "facet.$key";
                $backendParams->add($fullKey, $value);
            }
            $backendParams->add('facet.mincount', 1);
        }

        // Filters
        $filters = $this->getFilterSettings();
        foreach ($filters as $filter) {
            $backendParams->add('fq', $filter);
        }

        // Shards
        $allShards = $this->getOptions()->getShards();
        $shards = $this->getSelectedShards();
        if (empty($shards)) {
            $shards = array_keys($allShards);
        }

        // If we have selected shards, we need to format them:
        if (!empty($shards)) {
            $selectedShards = [];
            foreach ($shards as $current) {
                $selectedShards[$current] = $allShards[$current];
            }
            $shards = $selectedShards;
            $backendParams->add('shards', implode(',', $selectedShards));
        }

        // Sort
        $sort = $this->getSort();
        if ($sort) {
            // If we have an empty search with relevance sort as the primary sort
            // field, see if there is an override configured:
            $sortFields = explode(',', $sort);
            $allTerms = trim($this->getQuery()->getAllTerms());
            if ('relevance' === $sortFields[0]
                && ('' === $allTerms || '*:*' === $allTerms)
                && ($relOv = $this->getOptions()->getEmptySearchRelevanceOverride())
            ) {
                $sort = $relOv;
            }
            $backendParams->add('sort', $this->normalizeSort($sort));
        }

        // Highlighting -- on by default, but we should disable if necessary:
        if (!$this->getOptions()->highlightEnabled()) {
            $backendParams->add('hl', 'false');
        }

        // Pivot facets for visual results

        if ($pf = $this->getPivotFacets()) {
            $backendParams->add('facet.pivot', $pf);
        }

        // TueFind: Use author_id_and_facet instead of regular parameters
        $backendParams->remove('fl');
        $backendParams->add('fl', 'id,author_and_id_facet');
        $backendParams->remove('facet.field');
        $backendParams->add('facet.field', 'author_and_id_facet');

        return $backendParams;
    }
}
