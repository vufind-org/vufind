<?php
namespace TueFind\RecordDriver;



class Search3Default extends \IxTheo\RecordDriver\SolrDefault
//class Search3Default extends \VuFind\RecordDriver\SolrDefault
{

    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'Search3';

    /**
     * Get the Hierarchy Type (false if none)
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        return parent::getHierarchyType() ? 'search3' : false;
    }
}
