<?php
namespace IxTheo\Search\Subscriptions;
use IxTheo\Db\Table\Subscription as SubscriptionTable,
    VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Record\Loader,
    VuFind\Search\Base\Results as BaseResults,
    VuFindSearch\Service as SearchService,
    ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

class Results extends BaseResults
    implements AuthorizationServiceAwareInterface
{
    use AuthorizationServiceAwareTrait;

    /**
     * Object if user is logged in, false otherwise.
     *
     * @var \VuFind\Db\Row\User|bool
     */
    protected $user = null;

    /**
     * Active user list (false if none).
     *
     * @var \VuFind\Db\Row\UserList|bool
     */
    protected $list = false;

    /**
     *
     * @var \IxTheo\Db\Table\Subscription
     */
    protected $subscriptionTable = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     * search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     * @param SubscriptionTable          $subscriptionTable Subscription table
     */
    public function __construct(\VuFind\Search\Base\Params $params,
        SearchService $searchService, Loader $recordLoader,
        SubscriptionTable $subscriptionTable
    ) {
        parent::__construct($params, $searchService, $recordLoader);
        $this->subscriptionTable = $subscriptionTable;
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        return [];
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @throws ListPermissionException
     */
    protected function performSearch()
    {
        $auth = $this->getAuthorizationService();
        $this->user = $auth ? $auth->getIdentity() : false;
        $list = $this->getListObject();

        if (is_null($list) && !$this->user) {
            throw new ListPermissionException('Cannot retrieve subscriptions without logged in user.');
        }
        $this->resultTotal = count($list->toArray());

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $list = $this->subscriptionTable->get($this->user->id, $this->getParams()->getSort(), $this->getStartRecord() - 1, $limit);
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($list as $row) {
            $recordsToRequest[] = [
                'id' => $row->journal_control_number,
                'source' => 'Solr'
            ];
        }

        $this->recordLoader->setCacheContext("Subscription");
        $this->results = $this->recordLoader->loadBatch($recordsToRequest);
    }

    /**
     * Get the list object associated with the current search (null if no list
     * selected).
     *
     * @return \VuFind\Db\Row\UserList|null
     */
    public function getListObject()
    {
        // If we haven't previously tried to load a list, do it now:
        if ($this->list === false) {
            $this->list = $this->subscriptionTable->getAll($this->user->id, $this->getParams()->getSort());
        }
        return $this->list;
    }

    /**
     * Get Results, sorted on PHP side
     * (by title, which is not stored in MySQL due to redundancy issues)
     *
     * "Missing" records will be hidden
     * (e.g. if a user has subscribed a record in IxTheo and opens "MyResearch" in RelBib,
     * it can't be displayed there, cause it's not part of the index)
     *
     * @return array
     */
    public function getResultsSorted()
    {
        $results = $this->getResults();
        $results_sorted = [];

        foreach ($results as $i => $result) {
            if (!($result instanceof \VuFind\RecordDriver\Missing)) {
                $ppn = $result->getRecordId();
                $title = $result->getTitle();
                $results_sorted[$title . '#' . $ppn] = $result;
            }
        }
        ksort($results_sorted, SORT_LOCALE_STRING);
        return $results_sorted;
    }
}
