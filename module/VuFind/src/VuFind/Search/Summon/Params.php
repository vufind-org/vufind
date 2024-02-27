<?php

/**
 * Summon Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Summon;

use SerialsSolutions_Summon_Query as SummonQuery;
use VuFind\Solr\Utils as SolrUtils;
use VuFindSearch\ParamBag;

/**
 * Summon Search Parameters
 *
 * @category VuFind
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    use \VuFind\Search\Params\FacetLimitTrait;

    /**
     * Settings for all the facets
     *
     * @var array
     */
    protected $fullFacetSettings = [];

    /**
     * Settings for the date facet only
     *
     * @var array
     */
    protected $dateFacetSettings = [];

    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections
        = ['Advanced_Facets', 'HomePage_Facets', 'FacetsTop', 'Facets'];

    /**
     * Config sections to search for checkbox facet labels if no override
     * configuration is set.
     *
     * @var array
     */
    protected $defaultFacetLabelCheckboxSections = ['CheckboxFacets'];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($options, $configLoader);
        $config = $configLoader->get($options->getFacetsIni());
        $this->initFacetLimitsFromConfig($config->Facet_Settings ?? null);
    }

    /**
     * Add a field to facet on.
     *
     * @param string $newField Field name
     * @param string $newAlias Optional on-screen display label
     * @param bool   $ored     Should we treat this as an ORed facet?
     *
     * @return void
     */
    public function addFacet($newField, $newAlias = null, $ored = false)
    {
        // Save the full field name (which may include extra parameters);
        // we'll need these to do the proper search using the Summon class:
        if (strstr($newField, 'PublicationDate')) {
            // Special case -- we don't need to send this to the Summon API,
            // but we do need to set a flag so VuFind knows to display the
            // date facet control.
            $this->dateFacetSettings[] = 'PublicationDate';
        } else {
            $this->fullFacetSettings[] = $newField;
        }

        // Field name may have parameters attached -- remove them:
        $parts = explode(',', $newField);
        parent::addFacet($parts[0], $newAlias, $ored);
    }

    /**
     * Reset the current facet configuration.
     *
     * @return void
     */
    public function resetFacetConfig()
    {
        parent::resetFacetConfig();
        $this->dateFacetSettings = [];
        $this->fullFacetSettings = [];
    }

    /**
     * Get the full facet settings stored by addFacet -- these may include extra
     * parameters needed by the search results class.
     *
     * @return array
     */
    public function getFullFacetSettings()
    {
        return $this->fullFacetSettings;
    }

    /**
     * Get the date facet settings stored by addFacet.
     *
     * @return array
     */
    public function getDateFacetSettings()
    {
        return $this->dateFacetSettings;
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field   Facet field name.
     * @param string $value   Facet value.
     * @param string $default Default field name (null for default behavior).
     *
     * @return string         Human-readable description of field.
     */
    public function getFacetLabel($field, $value = null, $default = null)
    {
        // The default use of "Other" for undefined facets doesn't work well with
        // checkbox facets -- we'll use field names as the default within the Summon
        // search object.
        return parent::getFacetLabel($field, $value, $default ?: $field);
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @param array $include        List of checkbox filters to return (null for all)
     * @param bool  $includeDynamic Should we include dynamically-generated
     * checkboxes that are not part of the include list above?
     *
     * @return array
     */
    public function getCheckboxFacets(
        array $include = null,
        bool $includeDynamic = true
    ) {
        // Grab checkbox facet details using the standard method:
        $facets = parent::getCheckboxFacets($include, $includeDynamic);

        // Special case -- if we have a "holdings only" or "expand query" facet,
        // we want this to always appear, even on the "no results" screen, since
        // setting this facet actually EXPANDS rather than reduces the result set.
        foreach ($facets as $i => $facet) {
            [$field] = explode(':', $facet['filter']);
            if ($field == 'holdingsOnly' || $field == 'queryExpansion') {
                $facets[$i]['alwaysVisible'] = true;
            }
        }

        // Return modified list:
        return $facets;
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        $options = $this->getOptions();

        $sort = $this->getSort();
        if ($sort) {
            // If we have an empty search with relevance sort, see if there is
            // an override configured:
            if (
                $sort == 'relevance' && $this->getQuery()->getAllTerms() == ''
                && ($relOv = $this->getOptions()->getEmptySearchRelevanceOverride())
            ) {
                $sort = $relOv;
            }
        }

        // The "relevance" sort option is a VuFind reserved word; we need to make
        // this null in order to achieve the desired effect with Summon:
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);

        $backendParams->set('didYouMean', $options->spellcheckEnabled());

        // Get the language setting:
        $lang = $this->getOptions()->getTranslatorLocale();
        $backendParams->set('language', substr($lang, 0, 2));

        if ($options->highlightEnabled()) {
            $backendParams->set('highlight', true);
            $backendParams->set('highlightStart', '{{{{START_HILITE}}}}');
            $backendParams->set('highlightEnd', '{{{{END_HILITE}}}}');
        }
        if ($maxTopics = $options->getMaxTopicRecommendations()) {
            $backendParams->set('maxTopics', $maxTopics);
        }
        $backendParams->set('facets', $this->getBackendFacetParameters());
        $this->createBackendFilterParameters($backendParams);

        return $backendParams;
    }

    /**
     * Set up facets based on VuFind settings.
     *
     * @return array
     */
    protected function getBackendFacetParameters()
    {
        $finalFacets = [];
        foreach ($this->getFullFacetSettings() as $facet) {
            // See if parameters are included as part of the facet name;
            // if not, override them with defaults.
            $parts = explode(',', $facet);
            $facetName = $parts[0];
            $defaultMode = ($this->getFacetOperator($facet) == 'OR') ? 'or' : 'and';
            $facetMode = $parts[1] ?? $defaultMode;
            $facetPage = $parts[2] ?? 1;
            $facetLimit = $parts[3] ?? $this->getFacetLimitForField($facetName);
            $facetParams = "{$facetMode},{$facetPage},{$facetLimit}";
            $finalFacets[] = "{$facetName},{$facetParams}";
        }
        return $finalFacets;
    }

    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params Parameter collection to update
     *
     * @return void
     */
    public function createBackendFilterParameters(ParamBag $params)
    {
        // Which filters should be applied to our query?
        $filterList = $this->getFilterList();
        if (!empty($filterList)) {
            $orFacets = [];

            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $safeValue = SummonQuery::escapeParam($filt['value']);
                    // Special case -- "holdings only" is a separate parameter from
                    // other facets.
                    if ($filt['field'] == 'holdingsOnly') {
                        $params->set(
                            'holdings',
                            strtolower(trim($safeValue)) == 'true'
                        );
                    } elseif ($filt['field'] == 'queryExpansion') {
                        // Special case -- "query expansion" is a separate parameter
                        // from other facets.
                        $params->set(
                            'expand',
                            strtolower(trim($safeValue)) == 'true'
                        );
                    } elseif ($filt['field'] == 'openAccessFilter') {
                        // Special case -- "open access filter" is a separate
                        // parameter from other facets.
                        $params->set(
                            'openAccessFilter',
                            strtolower(trim($safeValue)) == 'true'
                        );
                    } elseif ($filt['field'] == 'excludeNewspapers') {
                        // Special case -- support a checkbox for excluding
                        // newspapers:
                        $params
                            ->add('filters', 'ContentType,Newspaper Article,true');
                    } elseif ($range = SolrUtils::parseRange($filt['value'])) {
                        // Special case -- range query (translate [x TO y] syntax):
                        $from = SummonQuery::escapeParam($range['from']);
                        $to = SummonQuery::escapeParam($range['to']);
                        $params
                            ->add('rangeFilters', "{$filt['field']},{$from}:{$to}");
                    } elseif ($filt['operator'] == 'OR') {
                        // Special case -- OR facets:
                        $orFacets[$filt['field']] ??= [];
                        $orFacets[$filt['field']][] = $safeValue;
                    } else {
                        // Standard case:
                        $fq = "{$filt['field']},{$safeValue}";
                        if ($filt['operator'] == 'NOT') {
                            $fq .= ',true';
                        }
                        $params->add('filters', $fq);
                    }
                }

                // Deal with OR facets:
                foreach ($orFacets as $field => $values) {
                    $params->add(
                        'groupFilters',
                        $field . ',or,' . implode(',', $values)
                    );
                }
            }
        }
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        $filter = parent::formatFilterListEntry(
            $field,
            $value,
            $operator,
            $translate
        );

        // Convert range queries to a language-non-specific format:
        $caseInsensitiveRegex = '/^\(\[(.*) TO (.*)\] OR \[(.*) TO (.*)\]\)$/';
        if (preg_match('/^\[(.*) TO (.*)\]$/', $value, $matches)) {
            // Simple case: [X TO Y]
            $filter['displayText'] = $matches[1] . '-' . $matches[2];
        } elseif (preg_match($caseInsensitiveRegex, $value, $matches)) {
            // Case insensitive case: [x TO y] OR [X TO Y]; convert
            // only if values in both ranges match up!
            if (
                strtolower($matches[3]) == strtolower($matches[1])
                && strtolower($matches[4]) == strtolower($matches[2])
            ) {
                $filter['displayText'] = $matches[1] . '-' . $matches[2];
            }
        }

        return $filter;
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     *
     * @param string $facetList     Config section containing fields to activate
     * @param string $facetSettings Config section containing related settings
     * @param string $cfgFile       Name of configuration to load (null to load
     * default facets configuration).
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings, $cfgFile = null)
    {
        $config = $this->configLoader
            ->get($cfgFile ?? $this->getOptions()->getFacetsIni());
        // Special case -- when most settings are in Results_Settings, the limits
        // can be found in Facet_Settings.
        $limitSection = ($facetSettings === 'Results_Settings')
            ? 'Facet_Settings' : $facetSettings;
        $this->initFacetLimitsFromConfig($config->$limitSection ?? null);
        return parent::initFacetList($facetList, $facetSettings, $cfgFile);
    }

    /**
     * Initialize facet settings for the advanced search screen.
     *
     * @return void
     */
    public function initAdvancedFacets()
    {
        // If no configuration was found, set up defaults instead:
        if (!$this->initFacetList('Advanced_Facets', 'Advanced_Facet_Settings')) {
            $defaults = ['Language' => 'Language', 'ContentType' => 'Format'];
            foreach ($defaults as $key => $value) {
                $this->addFacet($key, $value);
            }
        }
    }

    /**
     * Initialize facet settings for the home page.
     *
     * @return void
     */
    public function initHomePageFacets()
    {
        // Load Advanced settings if HomePage settings are missing (legacy support):
        if (!$this->initFacetList('HomePage_Facets', 'HomePage_Facet_Settings')) {
            $this->initAdvancedFacets();
        }
    }
}
