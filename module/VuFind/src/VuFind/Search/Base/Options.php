<?php

/**
 * Abstract options search model.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\ConfigManagerInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function count;
use function get_class;
use function in_array;
use function intval;
use function is_array;

/**
 * Abstract options search model.
 *
 * This abstract class defines the option methods for modeling a search in VuFind.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class Options implements TranslatorAwareInterface
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Available sort options
     *
     * @var array
     */
    protected array $sortOptions;

    /**
     * Allowed hidden sort options
     *
     * @var array
     */
    protected array $hiddenSortOptions = [];

    /**
     * Available sort options for facets
     *
     * @var array
     */
    protected array $facetSortOptions = [];

    /**
     * Overall default sort option
     *
     * @var string
     */
    protected string $defaultSort;

    /**
     * Handler-specific defaults
     *
     * @var array
     */
    protected array $defaultSortByHandler;

    /**
     * RSS-specific sort option
     *
     * @var ?string
     */
    protected ?string $rssSort;

    /**
     * Default search handler
     *
     * @var ?string
     */
    protected ?string $defaultHandler;

    /**
     * Advanced search handlers
     *
     * @var array
     */
    protected array $advancedHandlers;

    /**
     * Basic search handlers
     *
     * @var array
     */
    protected array $basicHandlers;

    /**
     * Special advanced facet settings
     *
     * @var string
     */
    protected string $specialAdvancedFacets = '';

    /**
     * Should we retain filters by default?
     *
     * @var bool
     */
    protected bool $retainFiltersByDefault;

    /**
     * Should we display a "Reset Filters" link regardless of retainFiltersByDefault?
     *
     * @var bool
     */
    protected bool $alwaysDisplayResetFilters;

    /**
     * Default filters to apply to new searches
     *
     * @var array
     */
    protected array $defaultFilters;

    /**
     * Default limit option
     *
     * @var ?int
     */
    protected ?int $defaultLimit;

    /**
     * Available limit options
     *
     * @var int[]
     */
    protected array $limitOptions;

    /**
     * If result scroller is used.
     *
     * @var bool
     */
    protected bool $resultScrollerActive = false;

    /**
     * Default view option
     *
     * @var string
     */
    protected string $defaultView = 'list';

    /**
     * Available view options
     *
     * @var array
     */
    protected array $viewOptions = [];

    /**
     * Default delimiter used for delimited facets
     *
     * @var string
     */
    protected string $defaultFacetDelimiter = '{{{_:::_}}}';

    /**
     * Facet settings
     *
     * @var array
     */
    protected array $delimitedFacets = [];

    /**
     * Convenient field => delimiter lookup array derived from $delimitedFacets.
     *
     * @var ?array
     */
    protected ?array $processedDelimitedFacets = null;

    /**
     * Facet settings
     *
     * @var array
     */
    protected array $translatedFacets = [];

    /**
     * Text domains for translated facets
     *
     * @var array
     */
    protected array $translatedFacetsTextDomains = [];

    /**
     * Formats for translated facets
     *
     * @var array
     */
    protected array $translatedFacetsFormats = [];

    /**
     * Hierarchical facets
     *
     * @var array
     */
    protected array $hierarchicalFacets = [];

    /**
     * Hierarchical facet separators
     *
     * @var array
     */
    protected array $hierarchicalFacetSeparators = [];

    /**
     * Hierarchical facet sort settings
     *
     * @var array
     */
    protected array $hierarchicalFacetSortSettings = [];

    /**
     * Spelling setting
     *
     * @var bool
     */
    protected bool $spellcheck = true;

    /**
     * Available shards
     *
     * @var array
     */
    protected array $shards = [];

    /**
     * Default selected shards
     *
     * @var array
     */
    protected array $defaultSelectedShards = [];

    /**
     * Should we present shard checkboxes to the user?
     *
     * @var bool
     */
    protected bool $visibleShardCheckboxes = false;

    /**
     * Highlighting setting
     *
     * @var bool
     */
    protected bool $highlight = false;

    /**
     * Autocomplete setting
     *
     * @var bool
     */
    protected bool $autocompleteEnabled = false;

    /**
     * Autocomplete auto submit setting
     *
     * @var bool
     */
    protected bool $autocompleteAutoSubmit = true;

    /**
     * Autocomplete apply active filters setting (null to fall back to retainFiltersByDefault setting)
     *
     * @var ?bool
     */
    protected $autocompleteApplyActiveFilters = null;

    /*
     * Autocomplete max display items setting
     *
     * @var int
     */
    protected $autocompleteDisplayLimit = 20;

    /**
     * Autocomplete query formatting rules
     *
     * @var array
     */
    protected array $autocompleteFormattingRules = [];

    /**
     * Configuration file to read global settings from
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected string $mainIni = 'config';

    /**
     * Configuration file to read search settings from
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected string $searchIni = 'searches';

    /**
     * Configuration file to read facet settings from
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected string $facetsIni = 'facets';

    /**
     * Active list view option (see [List] in searches.ini).
     *
     * @var string
     */
    protected string $listviewOption;

    /**
     * Maximum number of results (-1 = unlimited)
     *
     * @var int
     */
    protected int $resultLimit;

    /**
     * Default result limit if not set in configuration.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var int
     */
    protected int $defaultResultLimit = -1;

    /**
     * Maximum supported value for $resultLimit above, or null for no limit.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var ?int
     */
    protected ?int $maxResultLimit = null;

    /**
     * Is first/last navigation supported by the backend?
     *
     * @var bool
     */
    protected bool $firstLastNavigationSupported = true;

    /**
     * Is the record page first/last navigation scroller enabled?
     *
     * @var bool
     */
    protected bool $recordPageFirstLastNavigation = false;

    /**
     * Should hierarchicalFacetFilters and hierarchicalExcludeFilters
     * apply in advanced search
     *
     * @var bool
     */
    protected bool $filterHierarchicalFacetsInAdvanced;

    /**
     * Hierarchical exclude filters
     *
     * @var array
     */
    protected array $hierarchicalExcludeFilters;

    /**
     * Hierarchical facet filters
     *
     * @var array
     */
    protected array $hierarchicalFacetFilters;

    /**
     * Top pagination control style (none, simple or full)
     *
     * @var string
     */
    protected string $topPaginatorStyle;

    /**
     * Is loading of results with JavaScript enabled?
     *
     * @var bool
     */
    protected bool $loadResultsWithJs;

    /**
     * Should we display citation search links in results?
     *
     * @var bool
     */
    protected bool $displayCitationLinksInResults;

    /**
     * Should we display a warning in restricted views?
     *
     * @var bool
     */
    protected bool $showRestrictedViewWarning;

    /**
     * VuFind main configuration
     *
     * @var array
     */
    protected array $mainConfig;

    /**
     * Search settings
     *
     * @var array
     */
    protected array $searchSettings;

    /**
     * Facet settings
     *
     * @var array
     */
    protected array $facetSettings;

    /**
     * Section name for advanced facet settings
     *
     * @var string
     */
    protected string $advancedFacetSettingsSection = 'Advanced_Settings';

    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(protected ConfigManagerInterface $configManager)
    {
        $this->mainConfig = $configManager->getConfigArray($this->mainIni);
        $this->searchSettings = $configManager->getConfigArray($this->searchIni);
        $this->facetSettings = $configManager->getConfigArray($this->facetsIni);

        // Search handlers:
        $this->basicHandlers = $this->searchSettings['Basic_Searches'] ?? [];
        $this->advancedHandlers = $this->searchSettings['Advanced_Searches'] ?? [];
        $this->defaultHandler = $this->searchSettings['General']['default_handler'] ?? null;

        // Limit preferences:
        $this->defaultLimit = $this->searchSettings['General']['default_limit'] ?? 20;
        $this->limitOptions = $this->explodeListSetting(
            $this->searchSettings['General']['limit_options'] ?? '',
            'intval'
        );
        $this->resultLimit = (int)($this->searchSettings['General']['result_limit'] ?? $this->defaultResultLimit);
        if ($this->maxResultLimit) {
            $this->resultLimit = -1 === $this->resultLimit
                ? $this->maxResultLimit
                : min($this->resultLimit, $this->maxResultLimit);
        }

        // Sort options:
        $this->sortOptions = $this->searchSettings['Sorting'] ?? [];
        $this->defaultSort = $this->searchSettings['General']['default_sort'] ?? 'relevance';
        $this->defaultSortByHandler = (array)($this->searchSettings['DefaultSortingByType'] ?? []);
        $this->rssSort = $this->searchSettings['RSS']['sort'] ?? null;
        $this->initializeHiddenSortOptions();

        // View options:
        $this->listviewOption = $this->searchSettings['List']['view'] ?? 'full';

        // Filter options:
        $this->defaultFilters = $this->searchSettings['General']['default_filters'] ?? [];
        $this->retainFiltersByDefault = $this->searchSettings['General']['retain_filters_by_default'] ?? true;
        $this->alwaysDisplayResetFilters = $this->searchSettings['General']['always_display_reset_filters'] ?? false;

        // Facet settings:
        $id = $this->getSearchClassId();
        if (isset($this->facetSettings['AvailableFacetSortOptions'][$id])) {
            $sortArray = (array)$this->facetSettings['AvailableFacetSortOptions'][$id];
            foreach ($sortArray as $facet => $sortOptions) {
                $this->facetSortOptions[$facet] = [];
                foreach (explode(',', $sortOptions) as $fieldAndLabel) {
                    [$field, $label] = explode('=', $fieldAndLabel);
                    $this->facetSortOptions[$facet][$field] = $label;
                }
            }
        }

        $advancedFacetSettings = $this->facetSettings[$this->advancedFacetSettingsSection] ?? [];
        $this->filterHierarchicalFacetsInAdvanced = !empty($advancedFacetSettings['enable_hierarchical_filters']);
        $this->hierarchicalExcludeFilters = $this->facetSettings['HierarchicalExcludeFilters'] ?? [];
        $this->hierarchicalFacetFilters = $this->facetSettings['HierarchicalFacetFilters'] ?? [];
        $this->setTranslatedFacets((array)($advancedFacetSettings['translated_facets'] ?? []));
        $this->specialAdvancedFacets = $advancedFacetSettings['special_facets'] ?? '';

        // Result display options:
        $this->resultScrollerActive = (bool)(
            $this->searchSettings['Record']['next_prev_navigation']
            ?? $this->mainConfig['Record']['next_prev_navigation']
            ?? false
        );
        $this->loadResultsWithJs = (bool)($this->searchSettings['General']['load_results_with_js'] ?? true);
        $this->topPaginatorStyle = $this->searchSettings['General']['top_paginator']
            ?? ($this->loadResultsWithJs ? 'simple' : false);

        $this->displayCitationLinksInResults
            = (bool)($this->searchSettings['Results_Settings']['display_citation_links'] ?? true);
        $this->showRestrictedViewWarning
            = (bool)($this->searchSettings['General']['show_restricted_view_warning'] ?? false);
    }

    /**
     * Get string listing special advanced facet types.
     *
     * @return string
     */
    public function getSpecialAdvancedFacets(): string
    {
        return $this->specialAdvancedFacets;
    }

    /**
     * Basic 'getter' for advanced search handlers.
     *
     * @return array
     */
    public function getAdvancedHandlers(): array
    {
        return $this->advancedHandlers;
    }

    /**
     * Basic 'getter' for basic search handlers.
     *
     * @return array
     */
    public function getBasicHandlers(): array
    {
        return $this->basicHandlers;
    }

    /**
     * Given a label from the configuration file, return the name of the matching
     * handler (basic checked first, then advanced); return the default handler
     * if no match is found.
     *
     * @param ?string $label Label to search for
     *
     * @return string
     */
    public function getHandlerForLabel(?string $label): string
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
     * @param ?string $handler Handler name to look up.
     *
     * @return string
     */
    public function getLabelForBasicHandler(?string $handler): string
    {
        $handlers = $this->getBasicHandlers();
        return $handlers[$handler] ?? false;
    }

    /**
     * Get default search handler.
     *
     * @return string
     */
    public function getDefaultHandler(): string
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
    public function getDefaultLimit(): int
    {
        return $this->defaultLimit;
    }

    /**
     * Get an array of limit options.
     *
     * @return int[]
     */
    public function getLimitOptions(): array
    {
        if (empty($this->limitOptions)) {
            $this->limitOptions = [$this->getDefaultLimit()];
        }
        return $this->limitOptions;
    }

    /**
     * If result scroller is used.
     *
     * @return bool
     */
    public function resultScrollerActive(): bool
    {
        return $this->resultScrollerActive;
    }

    /**
     * Get the name of the ini file used for configuring facet parameters in this
     * object.
     *
     * @return string
     */
    public function getFacetsIni(): string
    {
        return $this->facetsIni;
    }

    /**
     * Get the name of the ini file used for loading primary settings in this
     * object.
     *
     * @return string
     */
    public function getMainIni(): string
    {
        return $this->mainIni;
    }

    /**
     * Get the name of the ini file used for configuring search parameters in this
     * object.
     *
     * @return string
     */
    public function getSearchIni(): string
    {
        return $this->searchIni;
    }

    /**
     * Override the limit options.
     *
     * @param int[] $options New options to set.
     *
     * @return void
     */
    public function setLimitOptions(array $options): void
    {
        if (!empty($options)) {
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
    public function getSortOptions(): array
    {
        return $this->sortOptions;
    }

    /**
     * Get an array of hidden sort options.
     *
     * @return array An array of associative arrays with keys 'label' and 'pattern'
     */
    public function getHiddenSortOptions(): array
    {
        return $this->hiddenSortOptions;
    }

    /**
     * Get an array of sort options for a facet.
     *
     * @param string $facet Facet
     *
     * @return array
     */
    public function getFacetSortOptions(string $facet = '*'): array
    {
        return $this->facetSortOptions[$facet] ?? $this->facetSortOptions['*'] ?? [];
    }

    /**
     * Get the default sort option for the specified search handler.
     *
     * @param ?string $handler Search handler being used (null for default)
     *
     * @return string
     */
    public function getDefaultSortByHandler(?string $handler = null): string
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
    public function getRssSort(string $sort): string
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
     * Get the configured default view.
     *
     * @return string
     */
    protected function getConfiguredDefaultView(): string
    {
        return $this->defaultView;
    }

    /**
     * Set the configured default view.
     *
     * @param string $defaultView Default view
     *
     * @return void
     */
    protected function setConfiguredDefaultView(string $defaultView): void
    {
        $this->defaultView = $defaultView;
    }

    /**
     * Get default view setting.
     *
     * This determines how the results are presented (e.g. as list or grid)
     *
     * @return string
     */
    public function getDefaultView(): string
    {
        return $this->getConfiguredDefaultView();
    }

    /**
     * Get an array of view options.
     *
     * @return array
     */
    public function getViewOptions(): array
    {
        return $this->viewOptions;
    }

    /**
     * Returns the defaultFacetDelimiter value.
     *
     * @return string
     */
    public function getDefaultFacetDelimiter(): string
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
    public function setDefaultFacetDelimiter(string $defaultFacetDelimiter): void
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
    public function getDelimitedFacets(bool $processed = false): array
    {
        if (!$processed) {
            return $this->delimitedFacets;
        }
        if (null === $this->processedDelimitedFacets) {
            $this->processedDelimitedFacets = [];
            $defaultDelimiter = $this->getDefaultFacetDelimiter();
            foreach ($this->delimitedFacets as $current) {
                $parts = explode('|', $current, 2);
                $this->processedDelimitedFacets[$parts[0]] = count($parts) == 2
                    ? $parts[1]
                    : $defaultDelimiter;
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
    public function setDelimitedFacets(array $delimitedFacets): void
    {
        $this->delimitedFacets = $delimitedFacets;
        $this->processedDelimitedFacets = null; // clear processed value cache
    }

    /**
     * Get a list of facets that are subject to translation.
     *
     * @return array
     */
    public function getTranslatedFacets(): array
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
    public function setTranslatedFacets(array $facets): void
    {
        // Reset properties:
        $this->translatedFacets = $this->translatedFacetsTextDomains
            = $this->translatedFacetsFormats = [];

        // Fill in new data:
        foreach ($facets as $current) {
            $parts = explode(':', $current);
            $this->translatedFacets[] = $parts[0];
            if (isset($parts[1])) {
                $this->translatedFacetsTextDomains[$parts[0]] = $parts[1];
            }
            if (isset($parts[2])) {
                $this->translatedFacetsFormats[$parts[0]] = $parts[2];
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
    public function getTextDomainForTranslatedFacet(string $field): string
    {
        return $this->translatedFacetsTextDomains[$field] ?? 'default';
    }

    /**
     * Look up the format for use when translating a particular facet
     * field.
     *
     * @param string $field Field name being translated
     *
     * @return ?string
     */
    public function getFormatForTranslatedFacet(string $field): ?string
    {
        return $this->translatedFacetsFormats[$field] ?? null;
    }

    /**
     * Get hierarchical facet fields.
     *
     * @return array
     */
    public function getHierarchicalFacets(): array
    {
        return $this->hierarchicalFacets;
    }

    /**
     * Get hierarchical facet separators.
     *
     * @return array
     */
    public function getHierarchicalFacetSeparators(): array
    {
        return $this->hierarchicalFacetSeparators;
    }

    /**
     * Get hierarchical facet sort settings.
     *
     * @return array
     */
    public function getHierarchicalFacetSortSettings(): array
    {
        return $this->hierarchicalFacetSortSettings;
    }

    /**
     * Get current spellcheck setting and (optionally) change it.
     *
     * @param ?bool $bool True to enable, false to disable, null to leave alone
     *
     * @return bool
     */
    public function spellcheckEnabled(?bool $bool = null): bool
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
    public function highlightEnabled(): bool
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
    public function getHumanReadableFieldName(string $field): string
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
    public function disableHighlighting(): void
    {
        $this->highlight = false;
    }

    /**
     * Is autocomplete enabled?
     *
     * @return bool
     */
    public function autocompleteEnabled(): bool
    {
        return $this->autocompleteEnabled;
    }

    /**
     * Should autocomplete auto submit?
     *
     * @return bool
     */
    public function autocompleteAutoSubmit(): bool
    {
        return $this->autocompleteAutoSubmit;
    }

    /**
     * Should autocomplete apply active filters?
     *
     * @return bool
     */
    public function autocompleteApplyActiveFilters(): bool
    {
        return $this->autocompleteApplyActiveFilters ?? $this->getRetainFilterSetting();
    }

    /**
     * Get max number of displayed suggestions
     *
     * @return array
     */
    public function getAutocompleteDisplayLimit(): int
    {
        return $this->autocompleteDisplayLimit;
    }

    /**
     * Get autocomplete query formatting rules.
     *
     * @return array
     */
    public function getAutocompleteFormattingRules(): array
    {
        return $this->autocompleteFormattingRules;
    }

    /**
     * Get a string of the listviewOption (full or tab).
     *
     * @return string
     */
    public function getListViewOption(): string
    {
        return $this->listviewOption;
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    abstract public function getSearchAction(): string;

    /**
     * Return the route name for the search home action.
     *
     * @return string
     */
    public function getSearchHomeAction(): string
    {
        // Assume the home action is the same as the search action, only with
        // a "-home" suffix in place of the search action.
        $basicSearch = $this->getSearchAction();
        return substr($basicSearch, 0, strpos($basicSearch, '-')) . '-home';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns null if the feature is not supported.
     *
     * @return ?string
     */
    public function getAdvancedSearchAction(): ?string
    {
        // Assume unsupported by default:
        return null;
    }

    /**
     * Return the route name for the facet list action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getFacetListAction(): ?string
    {
        return null;
    }

    /**
     * Return the route name for the versions search action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getVersionsAction(): ?string
    {
        return null;
    }

    /**
     * Return the route name for the "cites" search action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getCitesAction(): ?string
    {
        return null;
    }

    /**
     * Return the route name for the "cited by" search action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getCitedByAction(): ?string
    {
        return false;
    }

    /**
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart(): bool
    {
        // Assume true by default.
        return true;
    }

    /**
     * Get default filters to apply to an empty search.
     *
     * @return array
     */
    public function getDefaultFilters(): array
    {
        return $this->defaultFilters;
    }

    /**
     * Should filter settings be retained across searches by default?
     *
     * @return bool
     */
    public function getRetainFilterSetting(): bool
    {
        return $this->retainFiltersByDefault;
    }

    /**
     * Should the "Reset Filters" button be displayed?
     *
     * @return bool
     */
    public function shouldDisplayResetFilters(): bool
    {
        return $this->alwaysDisplayResetFilters || $this->getRetainFilterSetting();
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
    public function getShards(): array
    {
        return $this->shards;
    }

    /**
     * Get an array of default selected shards (values correspond with keys returned
     * by getShards().
     *
     * @return array
     */
    public function getDefaultSelectedShards(): array
    {
        return $this->defaultSelectedShards;
    }

    /**
     * Should we display shard checkboxes for this object?
     *
     * @return bool
     */
    public function showShardCheckboxes(): bool
    {
        return $this->visibleShardCheckboxes;
    }

    /**
     * If there is a limit to how many search results a user can access, this
     * method will return that limit. If there is no limit, this will return -1.
     *
     * @return int
     */
    public function getVisibleSearchResultLimit(): int
    {
        return intval($this->resultLimit);
    }

    /**
     * Load all API-related settings from the relevant ini file(s).
     *
     * @return array
     */
    public function getAPISettings(): array
    {
        // Inherit defaults from searches.ini (if that is not already the
        // configured search settings file):
        $defaultSettings = $this->configManager->getConfigArray('searches')['API'] ?? [];
        $localIni = $this->getSearchIni();
        $localSettings = ($localIni !== 'searches')
            ? $this->configManager->getConfigArray($localIni)['API'] ?? [] : [];
        return array_merge($defaultSettings, $localSettings);
    }

    /**
     * Load all recommendation settings from the relevant ini file. Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @param ?string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     */
    public function getRecommendationSettings(?string $handler = null): array
    {
        // Load the necessary settings to determine the appropriate recommendations
        // module:
        $searchSettings = $this->configManager->getConfigArray($this->getSearchIni());

        // Load a type-specific recommendations setting if possible, or the default
        // otherwise:
        $recommend = [];

        if (
            null !== $handler
            && isset($searchSettings['TopRecommendations'][$handler])
        ) {
            $recommend['top'] = $searchSettings['TopRecommendations'][$handler];
        } else {
            $recommend['top'] = $searchSettings['General']['default_top_recommend'] ?? false;
        }
        if (
            null !== $handler
            && isset($searchSettings['SideRecommendations'][$handler])
        ) {
            $recommend['side'] = $searchSettings['SideRecommendations'][$handler];
        } else {
            $recommend['side'] = $searchSettings['General']['default_side_recommend'] ?? false;
        }
        if (
            null !== $handler
            && isset($searchSettings['NoResultsRecommendations'][$handler])
        ) {
            $recommend['noresults'] = $searchSettings['NoResultsRecommendations'][$handler];
        } else {
            $recommend['noresults'] = $searchSettings['General']['default_noresults_recommend'] ?? false;
        }

        return $recommend;
    }

    /**
     * Get the identifier used for naming the various search classes in this family.
     *
     * @return string
     */
    public function getSearchClassId(): string
    {
        // Parse identifier out of class name of format VuFind\Search\[id]\Options:
        $className = get_class($this);
        $class = explode('\\', $className);

        // Special case: if there's an unexpected number of parts, we may be testing
        // with a mock object; if so, that's okay, but anything else is unexpected.
        if (count($class) !== 4) {
            if ($this instanceof MockObject) {
                return 'Mock';
            }
            throw new \Exception("Unexpected class name: {$className}");
        }

        return $class[2];
    }

    /**
     * Get the search class ID for identifying search box options; this is normally
     * the same as the current search class ID, but some "special purpose" search
     * namespaces (e.g. SolrAuthor) need to point to a different ID for search box
     * generation
     *
     * @return string
     */
    public function getSearchBoxSearchClassId(): string
    {
        return $this->getSearchClassId();
    }

    /**
     * Is first/last navigation supported by the backend
     *
     * @return bool
     */
    public function firstLastNavigationSupported(): bool
    {
        return $this->firstLastNavigationSupported;
    }

    /**
     * Should we include first/last options in record page navigation?
     *
     * @return bool
     */
    public function recordFirstLastNavigationEnabled(): bool
    {
        return $this->firstLastNavigationSupported() && $this->recordPageFirstLastNavigation;
    }

    /**
     * Does this search backend support scheduled searching?
     *
     * @return bool
     */
    public function supportsScheduledSearch(): bool
    {
        // Unsupported by default!
        return false;
    }

    /**
     * Should we load results with JavaScript?
     *
     * @return bool
     */
    public function loadResultsWithJsEnabled(): bool
    {
        return $this->loadResultsWithJs;
    }

    /**
     * Override the setting for loading results with JavaScript.
     *
     * @param bool $enable Enable JS?
     *
     * @return void
     */
    public function setLoadResultsWithJs(bool $enable): void
    {
        $this->loadResultsWithJs = $enable;
    }

    /**
     * Get top paginator style
     *
     * @return string
     */
    public function getTopPaginatorStyle(): string
    {
        return $this->topPaginatorStyle;
    }

    /**
     * Return the callback used for normalization within this backend.
     *
     * @return callable
     */
    public function getSpellingNormalizer(): callable
    {
        return new \VuFind\Normalizer\DefaultSpellingNormalizer();
    }

    /**
     * Should we display citation search links in results?
     *
     * @return bool
     */
    public function displayCitationLinksInResults(): bool
    {
        return $this->displayCitationLinksInResults;
    }

    /**
     * Get advanced search limits that override the natural sorting to
     * display at the top.
     *
     * @param string $limit advanced search limit
     *
     * @return array
     */
    public function limitOrderOverride(string $limit): array
    {
        $limits = $this->facetSettings['Advanced_Settings']['limitOrderOverride'] ?? [];
        $delimiter = $this->facetSettings['Advanced_Settings']['limitDelimiter'] ?? '::';
        $limitConf = $limits[$limit] ?? '';
        return array_map('trim', explode($delimiter, $limitConf));
    }

    /**
     * Are hierarchicalFacetFilters and hierarchicalExcludeFilters enabled in advanced search?
     *
     * @return bool
     */
    public function getFilterHierarchicalFacetsInAdvanced(): bool
    {
        return $this->filterHierarchicalFacetsInAdvanced;
    }

    /**
     * Get hierarchical exclude filters.
     *
     * @param string|null $field Field to get or null for all values.
     *                           Default is null.
     *
     * @return array
     */
    public function getHierarchicalExcludeFilters(?string $field = null): array
    {
        if ($field) {
            return $this->hierarchicalExcludeFilters[$field] ?? [];
        }
        return $this->hierarchicalExcludeFilters;
    }

    /**
     * Get hierarchical facet filters.
     *
     * @param string|null $field Field to get or null for all values.
     *                           Default is null.
     *
     * @return array
     */
    public function getHierarchicalFacetFilters(?string $field = null): array
    {
        if ($field) {
            return $this->hierarchicalFacetFilters[$field] ?? [];
        }
        return $this->hierarchicalFacetFilters;
    }

    /**
     * Should we display a warning in restricted views?
     *
     * @return bool
     */
    public function showRestrictedViewWarning(): bool
    {
        return $this->showRestrictedViewWarning;
    }

    /**
     * Get minimum value for date range sliders.
     *
     * @param string $field Field name
     *
     * @return ?int
     */
    public function getDateRangeSliderMinValue(string $field): ?int
    {
        return $this->parseDateRangeSliderSetting($this->facetSettings["Facet_$field"]['slider_min_value'] ?? '');
    }

    /**
     * Get maximum value for date range sliders.
     *
     * @param string $field Field name
     *
     * @return ?int
     */
    public function getDateRangeSliderMaxValue(string $field): ?int
    {
        return $this->parseDateRangeSliderSetting($this->facetSettings["Facet_$field"]['slider_max_value'] ?? '');
    }

    /**
     * Configure autocomplete preferences from an .ini file.
     *
     * @param ?array $searchSettings Object representation of .ini file
     *
     * @return void
     */
    protected function configureAutocomplete(?array $searchSettings = null): void
    {
        // Only change settings from current values if they are defined in .ini:
        $autocompleteSettings = $searchSettings['Autocomplete'] ?? [];
        if (null !== ($enabled = $autocompleteSettings['enabled'] ?? null)) {
            $this->autocompleteEnabled = $enabled;
        }
        if (null !== ($autosubmit = $autocompleteSettings['auto_submit'] ?? null)) {
            $this->autocompleteAutoSubmit = $autosubmit;
        }
        $this->autocompleteApplyActiveFilters = $autocompleteSettings['apply_active_filters'] ?? null;
        if (null !== ($displaylimit = $autocompleteSettings['display_limit'] ?? null)) {
            $this->autocompleteDisplayLimit = (int)$displaylimit;
        }
        $formattingRules = $autocompleteSettings['formatting_rule'] ?? [];
        if ($formattingRules && is_array($formattingRules)) {
            $this->autocompleteFormattingRules = $formattingRules;
        }
    }

    /**
     * Initialize hidden sort options by combining the settings into a single array
     *
     * @return void
     */
    protected function initializeHiddenSortOptions(): void
    {
        $this->hiddenSortOptions = [];
        $hiddenSortOptions = (array)($this->searchSettings['HiddenSorting']['pattern'] ?? []);
        $hiddenSortOptionLabels = (array)($this->searchSettings['HiddenSorting']['label'] ?? []);
        foreach ($hiddenSortOptions as $key => $pattern) {
            $label = (string)($hiddenSortOptionLabels[$key] ?? $key);
            $this->hiddenSortOptions[] = [
                'label' => ctype_digit($label) ? null : $label,
                'pattern' => $pattern,
            ];
        }
    }

    /**
     * Parse a date range slider value setting.
     *
     * @param string $setting Setting to parse
     *
     * @return ?int
     */
    protected function parseDateRangeSliderSetting(string $setting): ?int
    {
        if ('' === $setting) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $setting)) {
            return (int)$setting;
        }
        if (false !== ($time = strtotime($setting))) {
            return date('Y', $time);
        }
        return null;
    }
}
