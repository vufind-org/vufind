<?php
namespace IxTheo\Search\PDASubscriptions;
use VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Search\Base\Results as BaseResults,
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
            $table = $this->getTable('PDASubscription');
            $list = $table->get($this->user->id, $this->getParams()->getSort(), $this->getStartRecord() - 1, $limit);
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($list as $row) {
            $recordsToRequest[] = [
                'id' => $row->book_ppn,
                'source' => 'Solr'
            ];
        }

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordLoader->setCacheContext("PDASubscription");
        $this->results = $recordLoader->loadBatch($recordsToRequest);
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
            $table = $this->getTable('PDASubscription');
            $this->list = $table->getAll($this->user->id, $this->getParams()->getSort());
        }
        return $this->list;
    }
}
