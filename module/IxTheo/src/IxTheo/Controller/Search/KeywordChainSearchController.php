<?php

namespace IxTheo\Controller\Search;

use Zend\ServiceManager\ServiceLocatorInterface;

class KeywordChainSearchController extends \VuFind\Controller\AbstractSearch
{
    // Try to implement KWC based on the Browse Controller


    /**
     * Constructor
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'KeywordChainSearch';
        parent::__construct($sm);
    }


    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;


    /**
     * Helper class that adds quotes around the values of an array
     *
     * @param array $array Two-dimensional array where each entry has a value param
     *
     * @return array       Array indexed by value with text of displayText and count
     */
    protected function quoteValues($array)
    {
        foreach ($array as $i => $result) {
            $result['value'] = '"' . $result['value'] . '"';
            $array[$i] = $result;
        }
        return $array;
    }


    /**
     * Attach Wildcard to each part of the query string
     *
     *
     */

    protected function appendWildcard($query)
    {
        return preg_replace('~(\w+)~', '$1*', $query);
    }

    protected function configureKeywordChainSearch($request, $sort)
    {
        $facet = 'key_word_chains_sorted';

        $results = $this->getResultsManager()->get($this->searchClassId);
        $params = $results->getParams();
        $params->addFacet($facet);
        $lookfor = $request->get('lookfor');

        $request->set('lookfor', $lookfor);
        $request->set('type', 'KeywordChainSearch');
        $params->initFromRequest($request);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $params->setFacetSort($sort);
        $params->setFacetOffset(($params->getPage() - 1) * $params->getLimit());
        $params->setFacetLimit(-1);
        $results->setParams($params);

        return $results;
    }


    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Zend\View\Model\ViewModel
     */

    protected function createViewModel($params_ext = null)
    {
    }


    /**
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $params = $this->serviceLocator->get('VuFind\SearchParamsPluginManager')->get('KeywordChainSearch');
        return parent::createViewModel(['params' => $params]);
    }


    public function resultsAction()
    {
        $request = new \Zend\Stdlib\Parameters(
            $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray()
        );

        $results = $this->configureKeywordChainSearch($request, 'prefix');

        $params = (!empty($results)) ? $results->getParams() : [];


        if (!empty($results)) {
            $view = parent::createViewModel(['params' => $params, 'results' => $results]);
        } else {
            $view = parent::createViewModel(['params' => $params]);
        }
        return $view;
    }


    public function searchAction()
    {
        $this->forwardTo('KeywordChainSearch', 'Results');
    }
}
