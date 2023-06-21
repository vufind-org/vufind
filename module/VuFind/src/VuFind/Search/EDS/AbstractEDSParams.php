<?php

namespace VuFind\Search\EDS;

use VuFindSearch\ParamBag;

class AbstractEDSParams extends \VuFind\Search\Base\Params
{
    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params  Parameter collection to update
     *
     * @return void
     */
    public function createBackendFilterParameters(ParamBag $params)
    {
        // Which filters should be applied to our query?
        $filterList = $this->getFilterList();
        $hiddenFilterList = $this->getHiddenFilters();
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    // Standard case:
                    $fq = "{$filt['field']}:{$filt['value']}";
                    $params->add('filters', $fq);
                }
            }
        }
        if (!empty($hiddenFilterList)) {
            foreach ($hiddenFilterList as $field => $hiddenFilters) {
                foreach ($hiddenFilters as $value) {
                    // Standard case:
                    $hfq = "{$field}:{$value}";
                    $params->add('filters', $hfq);
                }
            }
        }
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getView()
    {
        $viewArr = explode('|', $this->view ?? '');
        return $viewArr[0];
    }

}
