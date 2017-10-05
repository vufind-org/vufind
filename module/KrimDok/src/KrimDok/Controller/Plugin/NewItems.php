<?php
namespace KrimDok\Controller\Plugin;

use \Zend\ServiceManager\ServiceLocatorAwareTrait;
use \Zend\ServiceManager\ServiceLocatorAwareInterface;

class NewItems extends \VuFind\Controller\Plugin\NewItems implements ServiceLocatorAwareInterface {

    use ServiceLocatorAwareTrait;

    /**
     * - This function is needed to raise the limit from perPage * resultPages (e.g. 200)
     *   to the QueryIDLimit, which defaults to 1024.
     * - This function doesnt call $catalog->getNewItems correctly due to a
     *   weakness in the vufind architecture.
     *   see: https://sourceforge.net/p/vufind/mailman/message/35840898/
     * - It should pass the current page number and page limit for the current page to solr, but this
     *   is not possible because the vufind core needs a list of all IDs on all pages as result..
     * - ID Filtering for the current page is done afterwards manually by VuFind.
     *
     * @param \VuFind\ILS\Connection                     $catalog ILS connection
     * @param \VuFind\Search\Solr\Params                 $params  Solr parameters
     * @param string                                     $range   Range setting
     * @param string                                     $dept    Department setting
     * @param \Zend\Mvc\Controller\Plugin\FlashMessenger $flash   Flash messenger
     *
     * @return array
     */
    public function getBibIDsFromCatalog($catalog, $params, $range, $dept, $flash)
    {
        $resultPages = $this->getResultPages();
        $perPage = $params->getLimit();
        $maxResultSize = $perPage * $resultPages;
        $limit = $params->getQueryIDLimit();
        if($maxResultSize < $limit) {
            $maxResultSize = $limit;
        }
        $newItems = $catalog->getNewItems(1, $maxResultSize, $range, $dept);

        // Build a list of unique IDs
        $bibIDs = [];
        if (isset($newItems['results'])) {
            for ($i = 0; $i < count($newItems['results']); $i++) {
                $bibIDs[] = $newItems['results'][$i]['id'];
            }
        }

        // Truncate the list if it is too long:
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $flash->addMessage('too_many_new_items', 'info');
        }

        return $bibIDs;
    }
}
