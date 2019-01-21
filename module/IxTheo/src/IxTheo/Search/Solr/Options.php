<?php

namespace IxTheo\Search\Solr;

class Options extends \TueFind\Search\Solr\Options
{
    /**
     * Searches with forced own default sort only
     *
     * @var array
     */
    protected $forceDefaultSortSearches = [];

    /**
     * Constructor, used to parse IxTheo-specific options from searches.ini
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $searchSettings = $configLoader->get($this->searchIni);

        if (isset($searchSettings->IxTheo->forceDefaultSortSearches)) {
            $this->forceDefaultSortSearches = $searchSettings->IxTheo->forceDefaultSortSearches->toArray();
        }
    }

    /**
     * Get searches with forced own default sort only
     *
     * @return array
     */
    public function getForceDefaultSortSearches() {
        return $this->forceDefaultSortSearches;
    }
}
