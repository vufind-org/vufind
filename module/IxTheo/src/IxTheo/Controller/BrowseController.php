<?php

namespace IxTheo\Controller;

class BrowseController extends \VuFind\Controller\BrowseController
{
    /**
     * Browse Author
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function authorAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'lcc' => 'By Call Number',
            'topic' => 'By Topic',
            'genre' => 'By Genre',
            'region' => 'By Region',
            'era' => 'By Era',
            'publisher' => 'By Publisher',
            'ixtheo-classification' => 'By IxTheo-Classification',
            'relbib-classification' => 'By RelBib-Classification',
        ];

        return $this->performBrowse('Author', $categoryList, true);
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
        $browseOptions = [];

        // First option: tags -- is it enabled in config.ini?  If no setting is
        // found, assume it is active. Note that this setting is disabled if tags
        // are universally turned off.
        if ((!isset($this->config->Browse->tag) || $this->config->Browse->tag)
            && $this->tagsEnabled()
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
        $remainingOptions = [
            'IxTheo-Classification', 'RelBib-Classification', 'Topic', 'Author', 'Publisher'
        ];
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
     * Browse Era
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function eraAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic' => 'By Topic',
            'genre' => 'By Genre',
            'region' => 'By Region'
        ];

        return $this->performBrowse('Era', $categoryList, true);
    }

    /**
     * Browse Genre
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function genreAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic' => 'By Topic',
            'region' => 'By Region',
            'era' => 'By Era',
            'publisher' => 'By Publisher',
            'ixtheo-classification' => 'By IxTheo-Classification',
            'relbib-classification' => 'By RelBib-Classification',
        ];

        return $this->performBrowse('Genre', $categoryList, true);
    }

    /**
     * Get a list of letters to display in alphabetical mode.
     *
     * @return array
     */
    protected function getAlphabetList()
    {
        // ALPHABET TO ['value','displayText']
        // (value has asterix appended for Solr, but is unmodified for tags)
        $suffix = $this->getCurrentAction() == 'Tag' ? '' : '*';
        $callback = function ($letter) use ($suffix) {
            return ['value' => $letter . $suffix, 'displayText' => $letter];
        };

        $ixtheo_notation_callback = function ($letter) use ($suffix) {
            return ['value' => $letter . $suffix, 'displayText' => $this->translate('ixtheo-' . $letter)];
        };

        $relbib_notation_callback = function ($letter) use ($suffix) {
            return ['value' => $letter . $suffix, 'displayText' => $this->translate('relbib-' . $letter)];
        };

        // Get base alphabet:
        $chars = isset($this->config->Browse->alphabet_letters)
            ? $this->config->Browse->alphabet_letters
            : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Put numbers in the front for Era since years are important:
        if ($this->getCurrentAction() === 'IxTheo-Classification') {
            $chars = 'ABCFHKNRSTVXZ';
            $callback = $ixtheo_notation_callback;
        } else if ($this->getCurrentAction() === 'RelBib-Classification') {
            $chars = 'ABHKNTVXZ';
            $callback = $relbib_notation_callback;
        }  else if ($this->getCurrentAction() == 'Era') {
            $chars = '0123456789' . $chars;
        } else {
            $chars .= '0123456789';
        }

        preg_match_all('/(.)/u', $chars, $matches);
        return array_map($callback, $matches[1]);
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
        case 'ixtheo-classification':
            return 'ixtheo_notation_facet';
        case 'publisher':
            return 'publisher_facet';
        case 'relbib-classification':
            return 'relbib_notation_facet';
        default:
            return parent::getCategory($action);
        }
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
        case 'author':
            return ['author_facet', $this->quoteValues(
                        $this->getFacetList('author_facet')
                    )
                ];
        case 'ixtheo-classification':
            return [
                'ixtheo_notation_facet', $this->quoteValues(
                    $this->getFacetList('ixtheo_notation_facet', $category)
                )
            ];
        case 'publisher':
            return [
                'publisher_facet', $this->quoteValues(
                    $this->getFacetList('publisher_facet', $category)
                )
            ];
        case 'relbib-classification':
            return [
                'relbib_notation_facet', $this->quoteValues(
                    $this->getFacetList('relbib_notation_facet', $category)
                )
            ];
        default:
            return parent::getSecondaryList($facet);
        }
    }

    /**
     * Browse ixTheo notations
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function ixTheoClassificationAction()
    {
        $categoryList = [
            'alphabetical' => 'By Categories',
            'lcc' => 'By Call Number',
            'topic' => 'By Topic',
            'genre' => 'By Genre',
            'region' => 'By Region',
            'era' => 'By Era',
            'publisher' => 'By Publisher',
        ];

        return $this->performBrowse('IxTheo-Classification', $categoryList, true);
    }

    /**
     * Browse Publisher
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function publisherAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'lcc' => 'By Call Number',
            'topic' => 'By Topic',
            'genre' => 'By Genre',
            'region' => 'By Region',
            'era' => 'By Era',
            'ixtheo-classification' => 'By IxTheo-Classification',
            'relbib-classification' => 'By RelBib-Classification',
        ];

        return $this->performBrowse('Publisher', $categoryList, true);
    }

    /**
     * Browse Region
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function regionAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic' => 'By Topic',
            'genre' => 'By Genre',
            'era' => 'By Era',
            'publisher' => 'By Publisher',
            'ixtheo-classification' => 'By IxTheo-Classification',
            'relbib-classification' => 'By RelBib-Classification'
        ];

        return $this->performBrowse('Region', $categoryList, true);
    }

    public function relBibClassificationAction()
    {
        $categoryList = [
            'alphabetical' => 'By Categories',
        ];

        return $this->performBrowse('RelBib-Classification', $categoryList, true);
    }

    /**
     * Browse Topic
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function topicAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'genre' => 'By Genre',
            'region' => 'By Region',
            'era' => 'By Era',
            'author' => 'By Author',
            'publisher' => 'By Publisher',
            'ixtheo-classification' => 'By IxTheo-Classification',
            'relbib-classification' => 'By RelBib-Classification',
        ];

        return $this->performBrowse('Topic', $categoryList, true);
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
    protected function getFacetList($facet, $category = null, $sort = 'count', $query = '[* TO *]')
    {
        $results = $this->serviceLocator->get('VuFind\SearchResultsPluginManager')->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet);
        if ($category != null) {
            $query = $category . ':' . $query;
        } else {
            $query = $facet . ':' . $query;
        }
        $params->setOverrideQuery('{!multiLanguageQueryParser}' . $query);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        // Get limit from config
        $params->setFacetLimit($this->config->Browse->result_limit);
        $params->setLimit(0);
        // Facet prefix
        if ($this->params()->fromQuery('facet_prefix')) {
            $params->setFacetPrefix($this->params()->fromQuery('facet_prefix'));
        }
        $params->setFacetSort($sort);
        $result = $results->getFacetList();
        if (isset($result[$facet])) {
            // Sort facets alphabetically if configured to do so:
            if (isset($this->config->Browse->alphabetical_order)
                && $this->config->Browse->alphabetical_order
            ) {
                $callback = function ($a, $b) {
                    return strcoll($a['displayText'], $b['displayText']);
                };
                usort($result[$facet]['list'], $callback);
            }
            return $result[$facet]['list'];
        } else
            return [];
    }
}
