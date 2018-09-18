<?php
namespace IxTheo\Search\PDASubscriptions;
use IxTheo\Db\Table\PDASubscription as PDASubscriptionTable,
    VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Record\Loader,
    VuFind\Search\Base\Params as BaseParams,
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
     * DB table
     * @var \IxTheo\Db\Table\PDASubscription
     */
    protected $pdasubscriptionTable = null;

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
            $list = $this->pdasubscriptionTable->get($this->user->id, $this->getParams()->getSort(), $this->getStartRecord() - 1, $limit);
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($list as $row) {
            $recordsToRequest[] = [
                'id' => $row->book_ppn,
                'source' => 'Solr'
            ];
        }

        $this->recordLoader->setCacheContext("PDASubscription");
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
            $this->list = $this->pdasubscriptionTable->getAll($this->user->id, $this->getParams()->getSort());
        }
        return $this->list;
    }

    public function setPDAsubscriptionTable(PDASubscriptionTable $pdasubscriptionTable) {
        $this->pdasubscriptionTable = $pdasubscriptionTable;
    }
}
