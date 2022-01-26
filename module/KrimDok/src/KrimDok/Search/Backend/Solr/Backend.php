<?php
namespace KrimDok\Search\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Exception\RemoteErrorException;

class Backend extends \TueFindSearch\Backend\Solr\Backend
{
    /**
     * Obtain information from an alphabetic browse index.
     *
     * @param string   $source      Name of index to search
     * @param string   $from        Starting point for browse results
     * @param int      $page        Result page to return (starts at 0)
     * @param int      $limit       Number of results to return on each page
     * @param ParamBag $params      Additional parameters
     * POST)
     * @param int      $offsetDelta Delta to use when calculating page
     * offset (useful for showing a few results above the highlighted row)
     *
     * @return array
     */
    public function alphabeticBrowse($source, $from, $page, $limit = 20,
        $params = null, $offsetDelta = 0, $filterBy = ""
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);
        $params->set('from', $from);
        $params->set('offset', ($page * $limit) + $offsetDelta);
        $params->set('rows', $limit);
        $params->set('source', $source);
        if (!empty($filterBy))
            $params->set('filterBy', $filterBy);
        try {
            $response = $this->connector->query('browse', $params);
        } catch (RemoteErrorException $e) {
            $this->refineBrowseException($e);
        }
        return $this->deserialize($response);
    }
}
