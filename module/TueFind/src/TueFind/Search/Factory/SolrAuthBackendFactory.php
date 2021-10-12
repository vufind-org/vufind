<?php

namespace TueFind\Search\Factory;

use TueFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;

class SolrAuthBackendFactory extends \VuFind\Search\Factory\SolrAuthBackendFactory
{
    /**
     * Same code as parent, but uses TueFind's QueryBuilder instead.
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $config = $this->config->get($this->mainConfig);
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
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans, $caseSensitiveRanges
        );
        $builder->setLuceneHelper($helper);

        return $builder;
    }
}
