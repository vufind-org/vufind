<?php
/**
 * Abstract options search model.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Base;
use VuFind\Translator\Translator, Zend\Session\Container as SessionContainer;

/**
 * Abstract options search model.
 *
 * This abstract class defines the option methods for modeling a search in VuFind.
 *
 * @category VuFind2
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class Options
{
    // Available sort options
    protected $sortOptions = array();
    protected $defaultSort = 'relevance';
    protected $defaultSortByHandler = array();
    protected $rssSort = null;

    // Search options for the user
    protected $defaultHandler = null;
    protected $advancedHandlers = array();
    protected $basicHandlers = array();
    protected $specialAdvancedFacets = '';
    protected $retainFiltersByDefault = true;

    // Available limit options
    protected $defaultLimit = 20;
    protected $limitOptions = array();

    // Available view options
    protected $defaultView = 'list';
    protected $viewOptions = array();

    // Facet settings
    protected $translatedFacets = array();

    // Spelling
    protected $spellcheck = true;

    // Shard settings
    protected $shards = array();
    protected $defaultSelectedShards = array();
    protected $visibleShardCheckboxes = false;

    // Highlighting
    protected $highlight = false;

    // Autocomplete setting
    protected $autocompleteEnabled = false;

    // Configuration files to read search settings from
    protected $searchIni = 'searches';
    protected $facetsIni = 'facets';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->limitOptions = array($this->defaultLimit);
    }

    /**
     * Get string listing special advanced facet types.
     *
     * @return string
     */
    public function getSpecialAdvancedFacets()
    {
        return $this->specialAdvancedFacets;
    }

    /**
     * Basic 'getter' for advanced search handlers.
     *
     * @return array
     */
    public function getAdvancedHandlers()
    {
        return $this->advancedHandlers;
    }

    /**
     * Basic 'getter' for basic search handlers.
     *
     * @return array
     */
    public function getBasicHandlers()
    {
        return $this->basicHandlers;
    }

    /**
     * Get default search handler.
     *
     * @return string
     */
    public function getDefaultHandler()
    {
        return $this->defaultHandler;
    }

    /**
     * Get default limit setting.
     *
     * @return int
     */
    public function getDefaultLimit()
    {
        return $this->defaultLimit;
    }

    /**
     * Get an array of limit options.
     *
     * @return array
     */
    public function getLimitOptions()
    {
        return $this->limitOptions;
    }

    /**
     * Get the name of the ini file used for configuring facet parameters in this
     * object.
     *
     * @return string
     */
    public function getFacetsIni()
    {
        return $this->facetsIni;
    }

    /**
     * Get the name of the ini file used for configuring search parameters in this
     * object.
     *
     * @return string
     */
    public function getSearchIni()
    {
        return $this->searchIni;
    }

    /**
     * Override the limit options.
     *
     * @param array $options New options to set.
     *
     * @return void
     */
    public function setLimitOptions($options)
    {
        if (is_array($options) && !empty($options)) {
            $this->limitOptions = $options;

            // If the current default limit is no longer legal, pick the
            // first option in the array as the new default:
            if (!in_array($this->defaultLimit, $this->limitOptions)) {
                $this->defaultLimit = $this->limitOptions[0];
            }
        }
    }

    /**
     * Get an array of sort options.
     *
     * @return array
     */
    public function getSortOptions()
    {
        return $this->sortOptions;
    }

    /**
     * Get the default sort option for the specified search handler.
     *
     * @param string $handler Search handler being used
     *
     * @return string
     */
    public function getDefaultSortByHandler($handler = null)
    {
        // Use default handler if none specified:
        if (empty($handler)) {
            $handler = $this->getDefaultHandler();
        }
        // Send back search-specific sort if available:
        if (isset($this->defaultSortByHandler[$handler])) {
            return $this->defaultSortByHandler[$handler];
        }
        // If no search-specific sort handler was found, use the overall default:
        return $this->defaultSort;
    }

    /**
     * Return the sorting value for RSS mode
     *
     * @param string $sort Sort setting to modify for RSS mode
     *
     * @return string
     */
    public function getRssSort($sort)
    {
        if (empty($this->rssSort)) {
            return $sort;
        }
        if ($sort == 'relevance') {
            return $this->rssSort;
        }
        return $this->rssSort . ',' . $sort;
    }

    /**
     * Get default view setting.
     *
     * @return int
     */
    public function getDefaultView()
    {
        return $this->defaultView;
    }

    /**
     * Get an array of view options.
     *
     * @return array
     */
    public function getViewOptions()
    {
        return $this->viewOptions;
    }

    /**
     * Get a list of facets that are subject to translation.
     *
     * @return array
     */
    public function getTranslatedFacets()
    {
        return $this->translatedFacets;
    }

    /**
     * Get current spellcheck setting and (optionally) change it.
     *
     * @param bool $bool True to enable, false to disable, null to leave alone
     *
     * @return bool
     */
    public function spellcheckEnabled($bool = null)
    {
        if (!is_null($bool)) {
            $this->spellcheck = $bool;
        }
        return $this->spellcheck;
    }

    /**
     * Is highlighting enabled?
     *
     * @return bool
     */
    public function highlightEnabled()
    {
        return $this->highlight;
    }

    /**
     * Translate a field name to a displayable string for rendering a query in
     * human-readable format:
     *
     * @param string $field Field name to display.
     *
     * @return string       Human-readable version of field name.
     */
    public function getHumanReadableFieldName($field)
    {
        if (isset($this->basicHandlers[$field])) {
            return Translator::translate($this->basicHandlers[$field]);
        } else if (isset($this->advancedHandlers[$field])) {
            return Translator::translate($this->advancedHandlers[$field]);
        } else {
            return $field;
        }
    }

    /**
     * Turn off highlighting.
     *
     * @return void
     */
    public function disableHighlighting()
    {
        $this->highlight = false;
    }

    /**
     * Is autocomplete enabled?
     *
     * @return bool
     */
    public function autocompleteEnabled()
    {
        return $this->autocompleteEnabled;
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    abstract public function getSearchAction();

    /**
     * Return the route name for the search home action.
     *
     * @return string
     */
    public function getSearchHomeAction()
    {
        // Assume the home action is the same as the search action, only with
        // a "-home" suffix in place of the search action.
        $basicSearch = $this->getSearchAction();
        return substr($basicSearch, 0, strpos($basicSearch, '-')) . '-home';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        // Assume unsupported by default:
        return false;
    }

    /**
     * Get a session namespace specific to the current class.
     *
     * @return SessionContainer
     */
    public function getSession()
    {
        static $session = false;
        if (!$session) {
            $session = new SessionContainer(get_class($this));
        }
        return $session;
    }

    /**
     * Remember the last sort option used.
     *
     * @param string $last Option to remember.
     *
     * @return void
     */
    public function rememberLastSort($last)
    {
        $this->getSession()->lastSort = $last;
    }

    /**
     * Retrieve the last sort option used.
     *
     * @return string
     */
    public function getLastSort()
    {
        $session = $this->getSession();
        return isset($session->lastSort) ? $session->lastSort : null;
    }

    /**
     * Remember the last limit option used.
     *
     * @param string $last Option to remember.
     *
     * @return void
     */
    public function rememberLastLimit($last)
    {
        $this->getSession()->lastLimit = $last;
    }

    /**
     * Retrieve the last limit option used.
     *
     * @return string
     */
    public function getLastLimit()
    {
        $session = $this->getSession();
        return isset($session->lastLimit) ? $session->lastLimit : null;
    }

    /**
     * Remember the last view option used.
     *
     * @param string $last Option to remember.
     *
     * @return void
     */
    public function rememberLastView($last)
    {
        $this->getSession()->lastView = $last;
    }

    /**
     * Retrieve the last view option used.
     *
     * @return string
     */
    public function getLastView()
    {
        $session = $this->getSession();
        return isset($session->lastView) ? $session->lastView : null;
    }

    /**
     * Should filter settings be retained across searches by default?
     *
     * @return bool
     */
    public function getRetainFilterSetting()
    {
        return $this->retainFiltersByDefault;
    }

    /**
     * Get an associative array of available shards (key = internal VuFind ID for
     * this shard; value = details needed to connect to shard; empty for non-sharded
     * data sources).
     *
     * Although this mechanism was originally designed for Solr's sharding
     * capabilities, it could also be useful for multi-database search situations
     * (i.e. federated search, EBSCO's API, etc., etc.).
     *
     * @return array
     */
    public function getShards()
    {
        return $this->shards;
    }

    /**
     * Get an array of default selected shards (values correspond with keys returned
     * by getShards().
     *
     * @return array
     */
    public function getDefaultSelectedShards()
    {
        return $this->defaultSelectedShards;
    }

    /**
     * Should we display shard checkboxes for this object?
     *
     * @return bool
     */
    public function showShardCheckboxes()
    {
        return $this->visibleShardCheckboxes;
    }

    /**
     * If there is a limit to how many search results a user can access, this
     * method will return that limit.  If there is no limit, this will return
     * -1.
     *
     * @return int
     */
    public function getVisibleSearchResultLimit()
    {
        // No limit by default:
        return -1;
    }
}