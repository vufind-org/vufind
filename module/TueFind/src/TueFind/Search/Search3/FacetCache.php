<?php
namespace TueFind\Search\Search3;

class FacetCache extends \VuFind\Search\Base\FacetCache
{
    /**
     * Get the namespace to use for caching facets.
     *
     * @return string
     */
    protected function getCacheNamespace()
    {
        return 'search3-facets';
    }
}
