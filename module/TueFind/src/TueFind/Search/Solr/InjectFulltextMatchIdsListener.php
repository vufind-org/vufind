<?php
namespace TueFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;
use Zend\EventManager\SharedEventManagerInterface;

class InjectFulltextMatchIdsListener
{

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Is highlighting active?
     *
     * @var bool
     */
    protected $active = false;


    /**
     * Active Fulltext Type Filters
     *
     */
    protected $selected_fulltext_types;


    public function __construct(BackendInterface $backend)
    {
       $this->backend = $backend;
    }
   /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
    }


    /**
     * GetSearchHandlerName
     * @return string
     */
    protected function getSearchHandlerName(EventInterface $event) {
        $query = $event->getParam('query');
        if ($query instanceof \VuFindSearch\Query\Query)
            return $query->getHandler();
        if ($query instanceof \VuFindSearch\Query\QueryGroup)
            return $query->getReducedHandler();
        return "";
    }


    protected function getFulltextFilterFromFulltextTypeFacet($backend, $params) {
        $filter_queries = $params->get('fq');
        if ($filter_queries == null)
            return "";
        $selected_fulltext_types = [];
        foreach ($filter_queries as $filter_query) {
            if (!preg_match('/{!tag=fulltext_types_filter}fulltext_types:\((.*)\)/', $filter_query))
                continue;
            $fulltext_type_facet_expression = $backend->getQueryBuilder()->getLuceneHelper()->extractSearchTerms($filter_query);
            $fulltext_types_enabled = array_filter(explode(' ', preg_replace('/(AND|OR)/', '', $fulltext_type_facet_expression)));
            $selected_fulltext_types = array_map(function ($term) { return preg_replace('/"/', '', $term);}, $fulltext_types_enabled);
        }
        return $selected_fulltext_types;
   }


   /**
    * Set up highlighting parameters.
    *
    * @param EventInterface $event Event
    *
    * @return EventInterface
    */
    public function onSearchPre(EventInterface $event) {
        if ($event->getParam('context') != 'search') {
            return $event;
        }
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            if ($params) {
                if ($backend->getIdentifier() == 'Search2') {
                    $this->active = true;
                    $params->set('debug', 'results');
                    $params->set('debug.explain.structured', 'true');
                    $this->backend->getQueryBuilder()->setIncludeFulltextSnippets(true);
                    // Pass filter from chosen fulltext_type facet
                    $this->selected_fulltext_types = $this->getFulltextFilterFromFulltextTypeFacet($backend, $params);
                    $this->backend->getQueryBuilder()->setSelectedFulltextTypes($this->selected_fulltext_types);
                }
            }
        }
        return $event;
    }


   /**
     * Inject highlighting results.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        // Do nothing if highlighting is disabled or context is wrong
        if (!$this->active || $event->getParam('context') != 'search') {
            return $event;
        }

        // Inject highlighting details into record objects:
        $backend = $event->getParam('backend');
        if ($backend == $this->backend->getIdentifier()) {
            $result = $event->getTarget();
            if ($backend == 'Search2' && !empty($event->getParams('params')['query']->getString())) {
                foreach ($result->getRecords() as $record) {
                    $record->setHasFulltextMatch();
                    $record->setFulltextTypeFilters($this->selected_fulltext_types);
                }
            }
        }
    }
}
