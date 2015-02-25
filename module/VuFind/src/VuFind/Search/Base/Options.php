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
use VuFind\I18n\Translator\TranslatorAwareInterface,
    Zend\Session\Container as SessionContainer;

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
abstract class Options implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Available sort options
     *
     * @var array
     */
    protected $sortOptions = [];

    /**
     * Overall default sort option
     *
     * @var string
     */
    protected $defaultSort = 'relevance';

    /**
     * Handler-specific defaults
     *
     * @var array
     */
    protected $defaultSortByHandler = [];

    /**
     * RSS-specific sort option
     *
     * @var string
     */
    protected $rssSort = null;

    /**
     * Default search handler
     *
     * @var string
     */
    protected $defaultHandler = null;

    /**
     * Advanced search handlers
     *
     * @var array
     */
    protected $advancedHandlers = [];

    /**
     * Basic search handlers
     *
     * @var array
     */
    protected $basicHandlers = [];

    /**
     * Special advanced facet settings
     *
     * @var string
     */
    protected $specialAdvancedFacets = '';

    /**
     * Should we retain filters by default?
     *
     * @var bool
     */
    protected $retainFiltersByDefault = true;

    /**
     * Default filters to apply to new searches
     *
     * @var array
     */
    protected $defaultFilters = [];

    /**
     * Default limit option
     *
     * @var int
     */
    protected $defaultLimit = 20;

    /**
     * Available limit options
     *
     * @var array
     */
    protected $limitOptions = [];

    /**
     * Default view option
     *
     * @var string
     */
    protected $defaultView = 'list';

    /**
     * Available view options
     *
     * @var array
     */
    protected $viewOptions = [];

    /**
     * Facet settings
     *
     * @var array
     */
    protected $translatedFacets = [];

    /**
     * Spelling setting
     *
     * @var bool
     */
    protected $spellcheck = true;

    /**
     * Available shards
     *
     * @var array
     */
    protected $shards = [];

    /**
     * Default selected shards
     *
     * @var array
     */
    protected $defaultSelectedShards = [];

    /**
     * Should we present shard checkboxes to the user?
     *
     * @var bool
     */
    protected $visibleShardCheckboxes = false;

    /**
     * Highlighting setting
     *
     * @var bool
     */
    protected $highlight = false;

    /**
     * Autocomplete setting
     *
     * @var bool
     */
    protected $autocompleteEnabled = false;

    /**
     * Configuration file to read search settings from
     *
     * @var string
     */
    protected $searchIni = 'searches';

    /**
     * Configuration file to read facet settings from
     *
     * @var string
     */
    protected $facetsIni = 'facets';

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->limitOptions = [$this->defaultLimit];
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
     * Given a label from the configuration file, return the name of the matching
     * handler (basic checked first, then advanced); return the default handler
     * if no match is found.
     *
     * @param string $label Label to search for
     *
     * @return string
     */
    public function getHandlerForLabel($label)
    {
        $label = empty($label) ? false : $this->translate($label);

        foreach ($this->getBasicHandlers() as $id => $currentLabel) {
            if ($this->translate($currentLabel) == $label) {
                return $id;
            }
        }
        foreach ($this->getAdvancedHandlers() as $id => $currentLabel) {
            if ($this->translate($currentLabel) == $label) {
                return $id;
            }
        }
        return $this->getDefaultHandler();
    }

    /**
     * Given a basic handler name, return the corresponding label (or false
     * if none found):
     *
     * @param string $handler Handler name to look up.
     *
     * @return string
     */
    public function getLabelForBasicHandler($handler)
    {
        return isset($this->basicHandlers[$handler])
            ? $this->basicHandlers[$handler] : false;
    }

    /**
     * Get default search handler.
     *
     * @return string
     */
    public function getDefaultHandler()
    {
        if (!empty($this->defaultHandler)) {
            return $this->defaultHandler;
        }
        return current(array_keys($this->getBasicHandlers()));
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
            return $this->translate($this->basicHandlers[$field]);
        } else if (isset($this->advancedHandlers[$field])) {
            return $this->translate($this->advancedHandlers[$field]);
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
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart()
    {
        // Assume true by default.
        return true;
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
        $session = $this->getSession();
        if (!$session->getManager()->getStorage()->isImmutable()) {
            $session->lastSort = $last;
        }
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
        $session = $this->getSession();
        if (!$session->getManager()->getStorage()->isImmutable()) {
            $session->lastLimit = $last;
        }
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
        $session = $this->getSession();
        if (!$session->getManager()->getStorage()->isImmutable()) {
            $session->lastView = $last;
        }
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
     * Get default filters to apply to an empty search.
     *
     * @return array
     */
    public function getDefaultFilters()
    {
        return $this->defaultFilters;
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

    /**
     * Sleep magic method -- the translator can't be serialized, so we need to
     * exclude it from serialization.  Since we can't obtain a new one in the
     * __wakeup() method, it needs to be re-injected from outside.
     *
     * @return array
     */
    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['translator']);
        $vars = array_keys($vars);
        return $vars;
    }
}