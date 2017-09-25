<?php
/**
 * Created by PhpStorm.
 * User: quboo01
 * Date: 04.11.15
 * Time: 11:27
 */

namespace KrimDok\ILS\Driver;

use VuFind\ILS\Driver\NoILS;
use VuFind\Record\Loader;
use VuFindSearch\Query\Query as Query, VuFindSearch\Service as SearchService;
use VuFindSearch\ParamBag;

class KrimDok extends NoILS
{
    protected $searchService;

    /**
     * Constructor
     *
     * @param Loader $loader Record loader
     * @param SearchService $searchService
     */
    public function __construct(Loader $loader, SearchService $searchService)
    {
        parent::__construct($loader);
        $this->searchService = $searchService;
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page Page number of results to retrieve (counting starts at 1)
     * @param int $limit The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $query = new Query(' tue_local_indexed_date:[NOW-' . ($daysOld) . 'DAY TO NOW]');
        $offset = ($page - 1) * $limit;
        $search_results = $this->searchService->search("Solr", $query, $offset, $limit, new ParamBag(['fl' => 'id']));
        $records = $search_results->getRecords();
        $results = [];

        foreach ($records as $record) {
            $results[] = ['id' => $record->getUniqueID()];
        }
        return ['count' => count($results), 'results' => $results];
    }

    /**
     * Get the name of the search backend providing records.
     *
     * @return string
     */
    protected function getRecordSource()
    {
        return isset($this->config['Records']['source'])
            ? $this->config['Records']['source'] : 'VuFind';
    }
}
