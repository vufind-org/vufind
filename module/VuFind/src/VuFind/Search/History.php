<?php

namespace VuFind\Search;

use minSO;
use Zend\ServiceManager\ServiceManager;

class History
{

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getSearchHistory($userId = null, $purged = false)
    {
        // Retrieve search history

        $searchTable = $this->serviceManager->get('VuFind\DbTablePluginManager')
            ->get("Search");

        $searchHistory = $searchTable->getSearches(
            $this->serviceManager->get('VuFind\SessionManager')->getId(),
            $userId
        );

        $resultsManager = $this->serviceManager->get('VuFind\SearchResultsPluginManager');

        $searchMemory = $this->serviceManager->get('VuFind\Search\Memory');

        // Build arrays of history entries
        $saved = $unsaved = [];



        // Loop through the history
        /** @var  $current */
        foreach ($searchHistory as $current) {

            /** @var minSO $minSO */
            $minSO = $current->getSearchObject();

            // Saved searches
            if ($current->saved == 1) {
                $saved[] = $minSO->deminify($resultsManager);
            } else {
                // All the others...

                // If this was a purge request we don't need this
                if ($purged) {
                    $current->delete();

                    // We don't want to remember the last search after a purge:
                    $searchMemory->forgetSearch();
                } else {
                    // Otherwise add to the list
                    $unsaved[] = $minSO->deminify($resultsManager);
                }
            }
        }

        return ['saved' => $saved, 'unsaved' => $unsaved];
    }
}