<?php
/**
 * EDS API Options
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\EDS;

/**
 * EDS API Options
 *
 * @category VuFind2
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Available search mode options
     *
     * @var array
     */
    protected $modeOptions = [];

    /**
     * Default search mode options
     * @var string
     */
    protected $defaultMode = 'all';

    /**
     * The set search mode
     * @var string
     */
    protected $searchMode;

    /**
     * Default expanders to apply
     * @var array
     */
    protected $defaultExpanders = [];

    /**
     * Available expander options
     * @var unknown
     */
    protected $expanderOptions = [];

    /**
     * Available limiter options
     * @var unknown
    */
    protected $limiterOptions = [];

    /**
     * Wheither or not to return available facets with the search response
     * @var unknown
     */
    protected $includeFacets = 'y';

    /**
     * Available Search Options from the API
     * @var array
     */
    protected $apiInfo;

    /**
     * Limiters to display on the basic search screen
     *
     * @var array
     */
    protected $commonLimiters = [];

    /**
     * Expanders to display on the basic search screen
     *
     * @var array
     */
    protected $commonExpanders = [];

    /**
     * Pre-assigned filters
     *
     * @var array
     */
    protected $hiddenFilters = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param array                        $apiInfo      API information
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader,
        $apiInfo = null
    ) {
        $this->searchIni = $this->facetsIni = 'EDS';
        $searchSettings = $configLoader->get($this->searchIni);
        parent::__construct($configLoader);
        $this->resultLimit = 100;
        $this->viewOptions = [
            'list|title' => 'Title View', 'list|brief' => 'Brief View',
            'list|detailed' => 'Detailed View'
        ];
        $this->apiInfo = $apiInfo;
        $this->setOptionsFromApi($searchSettings);
        $this->setOptionsFromConfig($searchSettings);
        $facetConf = $configLoader->get($this->facetsIni);
        if (isset($facetConf->Advanced_Facet_Settings->translated_facets)
            && count($facetConf->Advanced_Facet_Settings->translated_facets) > 0
        ) {
            $this->setTranslatedFacets(
                $facetConf->Advanced_Facet_Settings->translated_facets->toArray()
            );
        }
    }

    /**
     * Get an array of search mode options
     *
     * @return array
     */
    public function getModeOptions()
    {
        return $this->modeOptions;
    }

    /**
     * Get the default search mode
     *
     * @return string
     */
    public function getDefaultMode()
    {
        return $this->defaultMode;
    }

    /**
     * Obtain the set searchmode
     *
     * @return string the search mode
     */
    public function getSearchMode()
    {
        return $this->searchMode;
    }

    /**
     * Set the search mode
     *
     * @param string $mode Mode
     *
     * @return void
     */
    public function setSearchMode($mode)
    {
        $this->searchMode = $mode;
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'eds-search';
    }

    /**
     * Return the view associated with this configuration
     *
     * @return string
     */
    public function getView()
    {
        return $this->defaultView;
    }

    /**
     * Return the view associated with this configuration
     *
     * @return string
     */
    public function getEdsView()
    {
        $viewArr = explode('|', $this->defaultView);
        return (1 < count($viewArr)) ? $viewArr[1] : $this->defaultView;
    }

    /**
     * Return the expander ids that have the default on flag set in admin
     *
     * @return array
     */
    public function getDefaultExpanders()
    {
        return $this->defaultExpanders;
    }
    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'eds-advanced';
    }

    /**
     * Set the search options from the Eds API Info methods results
     *
     * @return void
     */
    public function setOptionsFromApi()
    {
        // Set options from the INFO method first. If settings are set in the config
        // file, use them as 'overrides', but only if they are available (ie. are
        // returned in the INFO method)
        $this->populateViewSettings();
        $this->populateSearchCriteria();
    }

    /**
     * Apply user settings. All legal values have already been loaded from the API
     * at the time this method is called, so we just need to check if the
     * user-supplied values are valid, and if so, filter/reorder accordingly.
     *
     * @param \Zend\Config\Config $searchSettings Configuration
     * @param string              $section        Configuration section to read
     * @param string              $property       Property of this object to read
     * and/or modify.
     *
     * @return void
     */
    protected function filterAndReorderProperty($searchSettings, $section, $property)
    {
        if (!isset($searchSettings->$section)) {
            return;
        }

        // Use a reference to access $this->$property to avoid the awkward/ambiguous
        // expression $this->$property[$key] below.
        $propertyRef = & $this->$property;

        $newPropertyValues = [];
        foreach ($searchSettings->$section as $key => $value) {
            if (isset($propertyRef[$key])) {
                $newPropertyValues[$key] = $value;
            }
        }
        if (!empty($newPropertyValues)) {
            $this->$property = $newPropertyValues;
        }
    }

    /**
     * Apply user-requested "common" settings.
     *
     * @param \Zend\Config\Config $searchSettings Configuration
     * @param string              $setting        Name of common setting
     * @param string              $list           Name of property containing valid
     * values
     * @param string              $target         Name of property to populate
     *
     * @return void
     */
    protected function setCommonSettings($searchSettings, $setting, $list, $target)
    {
        if (isset($searchSettings->General->$setting)) {
            $userValues = explode(',', $searchSettings->General->$setting);

            if (!empty($userValues) && isset($this->$list) && !empty($this->$list)) {
                // Reference to property containing API-provided whitelist of values
                $listRef = & $this->$list;
                // Reference to property containing final common settings
                $targetRef = & $this->$target;
                foreach ($userValues as $current) {
                    // Only add values that are valid according to the API's list
                    if (isset($listRef[$current])) {
                        $targetRef[] = $current;
                    }
                }
            }
        }
    }

    /**
     * Load options from the configuration file. These will override the defaults set
     * from the values in the Info method. (If the values set in the config files in
     * not a 'valid' EDS API value, it will be ignored.
     *
     * @param \Zend\Config\Config $searchSettings Configuration
     *
     * @return void
     */
    protected function setOptionsFromConfig($searchSettings)
    {
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions
                = explode(",", $searchSettings->General->limit_options);
        }

        // Set up highlighting preference
        if (isset($searchSettings->General->highlighting)) {
            $this->highlight = $searchSettings->General->highlighting;
        }

        // Set up facet preferences
        if (isset($searchSettings->General->include_facets)) {
            $this->includeFacets = $searchSettings->General->include_facets;
        }

        // Load search preferences:
        if (isset($searchSettings->General->retain_filters_by_default)) {
            $this->retainFiltersByDefault
                = $searchSettings->General->retain_filters_by_default;
        }

        // Search handler setup. Only valid values set in the config files are used.
        $this->filterAndReorderProperty(
            $searchSettings, 'Basic_Searches', 'basicHandlers'
        );
        $this->filterAndReorderProperty(
            $searchSettings, 'Advanced_Searches', 'advancedHandlers'
        );

        // Sort preferences:
        $this->filterAndReorderProperty($searchSettings, 'Sorting', 'sortOptions');

        if (isset($searchSettings->General->default_sort)
            && isset($this->sortOptions[$searchSettings->General->default_sort])
        ) {
            $this->defaultSort = $searchSettings->General->default_sort;
        }

        if (isset($searchSettings->General->default_amount)
            && isset($this->amountOptions[$searchSettings->General->default_amount])
        ) {
            $this->defaultAmount = $searchSettings->General->default_amount;
        }

        if (isset($searchSettings->General->default_mode)
            && isset($this->modeOptions[$searchSettings->General->default_mode])
        ) {
            $this->defaultMode = $searchSettings->General->default_mode;
        }

        //View preferences
        if (isset($searchSettings->General->default_view)) {
            $this->defaultView = 'list|' . $searchSettings->General->default_view;
        }

        if (isset($searchSettings->Advanced_Facet_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $searchSettings->Advanced_Facet_Settings->special_facets;
        }

        // Set common limiters and expanders.
        // Only the values that are valid for this profile will be used.
        $this->setCommonSettings(
            $searchSettings, 'common_limiters', 'limiterOptions', 'commonLimiters'
        );
        $this->setCommonSettings(
            $searchSettings, 'common_expanders', 'expanderOptions', 'commonExpanders'
        );
    }

    /**
     * Map EBSCO sort labels to standard VuFind text.
     *
     * @param string $label Label to transform
     *
     * @return string
     */
    protected function mapSortLabel($label)
    {
        switch ($label) {
        case 'Date Newest':
            return 'sort_year';
        case 'Date Oldest':
            return 'sort_year asc';
        default:
            return 'sort_' . strtolower($label);
        }
    }

    /**
     * Populate available search criteria from the EDS API Info method
     *
     * @return void
     */
    protected function populateSearchCriteria()
    {
        if (isset($this->apiInfo)
            && isset($this->apiInfo['AvailableSearchCriteria'])
        ) {
            // Reference for readability:
            $availCriteria = & $this->apiInfo['AvailableSearchCriteria'];

            //Sort preferences
            $this->sortOptions = [];
            if (isset($availCriteria['AvailableSorts'])) {
                foreach ($availCriteria['AvailableSorts'] as $sort) {
                    $this->sortOptions[$sort['Id']]
                        = $this->mapSortLabel($sort['Label']);
                }
            }

            // By default, use all of the available search fields for both
            // advanced and basic. Use the values in the config files to filter.
            $this->basicHandlers = ['AllFields' => 'All Fields'];
            if (isset($availCriteria['AvailableSearchFields'])) {
                foreach ($availCriteria['AvailableSearchFields'] as $searchField) {
                    $this->basicHandlers[$searchField['FieldCode']]
                        = $searchField['Label'];
                }
            }
            $this->advancedHandlers = $this->basicHandlers;

            // Search Mode preferences
            $this->modeOptions = [];
            if (isset($availCriteria['AvailableSearchModes'])) {
                foreach ($availCriteria['AvailableSearchModes'] as $mode) {
                    $this->modeOptions[$mode['Mode']] = [
                        'Label' => $mode['Label'], 'Value' => $mode['Mode']
                    ];
                    if (isset($mode['DefaultOn'])
                        &&  'y' == $mode['DefaultOn']
                    ) {
                        $this->defaultMode = $mode['Mode'];
                    }
                }
            }

            //expanders
            $this->expanderOptions = [];
            $this->defaultExpanders = [];
            if (isset($availCriteria['AvailableExpanders'])) {
                foreach ($availCriteria['AvailableExpanders'] as $expander) {
                    $this->expanderOptions[$expander['Id']] = [
                        'Label' => $expander['Label'], 'Value' => $expander['Id']
                    ];
                    if (isset($expander['DefaultOn'])
                        && 'y' == $expander['DefaultOn']
                    ) {
                        $this->defaultExpanders[] =  $expander['Id'];
                    }
                }
            }

            //Limiters
            $this->limiterOptions = [];
            if (isset($availCriteria['AvailableLimiters'])) {
                foreach ($availCriteria['AvailableLimiters'] as $limiter) {
                    $val = '';
                    if ('select' == $limiter['Type']) {
                        $val = 'y';
                    }
                    $this->limiterOptions[$limiter['Id']] = [
                        'Id' => $limiter['Id'],
                        'Label' => $limiter['Label'],
                        'Type' => $limiter['Type'],
                        'LimiterValues' => isset($limiter['LimiterValues'])
                            ? $this->populateLimiterValues(
                                $limiter['LimiterValues']
                            )
                            : [['Value' => $val]],
                        'DefaultOn' => isset($limiter['DefaultOn'])
                            ? $limiter['DefaultOn'] : 'n',
                    ];

                }

            }
        }
    }

    /**
     * Populate limiter values forom the EDS API INFO method data
     *
     * @param array $limiterValues Limiter values from the API
     *
     * @return array
     */
    protected function populateLimiterValues($limiterValues)
    {
        $availableLimiterValues = [];
        if (isset($limiterValues)) {
            foreach ($limiterValues as $limiterValue) {
                $availableLimiterValues[] = [
                    'Value' => $limiterValue['Value'],
                    'LimiterValues' => isset($limiterValue['LimiterValues'])
                        ? $this
                            ->populateLimiterValues($limiterValue['LimiterValues'])
                        : null
                ];
            }
        }
        return empty($availableLimiterValues) ? null : $availableLimiterValues;
    }

    /**
     * Returns the available limters
     *
     * @return array
     */
    public function getAvailableLimiters()
    {
        return $this->limiterOptions;
    }

    /**
     * Returns the available expanders
     *
     * @return array
     */
    public function getAvailableExpanders()
    {
        return $this->expanderOptions;
    }

    /**
     * Sets the view settings from EDS API info method call data
     *
     * @return number
     */
    protected function populateViewSettings()
    {
        if (isset($this->apiInfo)
            && isset($this->apiInfo['ViewResultSettings'])
        ) {
            //default result Limit
            if (isset($this->apiInfo['ViewResultSettings']['ResultsPerPage'])) {
                $this->defaultLimit
                    = $this->apiInfo['ViewResultSettings']['ResultsPerPage'];
            } else {
                $this->defaultLimit = 20;
            }

            //default view (amount)
            if (isset($this->apiInfo['ViewResultSettings']['ResultListView'])) {
                $this->defaultView = 'list|'
                    . $this->apiInfo['ViewResultSettings']['ResultListView'];
            } else {
                $this->defaultView = 'list|brief';
            }
        }
    }

    /**
     * Get a translation string (if available) or else use a default
     *
     * @param string $label   Translation string to look up
     * @param string $default Default to use if no translation found
     *
     * @return string
     */
    protected function getLabelForCheckboxFilter($label, $default)
    {
        // If translation doesn't change the label, we don't want to
        // display the non-human-readable version so we should instead
        // return the EDS-provided English default.
        return ($label == $this->translate($label))
            ? $default : $label;
    }

    /**
     * Obtain limiters to display on the basic search screen
     *
     * @return array
     */
    public function getSearchScreenLimiters()
    {
        $ssLimiterOptions = [];
        if (isset($this->commonLimiters)) {
            foreach ($this->commonLimiters as $key) {
                $limiter = $this->limiterOptions[$key];
                $ssLimiterOptions[$key] = [
                    'selectedvalue' => 'LIMIT|' . $key . ':y',
                    'description' => $this->getLabelForCheckboxFilter(
                        'eds_limiter_' . $key, $limiter['Label']
                    ),
                    'selected' => ('y' == $limiter['DefaultOn']) ? true : false
                ];
            }
        }
        return $ssLimiterOptions;
    }

    /**
     * Obtain expanders to display on the basic search screen
     *
     * @return array
     */
    public function getSearchScreenExpanders()
    {
        $ssExpanderOptions = [];
        if (isset($this->commonExpanders)) {
            foreach ($this->commonExpanders as $key) {
                $expander = $this->expanderOptions[$key];
                $ssExpanderOptions[$key] = [
                    'selectedvalue' => 'EXPAND:' . $key,
                    'description' => $this->getLabelForCheckboxFilter(
                        'eds_expander_' . $key, $expander['Label']
                    ),
                ];
            }
        }
        return $ssExpanderOptions;
    }

    /**
     * Get default view setting.
     *
     * @return int
     */
    public function getDefaultView()
    {
        $viewArr = explode('|', $this->defaultView);
        return $viewArr[0];
    }

    /**
     * Add a hidden (i.e. not visible in facet controls) filter query to the object.
     *
     * @param string $fq Filter query for Solr.
     *
     * @return void
     */
    public function addHiddenFilter($fq)
    {
        $this->hiddenFilters[] = $fq;
    }

    /**
     * Get an array of hidden filters.
     *
     * @return array
     */
    public function getHiddenFilters()
    {
        return $this->hiddenFilters;
    }

    /**
     * Get default filters to apply to an empty search.
     *
     * @return array
     */
    public function getDefaultFilters()
    {
        // Populate defaults if not already set:
        if (empty($this->defaultFilters)) {
            //expanders
            $expanders = $this->getDefaultExpanders();
            foreach ($expanders as $expander) {
                $this->defaultFilters[] = 'EXPAND:' . $expander;
            }

            //limiters
            $limiters = $this->getAvailableLimiters();
            foreach ($limiters as $key => $value) {
                if ('select' == $value['Type'] && 'y' == $value['DefaultOn']) {
                    // only select limiters can be defaulted on limiters can be
                    // defaulted
                    $val = $value['LimiterValues'][0]['Value'];
                    $this->defaultFilters[] = 'LIMIT|' . $key . ':' . $val;
                }
            }
        }
        return $this->defaultFilters;
    }
}