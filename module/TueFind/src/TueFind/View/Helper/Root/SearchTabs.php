<?php

namespace TueFind\View\Helper\Root;

class SearchTabs extends \VuFind\View\Helper\Root\SearchTabs {

    protected function remapBasicSearch($activeOptions, $targetClass, $query,
        $handler, $filters
    ) {
        // Set up results object for URL building:
        $results = $this->results->get($targetClass);
        $params = $results->getParams();
        foreach ($filters as $filter) {
            $params->addHiddenFilter($filter);
        }

        // Overwrite VuFind default functionality
        // On change of tab fall back on the Tab Default hander to avoid
        // selecting non existing handlers in the other tab
        $options = $results->getOptions();
        $targetHandler = $options->getDefaultHandler();
         
        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return $this->url->__invoke($options->getSearchAction())
            . $results->getUrlQuery()->getParams(false);
    }

}
