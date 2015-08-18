<?php
/**
 * Summon Search Parameters
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Summon;
use SerialsSolutions_Summon_Query as SummonQuery,
    VuFind\Solr\Utils as SolrUtils,
    VuFindSearch\ParamBag;

/**
 * Summon Search Parameters
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
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
        return parent::addFacet($parts[0], $newAlias, $ored);
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
     * @param string $field Facet field name.
     *
     * @return string       Human-readable description of field.
     */
    public function getFacetLabel($field)
    {
        // The default use of "Other" for undefined facets doesn't work well with
        // checkbox facets -- we'll use field names as the default within the Summon
        // search object.
        return isset($this->facetConfig[$field])
            ? $this->facetConfig[$field] : $field;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @return array
     */
    public function getCheckboxFacets()
    {
        // Grab checkbox facet details using the standard method:
        $facets = parent::getCheckboxFacets();

        // Special case -- if we have a "holdings only" or "expand query" facet,
        // we want this to always appear, even on the "no results" screen, since
        // setting this facet actually EXPANDS rather than reduces the result set.
        if (isset($facets['holdingsOnly'])) {
            $facets['holdingsOnly']['alwaysVisible'] = true;
        }
        if (isset($facets['queryExpansion'])) {
            $facets['queryExpansion']['alwaysVisible'] = true;
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
            if ($sort == 'relevance' && $this->getQuery()->getAllTerms() == ''
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
        $lang = $this->getServiceLocator()->get('VuFind\Translator')->getLocale();
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('Summon');
        $defaultFacetLimit = isset($config->Facet_Settings->facet_limit)
            ? $config->Facet_Settings->facet_limit : 30;
        $fieldSpecificLimits = isset($config->Facet_Settings->facet_limit_by_field)
            ? $config->Facet_Settings->facet_limit_by_field : null;

        $finalFacets = [];
        foreach ($this->getFullFacetSettings() as $facet) {
            // See if parameters are included as part of the facet name;
            // if not, override them with defaults.
            $parts = explode(',', $facet);
            $facetName = $parts[0];
            $bestDefaultFacetLimit = isset($fieldSpecificLimits->$facetName)
                ? $fieldSpecificLimits->$facetName : $defaultFacetLimit;
            $defaultMode = ($this->getFacetOperator($facet) == 'OR') ? 'or' : 'and';
            $facetMode = isset($parts[1]) ? $parts[1] : $defaultMode;
            $facetPage = isset($parts[2]) ? $parts[2] : 1;
            $facetLimit = isset($parts[3]) ? $parts[3] : $bestDefaultFacetLimit;
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
                            'holdings', strtolower(trim($safeValue)) == 'true'
                        );
                    } else if ($filt['field'] == 'queryExpansion') {
                        // Special case -- "query expansion" is a separate parameter
                        // from other facets.
                        $params->set(
                            'expand', strtolower(trim($safeValue)) == 'true'
                        );
                    } else if ($filt['field'] == 'excludeNewspapers') {
                        // Special case -- support a checkbox for excluding
                        // newspapers:
                        $params
                            ->add('filters', "ContentType,Newspaper Article,true");
                    } else if ($range = SolrUtils::parseRange($filt['value'])) {
                        // Special case -- range query (translate [x TO y] syntax):
                        $from = SummonQuery::escapeParam($range['from']);
                        $to = SummonQuery::escapeParam($range['to']);
                        $params
                            ->add('rangeFilters', "{$filt['field']},{$from}:{$to}");
                    } else if ($filt['operator'] == 'OR') {
                        // Special case -- OR facets:
                        $orFacets[$filt['field']] = isset($orFacets[$filt['field']])
                            ? $orFacets[$filt['field']] : [];
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
                        'groupFilters', $field . ',or,' . implode(',', $values)
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
            $field, $value, $operator, $translate
        );

        // Convert range queries to a language-non-specific format:
        $caseInsensitiveRegex = '/^\(\[(.*) TO (.*)\] OR \[(.*) TO (.*)\]\)$/';
        if (preg_match('/^\[(.*) TO (.*)\]$/', $value, $matches)) {
            // Simple case: [X TO Y]
            $filter['displayText'] = $matches[1] . '-' . $matches[2];
        } else if (preg_match($caseInsensitiveRegex, $value, $matches)) {
            // Case insensitive case: [x TO y] OR [X TO Y]; convert
            // only if values in both ranges match up!
            if (strtolower($matches[3]) == strtolower($matches[1])
                && strtolower($matches[4]) == strtolower($matches[2])
            ) {
                $filter['displayText'] = $matches[1] . '-' . $matches[2];
            }
        }

        return $filter;
    }

    /**
     * Load all available facet settings.  This is mainly useful for showing
     * appropriate labels when an existing search has multiple filters associated
     * with it.
     *
     * @param string $preferredSection Section to favor when loading settings; if
     * multiple sections contain the same facet, this section's description will
     * be favored.
     *
     * @return void
     */
    public function activateAllFacets($preferredSection = false)
    {
        $this->initFacetList('Facets', 'Results_Settings', 'Summon');
        $this->initFacetList('Advanced_Facets', 'Advanced_Facet_Settings', 'Summon');
    }
}