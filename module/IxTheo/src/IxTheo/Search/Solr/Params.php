<?php

namespace IxTheo\Search\Solr;

class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Overwrite sort for BibleRangeSearch
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return string
     */
    protected function initSort($request)
    {
        $handler = $this->query->getHandler();
        if (in_array($handler, $this->getOptions()->getForceDefaultSortSearches())) {
            $this->setSort($this->getOptions()->getDefaultSortByHandler($handler));
        } else {
            parent::initSort($request);
        }
    }
}
