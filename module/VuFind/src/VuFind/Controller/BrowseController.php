<?php
/**
 * Browse Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader;

/**
 * BrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class BrowseController extends AbstractBase
{
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Current browse mode
     *
     * @var string
     */
    protected $currentAction = null;

    /**
     * Browse options disabled in configuration
     *
     * @var array
     */
    protected $disabledFacets;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = ConfigReader::getConfig();

        $this->disabledFacets = array();
        foreach ($this->config->Browse as $key => $setting) {
            if ($setting == false) {
                $this->disabledFacets[] = $key;
            }
        }
    }

    /**
     * Set the name of the current action.
     *
     * @param string $name Name of the current action
     *
     * @return void
     */
    protected function setCurrentAction($name)
    {
        $this->currentAction = $name;
    }

    /**
     * Get the name of the current action.
     *
     * @return string
     */
    protected function getCurrentAction()
    {
        return $this->currentAction;
    }

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Zend\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);

        // Set the current action.
        $currentAction = $this->getCurrentAction();
        if (!empty($currentAction)) {
            $view->currentAction = $currentAction;
        }

        // Initialize the array of top-level browse options.
        $browseOptions = array();

        // First option: tags -- is it enabled in config.ini?  If no setting is
        // found, assume it is active.
        if (!isset($this->config->Browse->tag)
            || $this->config->Browse->tag == true
        ) {
            $browseOptions[] = $this->buildBrowseOption('Tag', 'Tag');
            $view->tagEnabled = true;
        }

        // Read configuration settings for LC / Dewey call number display; default
        // to LC only if no settings exist in config.ini.
        if (!isset($this->config->Browse->dewey)
            && !isset($this->config->Browse->lcc)
        ) {
            $lcc = true;
            $dewey = false;
        } else {
            $lcc = (isset($this->config->Browse->lcc)
                && $this->config->Browse->lcc);
            $dewey = (isset($this->config->Browse->dewey)
                && $this->config->Browse->dewey);
        }

        // Add the call number options as needed -- note that if both options exist,
        // we need to use special text to disambiguate them.
        if ($dewey) {
            $browseOptions[] = $this->buildBrowseOption(
                'Dewey', ($lcc ? 'browse_dewey' : 'Call Number')
            );
            $view->deweyEnabled = true;
        }
        if ($lcc) {
            $browseOptions[] = $this->buildBrowseOption(
                'LCC', ($dewey ? 'browse_lcc' : 'Call Number')
            );
            $view->lccEnabled = true;
        }

        // Loop through remaining browse options.  All may be individually disabled
        // in config.ini, but if no settings are found, they are assumed to be on.
        $remainingOptions = array(
            'Author', 'Topic', 'Genre', 'Region', 'Era'
        );
        foreach ($remainingOptions as $current) {
            $option = strToLower($current);
            if (!isset($this->config->Browse->$option)
                || $this->config->Browse->$option == true
            ) {
                $browseOptions[] = $this->buildBrowseOption($current, $current);
                $option .= 'Enabled';
                $view->$option = true;
            }
        }

        // CARRY
        if ($findby = $this->params()->fromQuery('findby')) {
            $view->findby = $findby;
        }
        if ($query = $this->params()->fromQuery('query')) {
            $view->query = $query;
        }
        if ($category = $this->params()->fromQuery('category')) {
            $view->category = $category;
        }
        $view->browseOptions = $browseOptions;

        return $view;
    }

    /**
     * Build an array containing options describing a top-level Browse option.
     *
     * @param string $action      The name of the Action for this option
     * @param string $description A description of this Browse option
     *
     * @return array              The Browse option array
     */
    protected function buildBrowseOption($action, $description)
    {
        return array('action' => $action, 'description' => $description);
    }

    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $this->setCurrentAction('Home');
        return $this->createViewModel();
    }

    /**
     * Perform the search
     *
     * @param \Zend\View\Model\ViewModel $view View model to modify
     *
     * @return \Zend\View\Model\ViewModel
     */
    protected function performSearch($view)
    {
        // Remove disabled facets
        $facets = $view->categoryList;
        foreach ($this->disabledFacets as $facet) {
            unset($facets[$facet]);
        }
        $view->categoryList = $facets;

        // SEARCH (Tag does its own search)
        if ($this->params()->fromQuery('query')
            && $this->getCurrentAction() != 'Tag'
        ) {
            $results = $this->getFacetList(
                $this->params()->fromQuery('facet_field'),
                $this->params()->fromQuery('query_field'),
                'count', $this->params()->fromQuery('query')
            );
            $resultList = array();
            foreach ($results as $result) {
                $resultList[] = array(
                    'result' => $result['displayText'],
                    'count' => $result['count']
                );
            }
            // Don't make a second filter if it would be the same facet
            $view->paramTitle
                = ($this->params()->fromQuery('query_field') != $this->getCategory())
                ? 'filter[]=' . $this->params()->fromQuery('query_field') . ':'
                    . urlencode($this->params()->fromQuery('query')) . '&'
                : '';
            switch($this->getCurrentAction()) {
            case 'LCC':
                $view->paramTitle .= 'filter[]=callnumber-subject:';
                break;
            case 'Dewey':
                $view->paramTitle .= 'filter[]=dewey-ones:';
                break;
            default:
                $view->paramTitle .= 'filter[]='.$this->getCategory().':';
            }
            $view->paramTitle = str_replace(
                '+AND+',
                '&filter[]=',
                $view->paramTitle
            );
            $view->resultList = $resultList;
        }

        $view->setTemplate('browse/home');
        return $view;
    }

    /**
     * Browse tags
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function tagAction()
    {
        $this->setCurrentAction('Tag');
        $view = $this->createViewModel();

        $view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'popularity'   => 'By Popularity',
            'recent'       => 'By Recent'
        );

        if ($this->params()->fromQuery('findby')) {
            $params = $this->getRequest()->getQuery()->toArray();
            $tagTable = $this->getTable('Tags');
            // Special case -- display alphabet selection if necessary:
            if ($params['findby'] == 'alphabetical') {
                $legalLetters = $this->getAlphabetList();
                $view->secondaryList = $legalLetters;
                // Only display tag list when a valid letter is selected:
                if (isset($params['query'])) {
                    // Note -- this does not need to be escaped because
                    // $params['query'] has already been validated against
                    // the getAlphabetList() method below!
                    $tags = $tagTable->matchText($params['query']);
                    $tagList = array();
                    foreach ($tags as $tag) {
                        if ($tag['cnt'] > 0) {
                            $tagList[] = array(
                                'result' => $tag['tag'],
                                'count' => $tag['cnt']
                            );
                        }
                    }
                    $view->resultList = array_slice(
                        $tagList, 0, $this->config->Browse->result_limit
                    );
                }
            } else {
                // Default case: always display tag list for non-alphabetical modes:
                $tagList = $tagTable->getTagList(
                    $params['findby'],
                    $this->config->Browse->result_limit
                );
                $resultList = array();
                foreach ($tagList as $i=>$tag) {
                    $resultList[$i] = array(
                        'result' => $tag['tag'],
                        'count'    => $tag['cnt']
                    );
                }
                $view->resultList = $resultList;
            }
            $view->paramTitle = 'lookfor=';
            $view->searchParams = array();
        }

        return $this->performSearch($view);
    }

    /**
     * Browse LCC
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function lccAction()
    {
        $this->setCurrentAction('LCC');
        $view = $this->createViewModel();
        list($view->filter, $view->secondaryList) = $this->getSecondaryList('lcc');
        $view->secondaryParams = array(
            'query_field' => 'callnumber-first',
            'facet_field' => 'callnumber-subject'
        );
        $view->searchParams = array();
        return $this->performSearch($view);
    }

    /**
     * Browse Dewey
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function deweyAction()
    {
        $this->setCurrentAction('Dewey');
        $view = $this->createViewModel();
        list($view->filter, $hundredsList) = $this->getSecondaryList('dewey');
        $categoryList = array();
        foreach ($hundredsList as $dewey) {
            $categoryList[$dewey['value']] = $dewey['displayText']
                . ' (' . $dewey['count'] . ')';
        }
        $view->categoryList = $categoryList;
        if ($this->params()->fromQuery('findby')) {
            $secondaryList = $this->quoteValues(
                $this->getFacetList(
                    'dewey-tens',
                    'dewey-hundreds',
                    'count',
                    $this->params()->fromQuery('findby')
                )
            );
            foreach ($secondaryList as $index=>$item) {
                $secondaryList[$index]['value'] .=
                    ' AND dewey-hundreds:'
                    . $this->params()->fromQuery('findby');
            }
            $view->secondaryList = $secondaryList;
            $view->secondaryParams = array(
                'query_field' => 'dewey-tens',
                'facet_field' => 'dewey-ones'
            );
        }
        return $this->performSearch($view);
    }

    /**
     * Generic action function that handles all the common parts of the below actions
     *
     * @param string $currentAction name of the current action. profound stuff.
     * @param array  $categoryList  category options
     * @param string $facetPrefix   if this is true and we're looking
     * alphabetically, add a facet_prefix to the URL
     *
     * @return \Zend\View\Model\ViewModel
     */
    protected function performBrowse($currentAction, $categoryList, $facetPrefix)
    {
        $this->setCurrentAction($currentAction);
        $view = $this->createViewModel();
        $view->categoryList = $categoryList;

        $findby = $this->params()->fromQuery('findby');
        if ($findby) {
            $view->secondaryParams = array(
                'query_field' => $this->getCategory($findby),
                'facet_field' => $this->getCategory($currentAction)
            );
            $view->facetPrefix = $facetPrefix && $findby == 'alphabetical';
            list($view->filter, $view->secondaryList)
                = $this->getSecondaryList($findby);
        }

        return $this->performSearch($view);
    }

    /**
     * Browse Author
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function authorAction()
    {
        $categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'lcc'          => 'By Call Number',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );

        return $this->performBrowse('Author', $categoryList, false);
    }

    /**
     * Browse Topic
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function topicAction()
    {
        $categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );

        return $this->performBrowse('Topic', $categoryList, true);
    }

    /**
     * Browse Genre
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function genreAction()
    {
        $categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );

        return $this->performBrowse('Genre', $categoryList, true);
    }

    /**
     * Browse Region
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function regionAction()
    {
        $categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'era'          => 'By Era'
        );

        return $this->performBrowse('Region', $categoryList, true);
    }

    /**
     * Browse Era
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function eraAction()
    {
        $categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region'
        );

        return $this->performBrowse('Era', $categoryList, true);
    }

    /**
     * Get array with two values: a filter name and a secondary list based on facets
     *
     * @param string $facet the facet we need the contents of
     *
     * @return array
     */
    protected function getSecondaryList($facet)
    {
        $category = $this->getCategory();
        switch($facet) {
        case 'alphabetical':
            return array('', $this->getAlphabetList());
        case 'dewey':
            return array(
                'dewey-tens', $this->quoteValues(
                    $this->getFacetList('dewey-hundreds', $category, 'index')
                )
            );
        case 'lcc':
            return array(
                'callnumber-first', $this->quoteValues(
                    $this->getFacetList('callnumber-first', $category, 'index')
                )
            );
        case 'topic':
            return array(
                'topic_facet', $this->quoteValues(
                    $this->getFacetList('topic_facet', $category)
                )
            );
        case 'genre':
            return array(
                'genre_facet', $this->quoteValues(
                    $this->getFacetList('genre_facet', $category)
                )
            );
        case 'region':
            return array(
                'geographic_facet', $this->quoteValues(
                    $this->getFacetList('geographic_facet', $category)
                )
            );
        case 'era':
            return array(
                'era_facet', $this->quoteValues(
                    $this->getFacetList('era_facet', $category)
                )
            );
        }
    }

    /**
     * Get a list of items from a facet.
     *
     * @param string $facet    which facet we're searching in
     * @param string $category which subfacet the search applies to
     * @param string $sort     how are we ranking these? || 'index'
     * @param string $query    is there a specific query? No = wildcard
     *
     * @return array           Array indexed by value with text of displayText and
     * count
     */
    protected function getFacetList($facet, $category = null,
        $sort = 'count', $query = '[* TO *]'
    ) {
        $sm = $this->getSearchManager();
        $params = $sm->setSearchClassId('Solr')->getParams();
        $params->addFacet($facet);
        if ($category != null) {
            $query = $category . ':' . $query;
        } else {
            $query = $facet . ':' . $query;
        }
        $params->setOverrideQuery($query);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $params->recommendationsEnabled(false);
        $searchObject = $sm->setSearchClassId('Solr')->getResults($params);
        // Get limit from config
        $params->setFacetLimit($this->config->Browse->result_limit);
        $params->setLimit(0);
        // Facet prefix
        if ($this->params()->fromQuery('facet_prefix')) {
            $params->setFacetPrefix($this->params()->fromQuery('facet_prefix'));
        }
        $params->setFacetSort($sort);
        $result = $searchObject->getFacetList();
        if (isset($result[$facet])) {
            // Sort facets alphabetically if configured to do so:
            if (isset($this->config->Browse->alphabetical_order)
                && $this->config->Browse->alphabetical_order
            ) {
                if (isset($this->config->Site->locale)) {
                    setlocale(LC_ALL, $this->config->Site->locale . ".utf8");
                    $callback = function ($a, $b) {
                        return strcoll($a['displayText'], $b['displayText']);
                    };
                    usort($result[$facet]['list'], $callback);
                }
            }
            return $result[$facet]['list'];
        } else {
            return array();
        }
    }

    /**
     * Helper class that adds quotes around the values of an array
     *
     * @param array $array Two-dimensional array where each entry has a value param
     *
     * @return array       Array indexed by value with text of displayText and count
     */
    protected function quoteValues($array)
    {
        foreach ($array as $i=>$result) {
            $result['value'] = '"'.$result['value'].'"';
            $array[$i] = $result;
        }
        return $array;
    }

    /**
     * Get the facet search term for an action
     *
     * @param string $action action to be translated
     *
     * @return string
     */
    protected function getCategory($action = null)
    {
        if ($action == null) {
            $action = $this->getCurrentAction();
        }
        switch(strToLower($action)) {
        case 'alphabetical':
            return $this->getCategory();
        case 'dewey':
            return 'dewey-hundreds';
        case 'lcc':
            return 'callnumber-first';
        case 'author':
            return 'authorStr';
        case 'topic':
            return 'topic_facet';
        case 'genre':
            return 'genre_facet';
        case 'region':
            return 'geographic_facet';
        case 'era':
            return 'era_facet';
        }
        return $action;
    }

    /**
     * Get a list of letters to display in alphabetical mode.
     *
     * @return array
     */
    protected function getAlphabetList()
    {
        // Get base alphabet:
        $chars = isset($this->config->Browse->alphabet_letters)
            ? $this->config->Browse->alphabet_letters
            : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Put numbers in the front for Era since years are important:
        if ($this->getCurrentAction() == 'Era') {
            $chars = '0123456789' . $chars;
        } else {
            $chars .= '0123456789';
        }

        // ALPHABET TO ['value','displayText']
        // (value has asterix appended for Solr, but is unmodified for tags)
        $suffix = $this->getCurrentAction() == 'Tag' ? '' : '*';
        $callback = function ($letter) use ($suffix) {
            return array('value' => $letter . $suffix, 'displayText' => $letter);
        };
        preg_match_all('/(.)/u', $chars, $matches);
        return array_map($callback, $matches[1]);
    }
}