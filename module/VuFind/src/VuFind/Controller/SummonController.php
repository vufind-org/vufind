<?php
/**
 * Summon Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use Zend\Mvc\MvcEvent;

/**
 * Summon Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SummonController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'Summon';
        parent::__construct();
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('Summon');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }

    /**
     * Use preDispatch event to add Summon message.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function preDispatch(MvcEvent $e)
    {
        $this->layout()->poweredBy
            = 'Powered by Summon™ from Serials Solutions, a division of ProQuest.';
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'preDispatch'], 1000);
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        $view = parent::advancedAction();

        // Set up facet information:
        $view->facetList = $this->processAdvancedFacets(
            $this->getAdvancedFacets()->getFacetList(), $view->saved
        );
        $specialFacets = $this->parseSpecialFacetsSetting(
            $view->options->getSpecialAdvancedFacets()
        );
        if (isset($specialFacets['checkboxes'])) {
            $view->checkboxFacets = $this->processAdvancedCheckboxes(
                $specialFacets['checkboxes'], $view->saved
            );
        }
        $view->ranges = $this
            ->getAllRangeSettings($specialFacets, $view->saved, 'Summon');

        return $view;
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->createViewModel(
            ['results' => $this->getHomePageFacets()]
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache.
     *
     * @return \VuFind\Search\Summon\Results
     */
    protected function getAdvancedFacets()
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('object');
        if (!($results = $cache->getItem('summonSearchAdvancedFacets'))) {
            $config = $this->getServiceLocator()->get('VuFind\Config')
                ->get('Summon');
            $limit = isset($config->Advanced_Facet_Settings->facet_limit)
                ? $config->Advanced_Facet_Settings->facet_limit : 100;
            $results = $this->getResultsManager()->get('Summon');
            $params = $results->getParams();
            $facetsToShow = isset($config->Advanced_Facets)
                 ? $config->Advanced_Facets
                 : ['Language' => 'Language', 'ContentType' => 'Format'];
            if (isset($config->Advanced_Facet_Settings->orFacets)) {
                $orFields = array_map(
                    'trim', explode(',', $config->Advanced_Facet_Settings->orFacets)
                );
            } else {
                $orFields = [];
            }
            foreach ($facetsToShow as $facet => $label) {
                $useOr = (isset($orFields[0]) && $orFields[0] == '*')
                    || in_array($facet, $orFields);
                $params->addFacet(
                    $facet . ',or,1,' . $limit, $label, $useOr
                );
            }

            // We only care about facet lists, so don't get any results:
            $params->setLimit(0);

            // force processing for cache
            $results->getResults();

            $cache->setItem('summonSearchAdvancedFacets', $results);
        }

        // Restore the real service locator to the object (it was lost during
        // serialization):
        $results->restoreServiceLocator($this->getServiceLocator());
        return $results;
    }

    /**
     * Return a Search Results object containing homepage facet information.  This
     * data may come from the cache.
     *
     * @return \VuFind\Search\Summon\Results
     */
    protected function getHomePageFacets()
    {
        // For now, we'll use the same fields as the advanced search screen.
        return $this->getAdvancedFacets();
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
                    . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject
                    && $searchObject->getParams()->hasFilter($fullFilter)
                ) {
                    $facetList[$facet]['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }
}

