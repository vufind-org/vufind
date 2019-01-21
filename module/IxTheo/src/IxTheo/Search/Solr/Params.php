<?php

namespace IxTheo\Search\Solr;

class Params extends \TueFind\Search\Solr\Params
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
        if ($this->query instanceof \VuFindSearch\Query\Query && in_array($this->query->getHandler(), $this->getOptions()->getForceDefaultSortSearches())) {
            $this->setSort($this->getOptions()->getDefaultSortByHandler($this->query->getHandler()));
        } else {
            parent::initSort($request);
        }
    }
}
