<?php
/**
 * Abstract options search model.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Base;

use Laminas\Config\Config;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Abstract options search model.
 *
 * This abstract class defines the option methods for modeling a search in VuFind.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
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
     * Available sort options for facets
     *
     * @var array
     */
    protected $facetSortOptions = [];

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
     * Default delimiter used for delimited facets
     *
     * @var string
     */
    protected $defaultFacetDelimiter;

    /**
     * Facet settings
     *
     * @var array
     */
    protected $delimitedFacets = [];

    /**
     * Convenient field => delimiter lookup array derived from $delimitedFacets.
     *
     * @var array
     */
    protected $processedDelimitedFacets = null;

    /**
     * Facet settings
     *
     * @var array
     */
    protected $translatedFacets = [];

    /**
     * Text domains for translated facets
     *
     * @var array
     */
    protected $translatedFacetsTextDomains = [];

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
     * Autocomplete auto submit setting
     *
     * @var bool
     */
    protected $autocompleteAutoSubmit = true;

    /**
     * Configuration file to read global settings from
     *
     * @var string
     */
    protected $mainIni = 'config';

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
     * Active list view option (see [List] in searches.ini).
     *
     * @var string
     */
    protected $listviewOption = "full";

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Maximum number of results (no limit by default)
     *
     * @var int
     */
    protected $resultLimit = -1;

    /**
     * Is the first/last navigation scroller enabled?
     *
     * @var bool
     */
    protected $firstlastNavigation = false;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->limitOptions = [$this->defaultLimit];
        $this->setConfigLoader($configLoader);

        $id = $this->getSearchClassId();
        $facetSettings = $configLoader->get($this->facetsIni);
        if (isset($facetSettings->AvailableFacetSortOptions[$id])) {
            foreach ($facetSettings->AvailableFacetSortOptions[$id]->toArray()
                     as $facet => $sortOptions
            ) {
                $this->facetSortOptions[$facet] = [];
                foreach (explode(',', $sortOptions) as $fieldAndLabel) {
                    [$field, $label] = explode('=', $fieldAndLabel);
                    $this->facetSortOptions[$facet][$field] = $label;
                }
            }
        }
    }

    /**
     * Set the config loader
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     *
     * @return void
     */
    public function setConfigLoader(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
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
        return $this->basicHandlers[$handler] ?? false;
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
     * Get the name of the ini file used for loading primary settings in this
     * object.
     *
     * @return string
     */
    public function getMainIni()
    {
        return $this->mainIni;
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
     * Get an array of sort options for a facet.
     *
     * @param string $facet Facet
     *
     * @return array
     */
    public function getFacetSortOptions($facet = '*')
    {
        return $this->facetSortOptions[$facet] ?? $this->facetSortOptions['*'] ?? [];
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
     * Returns the defaultFacetDelimiter value.
     *
     * @return string
     */
    public function getDefaultFacetDelimiter()
    {
        return $this->defaultFacetDelimiter;
    }

    /**
     * Set the defaultFacetDelimiter value.
     *
     * @param string $defaultFacetDelimiter A default delimiter to be used with
     * delimited facets
     *
     * @return void
     */
    public function setDefaultFacetDelimiter($defaultFacetDelimiter)
    {
        $this->defaultFacetDelimiter = $defaultFacetDelimiter;
        $this->processedDelimitedFacets = null; // clear processed value cache
    }

    /**
     * Get a list of delimited facets
     *
     * @param bool $processed False = return raw values; true = process values into
     * field => delimiter associative array.
     *
     * @return array
     */
    public function getDelimitedFacets($processed = false)
    {
        if (!$processed) {
            return $this->delimitedFacets;
        }
        if (null === $this->processedDelimitedFacets) {
            $this->processedDelimitedFacets = [];
            $defaultDelimiter = $this->getDefaultFacetDelimiter();
            foreach ($this->delimitedFacets as $current) {
                $parts = explode('|', $current, 2);
                if (count($parts) == 2) {
                    $this->processedDelimitedFacets[$parts[0]] = $parts[1];
                } else {
                    $this->processedDelimitedFacets[$parts[0]] = $defaultDelimiter;
                }
            }
        }
        return $this->processedDelimitedFacets;
    }

    /**
     * Set the delimitedFacets value.
     *
     * @param array $delimitedFacets An array of delimited facet names
     *
     * @return void
     */
    public function setDelimitedFacets($delimitedFacets)
    {
        $this->delimitedFacets = $delimitedFacets;
        $this->processedDelimitedFacets = null; // clear processed value cache
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
     * Configure facet translation using an array of field names with optional
     * colon-separated text domains.
     *
     * @param array $facets Incoming configuration.
     *
     * @return void
     */
    public function setTranslatedFacets($facets)
    {
        // Reset properties:
        $this->translatedFacets = $this->translatedFacetsTextDomains = [];

        // Fill in new data:
        foreach ($facets as $current) {
            $parts = explode(':', $current);
            $this->translatedFacets[] = $parts[0];
            if (isset($parts[1])) {
                $this->translatedFacetsTextDomains[$parts[0]] = $parts[1];
            }
        }
    }

    /**
     * Look up the text domain for use when translating a particular facet
     * field.
     *
     * @param string $field Field name being translated
     *
     * @return string
     */
    public function getTextDomainForTranslatedFacet($field)
    {
        return $this->translatedFacetsTextDomains[$field] ?? 'default';
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
        if (null !== $bool) {
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
        } elseif (isset($this->advancedHandlers[$field])) {
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
     * Should autocomplete auto submit?
     *
     * @return bool
     */
    public function autocompleteAutoSubmit()
    {
        return $this->autocompleteAutoSubmit;
    }

    /**
     * Get a string of the listviewOption (full or tab).
     *
     * @return string
     */
    public function getListViewOption()
    {
        return $this->listviewOption;
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
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return false;
    }

    /**
     * Return the route name for the versions search action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getVersionsAction()
    {
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
        return intval($this->resultLimit);
    }

    /**
     * Load all API-related settings from the relevant ini file(s).
     *
     * @return array
     */
    public function getAPISettings()
    {
        // Inherit defaults from searches.ini (if that is not already the
        // configured search settings file):
        $defaultConfig = $this->configLoader->get('searches')->API;
        $defaultSettings = $defaultConfig ? $defaultConfig->toArray() : [];
        $localIni = $this->getSearchIni();
        $localConfig = ($localIni !== 'searches')
            ? $this->configLoader->get($localIni)->API : null;
        $localSettings = $localConfig ? $localConfig->toArray() : [];
        return array_merge($defaultSettings, $localSettings);
    }

    /**
     * Load all recommendation settings from the relevant ini file.  Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @param string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     */
    public function getRecommendationSettings($handler = null)
    {
        // Load the necessary settings to determine the appropriate recommendations
        // module:
        $searchSettings = $this->configLoader->get($this->getSearchIni());

        // Load a type-specific recommendations setting if possible, or the default
        // otherwise:
        $recommend = [];

        if (null !== $handler
            && isset($searchSettings->TopRecommendations->$handler)
        ) {
            $recommend['top'] = $searchSettings->TopRecommendations
                ->$handler->toArray();
        } else {
            $recommend['top']
                = isset($searchSettings->General->default_top_recommend)
                ? $searchSettings->General->default_top_recommend->toArray()
                : false;
        }
        if (null !== $handler
            && isset($searchSettings->SideRecommendations->$handler)
        ) {
            $recommend['side'] = $searchSettings->SideRecommendations
                ->$handler->toArray();
        } else {
            $recommend['side']
                = isset($searchSettings->General->default_side_recommend)
                ? $searchSettings->General->default_side_recommend->toArray()
                : false;
        }
        if (null !== $handler
            && isset($searchSettings->NoResultsRecommendations->$handler)
        ) {
            $recommend['noresults'] = $searchSettings->NoResultsRecommendations
                ->$handler->toArray();
        } else {
            $recommend['noresults']
                = isset($searchSettings->General->default_noresults_recommend)
                ? $searchSettings->General->default_noresults_recommend
                    ->toArray()
                : false;
        }

        return $recommend;
    }

    /**
     * Get the identifier used for naming the various search classes in this family.
     *
     * @return string
     */
    public function getSearchClassId()
    {
        // Parse identifier out of class name of format VuFind\Search\[id]\Options:
        $className = get_class($this);
        $class = explode('\\', $className);

        // Special case: if there's an unexpected number of parts, we may be testing
        // with a mock object; if so, that's okay, but anything else is unexpected.
        if (count($class) !== 4) {
            if ('Mock_' === substr($className, 0, 5)) {
                return 'Mock';
            }
            throw new \Exception("Unexpected class name: {$className}");
        }

        return $class[2];
    }

    /**
     * Should we include first/last options in result scroller navigation?
     *
     * @return bool
     */
    public function supportsFirstLastNavigation()
    {
        return $this->firstlastNavigation;
    }

    /**
     * Does this search backend support scheduled searching?
     *
     * @return bool
     */
    public function supportsScheduledSearch()
    {
        // Unsupported by default!
        return false;
    }

    /**
     * Return the callback used for normalization within this backend.
     *
     * @return callable
     */
    public function getSpellingNormalizer()
    {
        return new \VuFind\Normalizer\DefaultSpellingNormalizer();
    }

    /**
     * Configure autocomplete preferences from an .ini file.
     *
     * @param Config $searchSettings Object representation of .ini file
     *
     * @return void
     */
    protected function configureAutocomplete(Config $searchSettings = null)
    {
        // Only change settings from current values if they are defined in .ini:
        $this->autocompleteEnabled = $searchSettings->Autocomplete->enabled
            ?? $this->autocompleteEnabled;
        $this->autocompleteAutoSubmit = $searchSettings->Autocomplete->auto_submit
            ?? $this->autocompleteAutoSubmit;
    }

    /**
     * Get advanced search limits that override the natural sorting to
     * display at the top.
     *
     * @param string $limit advanced search limit
     *
     * @return array
     */
    public function limitOrderOverride($limit)
    {
        $facetSettings = $this->configLoader->get($this->getFacetsIni());
        $limits = $facetSettings->Advanced_Settings->limitOrderOverride ?? null;
        $delimiter = $facetSettings->Advanced_Settings->limitDelimiter ?? '::';
        $limitConf = $limits ? $limits->get($limit) : '';
        return array_map('trim', explode($delimiter, $limitConf));
    }
}
