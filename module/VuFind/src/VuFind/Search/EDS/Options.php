<?php

/**
 * EDS API Options
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
 * Copyright (C) The National Library of Finland 2022
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\EDS;

use function count;
use function in_array;
use function is_callable;

/**
 * EDS API Options
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;

    /**
     * Default limit option
     *
     * @var ?int
     */
    protected $defaultLimit = null;

    /**
     * Default view option
     *
     * @var ?string
     */
    protected $defaultView = null;

    /**
     * Available search mode options
     *
     * @var array
     */
    protected $modeOptions = [];

    /**
     * Default search mode options
     *
     * @var string
     */
    protected $defaultMode = 'all';

    /**
     * The set search mode
     *
     * @var string
     */
    protected $searchMode;

    /**
     * Default expanders to apply
     *
     * @var array
     */
    protected $defaultExpanders = [];

    /**
     * Available expander options
     *
     * @var array
     */
    protected $expanderOptions = [];

    /**
     * Available limiter options
     *
     * @var array
     */
    protected $limiterOptions = [];

    /**
     * Limiters enabled on advanced search screen (empty for all available)
     *
     * @var string[]
     */
    protected $advancedLimiters = [];

    /**
     * Available Search Options from the API or null if not yet initialized
     *
     * @var ?array
     */
    protected $apiInfo;

    /**
     * Callback to get available Search Options from the API
     *
     * @var ?callable
     */
    protected $apiInfoCallback = null;

    /**
     * Whether settings based on API info have been initialized
     *
     * @var bool
     */
    protected $apiOptionsInitialized = false;

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
     * Search configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $searchSettings;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param array|callable               $apiInfo      API information or callback
     * to retrieve it
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        $apiInfo = null
    ) {
        $this->searchIni = $this->facetsIni = 'EDS';
        $this->searchSettings = $configLoader->get($this->searchIni);
        parent::__construct($configLoader);
        // 2015-06-30 RF - Changed to unlimited
        //$this->resultLimit = 100;
        $this->viewOptions = [
            'list|title' => 'Title View',
            'list|brief' => 'Brief View',
            'list|detailed' => 'Detailed View',
        ];
        // If we get the API info as a callback, defer until it's actually needed to
        // avoid calling the API:
        if (is_callable($apiInfo)) {
            $this->apiInfo = null;
            $this->apiInfoCallback = $apiInfo;
        } else {
            $this->apiInfo = $apiInfo ?? [];
            $this->setOptionsFromApi();
        }
        $this->setOptionsFromConfig();
        $facetConf = $configLoader->get($this->facetsIni);
        if (
            isset($facetConf->Advanced_Facet_Settings->translated_facets)
            && count($facetConf->Advanced_Facet_Settings->translated_facets) > 0
        ) {
            $this->setTranslatedFacets(
                $facetConf->Advanced_Facet_Settings->translated_facets->toArray()
            );
        }
        // Make sure first-last navigation is never enabled since we cannot support:
        $this->firstLastNavigationSupported = false;
    }

    /**
     * Basic 'getter' for advanced search handlers.
     *
     * @return array
     */
    public function getAdvancedHandlers()
    {
        return $this->getApiProperty('advancedHandlers');
    }

    /**
     * Basic 'getter' for basic search handlers.
     *
     * @return array
     */
    public function getBasicHandlers()
    {
        return $this->getApiProperty('basicHandlers');
    }

    /**
     * Get default search handler.
     *
     * @return string
     */
    public function getDefaultHandler()
    {
        $this->setOptionsFromApi();
        return parent::getDefaultHandler();
    }

    /**
     * Get an array of sort options.
     *
     * @return array
     */
    public function getSortOptions()
    {
        return $this->getApiProperty('sortOptions');
    }

    /**
     * Get default limit setting.
     *
     * @return int
     */
    public function getDefaultLimit()
    {
        return $this->getApiProperty('defaultLimit');
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
        return $this->getApiProperty('defaultView');
    }

    /**
     * Get an array of search mode options
     *
     * @return array
     */
    public function getModeOptions()
    {
        return $this->getApiProperty('modeOptions');
    }

    /**
     * Get the default search mode
     *
     * @return string
     */
    public function getDefaultMode()
    {
        return $this->getApiProperty('defaultMode');
    }

    /**
     * Return the view associated with this configuration
     *
     * @return string
     */
    public function getEdsView()
    {
        $viewArr = explode('|', $this->getApiProperty('defaultView'));
        return (1 < count($viewArr)) ? $viewArr[1] : $this->defaultView;
    }

    /**
     * Return the expander ids that have the default on flag set in admin
     *
     * @return array
     */
    public function getDefaultExpanders()
    {
        return $this->getApiProperty('defaultExpanders');
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
        if ($this->apiOptionsInitialized) {
            return;
        }
        $this->apiOptionsInitialized = true;

        // If we don't have API options yet, try to fetch them:
        if (null === $this->apiInfo && $this->apiInfoCallback) {
            $this->apiInfo = ($this->apiInfoCallback)();
        }
        // Set options from the INFO method first. If settings are set in the config
        // file, use them as 'overrides', but only if they are available (ie. are
        // returned in the INFO method)
        $this->populateViewSettings();
        $this->populateSearchCriteria();

        // Search handler setup. Only valid values set in the config files are used.
        $this->filterAndReorderProperty(
            'Basic_Searches',
            'basicHandlers'
        );
        $this->filterAndReorderProperty(
            'Advanced_Searches',
            'advancedHandlers'
        );

        // Sort preferences:
        $this->filterAndReorderProperty('Sorting', 'sortOptions');

        // Apply overrides from configuration:
        $defaultMode = $this->searchSettings->General->default_mode ?? null;
        if (null !== $defaultMode && isset($this->modeOptions[$defaultMode])) {
            $this->defaultMode = $defaultMode;
        }

        $defaultSort = $this->searchSettings->General->default_sort ?? null;
        if (null !== $defaultSort && isset($this->sortOptions[$defaultSort])) {
            $this->defaultSort = $defaultSort;
        }

        // Set common limiters and expanders.
        // Only the values that are valid for this profile will be used.
        $this->setCommonSettings(
            'common_limiters',
            'limiterOptions',
            'commonLimiters'
        );
        $this->setCommonSettings(
            'common_expanders',
            'expanderOptions',
            'commonExpanders'
        );
    }

    /**
     * Apply user settings. All legal values have already been loaded from the API
     * at the time this method is called, so we just need to check if the
     * user-supplied values are valid, and if so, filter/reorder accordingly.
     *
     * @param string $section  Configuration section to read
     * @param string $property Property of this object to read and/or modify.
     *
     * @return void
     */
    protected function filterAndReorderProperty(
        string $section,
        string $property
    ): void {
        if (!isset($this->searchSettings->$section)) {
            return;
        }

        // Use a reference to access $this->$property to avoid the awkward/ambiguous
        // expression $this->$property[$key] below.
        $propertyRef = & $this->$property;

        $newPropertyValues = [];
        foreach ($this->searchSettings->$section as $key => $value) {
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
     * @param string $setting Name of common setting
     * @param string $list    Name of property containing valid values
     * @param string $target  Name of property to populate
     *
     * @return void
     */
    protected function setCommonSettings(
        string $setting,
        string $list,
        string $target
    ): void {
        if (!empty($this->searchSettings->General->$setting)) {
            $userValues = explode(',', $this->searchSettings->General->$setting);

            if (!empty($this->$list)) {
                // Reference to property containing API-provided list of legal values
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
     * Load options from the configuration file.
     *
     * @return void
     */
    protected function setOptionsFromConfig()
    {
        if (isset($this->searchSettings->General->default_limit)) {
            $this->defaultLimit = $this->searchSettings->General->default_limit;
        }
        if (isset($this->searchSettings->General->limit_options)) {
            $this->limitOptions = $this->explodeListSetting($this->searchSettings->General->limit_options);
        }

        // Set up highlighting preference
        if (isset($this->searchSettings->General->highlighting)) {
            // For legacy config compatibility, support the "n" value to disable highlighting:
            $falsyStrings = ['n', 'false'];
            $this->highlight = in_array(strtolower($this->searchSettings->General->highlighting), $falsyStrings)
                ? false
                : (bool)$this->searchSettings->General->highlighting;
        }

        // View preferences
        if (isset($this->searchSettings->General->default_view)) {
            $this->defaultView
                = 'list|' . $this->searchSettings->General->default_view;
        }

        // Load list view for result (controls AJAX embedding vs. linking)
        if (isset($this->searchSettings->List->view)) {
            $this->listviewOption = $this->searchSettings->List->view;
        }

        if (isset($this->searchSettings->Advanced_Facet_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $this->searchSettings->Advanced_Facet_Settings->special_facets;
        }

        // Load autocomplete preferences:
        $this->configureAutocomplete($this->searchSettings);

        if (isset($this->searchSettings->General->advanced_limiters)) {
            $this->advancedLimiters = $this->explodeListSetting($this->searchSettings->General->advanced_limiters);
        }
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
                return 'sort_year_asc';
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
        if (isset($this->apiInfo['AvailableSearchCriteria'])) {
            // Reference for readability:
            $availCriteria = & $this->apiInfo['AvailableSearchCriteria'];

            // Sort preferences
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
                        'Label' => $mode['Label'], 'Value' => $mode['Mode'],
                    ];
                    if (
                        isset($mode['DefaultOn'])
                        && 'y' == $mode['DefaultOn']
                    ) {
                        $this->defaultMode = $mode['Mode'];
                    }
                }
            }

            // Expanders
            $this->expanderOptions = [];
            $this->defaultExpanders = [];
            if (isset($availCriteria['AvailableExpanders'])) {
                foreach ($availCriteria['AvailableExpanders'] as $expander) {
                    $this->expanderOptions[$expander['Id']] = [
                        'Label' => $expander['Label'], 'Value' => $expander['Id'],
                    ];
                    if (
                        isset($expander['DefaultOn'])
                        && 'y' == $expander['DefaultOn']
                    ) {
                        $this->defaultExpanders[] = $expander['Id'];
                    }
                }
            }

            // Limiters
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
                        'DefaultOn' => $limiter['DefaultOn'] ?? 'n',
                    ];
                }
            }
        }
    }

    /**
     * Populate limiter values from the EDS API INFO method data
     *
     * @param array $limiterValues Limiter values from the API
     *
     * @return array
     */
    protected function populateLimiterValues(array $limiterValues)
    {
        $availableLimiterValues = [];
        foreach ($limiterValues as $limiterValue) {
            $availableLimiterValues[] = [
                'Value' => $limiterValue['Value'],
                'LimiterValues' => isset($limiterValue['LimiterValues'])
                    ? $this
                        ->populateLimiterValues($limiterValue['LimiterValues'])
                    : null,
            ];
        }
        return empty($availableLimiterValues) ? null : $availableLimiterValues;
    }

    /**
     * Get the value of a property that is retrieved via the Info method and stored
     * in a member property.
     *
     * @param string $propertyName Name of the member property
     *
     * @return mixed
     */
    protected function getApiProperty(string $propertyName)
    {
        $this->setOptionsFromApi();
        return $this->$propertyName;
    }

    /**
     * Returns the available limiters
     *
     * @return array
     */
    public function getAvailableLimiters()
    {
        return $this->getApiProperty('limiterOptions');
    }

    /**
     * Returns the enabled limiters for the advanced search
     *
     * @return array
     */
    public function getAdvancedLimiters()
    {
        // Make sure that everything is labeled with an appropriate translation
        // string:
        $labeledLimiters = array_map(
            function ($limiter) {
                $limiter['Label'] = $this->getLabelForCheckboxFilter(
                    'eds_limiter_' . $limiter['Id'],
                    $limiter['Label']
                );
                return $limiter;
            },
            $this->getApiProperty('limiterOptions')
        );
        // No setting = use all available values
        if (!$this->advancedLimiters) {
            return $labeledLimiters;
        }
        // If we got this far, let's create a list of enabled limiters in the
        // requested order:
        $enabledLimiters = [];
        foreach ($this->advancedLimiters as $limiter) {
            if (isset($labeledLimiters[$limiter])) {
                $enabledLimiters[$limiter] = $labeledLimiters[$limiter];
            }
        }
        return $enabledLimiters;
    }

    /**
     * Returns the available expanders
     *
     * @return array
     */
    public function getAvailableExpanders()
    {
        return $this->getApiProperty('expanderOptions');
    }

    /**
     * Sets the view settings from EDS API info method call data
     *
     * @return void
     */
    protected function populateViewSettings()
    {
        $settings = $this->apiInfo['ViewResultSettings'] ?? [];
        // default result Limit
        $this->defaultLimit ??= $settings['ResultsPerPage'] ?? 20;

        // default view
        $this->defaultView ??= 'list|' . ($settings['ResultListView'] ?? 'brief');
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
        $limiterOptions = $this->getApiProperty('limiterOptions');
        foreach ($this->getApiProperty('commonLimiters') as $key) {
            $limiter = $limiterOptions[$key];
            $ssLimiterOptions[$key] = [
                'selectedvalue' => 'LIMIT|' . $key . ':y',
                'description' => $this->getLabelForCheckboxFilter(
                    'eds_limiter_' . $key,
                    $limiter['Label']
                ),
                'selected' => ('y' == $limiter['DefaultOn']) ? true : false,
            ];
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
        $expanderOptions = $this->getApiProperty('expanderOptions');
        foreach ($this->getApiProperty('commonExpanders') as $key) {
            $expander = $expanderOptions[$key];
            $ssExpanderOptions[$key] = [
                'selectedvalue' => 'EXPAND:' . $key,
                'description' => $this->getLabelForCheckboxFilter(
                    'eds_expander_' . $key,
                    $expander['Label']
                ),
            ];
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
        $viewArr = explode('|', $this->getApiProperty('defaultView'));
        return $viewArr[0];
    }

    /**
     * Get default filters to apply to an empty search.
     *
     * @return array
     */
    public function getDefaultFilters()
    {
        // Populate defaults if not already set:
        if (!$this->defaultFilters) {
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
