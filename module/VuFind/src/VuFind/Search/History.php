<?php

namespace VuFind\Search;

use minSO;

class History
{

    /**
     * @var \VuFind\Db\Table\Search
     */
    protected $searchTable;

    /**
     * @var \Zend\Session\SessionManager
     */
    protected $sessionManager;

    /**
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * @var \VuFind\Search\Memory
     */
    protected $searchMemory;

    /**
     * History constructor
     * @param \VuFind\Db\Table\Search $searchTable
     * @param \Zend\Session\SessionManager $sessionManager
     * @param \VuFind\Search\Results\PluginManager $resultsManager
     * @param \VuFind\Search\Memory $searchMemory
     */
    public function __construct($searchTable, $sessionManager, $resultsManager, $searchMemory)
    {
        $this->searchTable = $searchTable;
        $this->sessionManager = $sessionManager;
        $this->resultsManager = $resultsManager;
        $this->searchMemory = $searchMemory;
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getSearchHistory($userId = null, $purged = false)
    {
        // Retrieve search history
        $searchHistory = $this->searchTable->getSearches(
            $this->sessionManager->getId(),
            $userId
        );

        // Build arrays of history entries
        $saved = $unsaved = [];

        // Loop through the history
        /** @var \VuFind\Db\Row\Search $current */
        foreach ($searchHistory as $current) {

            /** @var minSO $minSO */
            $minSO = $current->getSearchObject();

            // Saved searches
            if ($current->saved == 1) {
                $saved[] = $minSO->deminify($this->resultsManager);
            } else {
                // All the others...

                // If this was a purge request we don't need this
                if ($purged) {
                    $current->delete();

                    // We don't want to remember the last search after a purge:
                    $this->searchMemory->forgetSearch();
                } else {
                    // Otherwise add to the list
                    $unsaved[] = $minSO->deminify($this->resultsManager);
                }
            }
        }

        return ['saved' => $saved, 'unsaved' => $unsaved];
    }
}