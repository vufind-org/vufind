<?php
/**
 * Solr Autocomplete Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Autocomplete
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
namespace Finna\Autocomplete;

/**
 * Solr Autocomplete Module
 *
 * This class provides suggestions by using the local Solr index.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
class Solr extends \VuFind\Autocomplete\Solr
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Autocomplete faceting settings
     *
     * @var array
     */
    protected $facetSettings;

    /**
     * Facet configuration
     *
     * @var \Zend\Config\Config
     */
    protected $facetConfig;

    /**
     * Hierarchical facets
     *
     * @var array
     */
    protected $hierarchicalFacets;

    /**
     * Search configuration
     *
     * @var \Zend\Config\Config
     */
    protected $searchConfig;

    /**
     * Facet translations
     *
     * @var array
     */
    protected $facetTranslations;

    /**
     * Is faceting disabled?
     *
     * @var boolean
     */
    protected $facetingDisabled = false;

    /**
     * Current search tab
     *
     * @var string
     */
    protected $searchTab = null;

    /**
     * Constructor
     *
     * @param PluginManager       $results      Results plugin manager
     * @param \Zend\Config\Config $facetConfig  Facet configuration
     * @param \Zend\Config\Config $searchConfig Search configuration
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results,
        $facetConfig, $searchConfig
    ) {
        $settings = [];
        $facets = isset($searchConfig->Autocomplete_Sections->facets)
            ? $searchConfig->Autocomplete_Sections->facets->toArray() : null;

        $this->hierarchicalFacets
            = isset($facetConfig->SpecialFacets->hierarchical)
            ? $facetConfig->SpecialFacets->hierarchical->toArray() : [];

        $pos = 0;
        foreach ($facets as $data) {
            $data = explode('|', $data);
            $field = $data[0];
            $filter = !empty($data[1]) ? ['pattern' => $data[1]] : null;
            $limit = isset($data[2]) && !empty($data[2]) ? $data[2] : null;
            $tabs = isset($data[3]) ? explode('&', $data[3]) : null;
            // Restrict hierarchical facet values to top-level if
            // no other filters are defined
            if (!$filter && in_array($field, $this->hierarchicalFacets)) {
                $filter = ['regex' => true, 'pattern' => '^0/*.'];
            }
            $settings[$field][] = [
                'pos' => $pos++, 'limit' => $limit,
                'filter' => $filter, 'tabs' => $tabs
            ];
        }

        $this->facetConfig = $facetConfig;
        $this->searchConfig = $searchConfig;
        $this->facetSettings = $settings;
        $this->facetTranslations = $facetConfig->Results->toArray();
        foreach ($facetConfig->CheckboxFacets->toArray() as $field => $val) {
            list($field,) = explode(':', $field);
            $this->facetTranslations[$field] = $val;
        }

        parent::__construct($results);
    }

    /**
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestions($query)
    {
        $params = $this->searchObject->getParams();
        $this->searchObject->getOptions()->disableHighlighting();

        if (!$this->facetingDisabled) {
            foreach ($this->facetSettings as $field => $facets) {
                foreach ($facets as $key => $facet) {
                    if (!empty($facet['tabs'])
                        && (!$this->searchTab
                        || !in_array($this->searchTab, $facet['tabs']))
                    ) {
                        unset($this->facetSettings[$field][$key]);
                    }
                }
            }
            $facetLimit = 20;
            $params->setFacetLimit($facetLimit);
            $allFacets = array_keys($this->facetSettings);
            $facets = array_diff($allFacets, $this->hierarchicalFacets);
            foreach ($facets as $facet) {
                $params->addFacet($facet);
            }
        }

        $suggestionsLimit = isset($this->searchConfig->Autocomplete->suggestions)
            ? $this->searchConfig->Autocomplete->suggestions : 5;

        $suggestions = parent::getSuggestions($query);
        if (!empty($suggestions)) {
            $suggestions = array_splice($suggestions, 0, $suggestionsLimit);
        }

        $facets = [];
        if (!$this->facetingDisabled) {
            $getFacetValues = function ($facet) {
                return [$facet['value'], $facet['count']];
            };
            $facetResults = [];

            // Facets
            foreach ($this->searchObject->getFacetList() as $field => $data) {
                $filtered = $this->filterFacetValues($field, $data['list']);
                foreach ($filtered as $data) {
                    $values
                        = $this->extractFacetData($field, $data['values']);
                    $facetResults[$data['pos']] = $values;
                }
            }

            // Hierarchical facets
            $this->initSearchObject();
            $this->searchObject->getOptions()->disableHighlighting();
            $this->searchObject->getParams()->setBasicSearch(
                $this->mungeQuery($query), $this->handler
            );
            $this->searchObject->getParams()->setSort($this->sortField);
            foreach ($this->filters as $current) {
                $this->searchObject->getParams()->addFilter($current);
            }
            foreach ($this->hierarchicalFacets as $facet) {
                $params->addFacet($facet, null, false);
            }
            $hierachicalFacets = $this->searchObject->getFullFieldFacets(
                array_intersect($this->hierarchicalFacets, $allFacets),
                false, -1, 'count'
            );
            foreach ($hierachicalFacets as $field => $data) {
                $filtered = $this->filterFacetValues($field, $data['data']['list']);
                foreach ($filtered as $data) {
                    $values = $this->extractFacetData(
                        $field, $data['values'], true
                    );
                    $facetResults[$data['pos']] = $values;
                }
            }
            $facets = $facetResults;
        }

        ksort($facets);
        $facets = array_values($facets);

        $result = compact('suggestions', 'facets');
        return $result;
    }

    /**
     * Disable faceting.
     * @return void
     */
    public function disableFaceting()
    {
        $this->facetingDisabled = true;
    }

    /**
     * Set current search tab.
     *
     * @param string $tab Search tab.
     *
     * @return void
     */
    public function setSearchTab($tab)
    {
        $this->searchTab = $tab;
    }

    /**
     * Filter and limit facet values.
     *
     * @param string $field  Facet field.
     * @param array  $values Facet values.
     *
     * @return array Filtered values
     */
    protected function filterFacetValues($field, $values)
    {
        $result = [];
        foreach ($this->facetSettings[$field] as $facet) {
            $filtered = [];
            if (!empty($facet['filter'])) {
                $filter = $facet['filter'];
                foreach ($values as $value) {
                    $pattern = $filter['pattern'];
                    $facetValue = $value['value'];
                    $match = false;
                    if (isset($filter['regex'])) {
                        $pattern = '/' . addcslashes($pattern, '/') . '/';
                        $match = preg_match($pattern, $facetValue) === 1;
                    } else {
                        $match = $facetValue === $pattern;
                    }
                    if ($match) {
                        $pos = $facet['pos'];
                        $filtered[] = $value;
                    }
                }
            } else {
                $pos = $facet['pos'];
                $filtered = $values;
            }
            if (isset($facet['limit'])) {
                $filtered = array_splice($filtered, 0, $facet['limit']);
            }
            if (!empty($filtered)) {
                $result[] = ['pos' => $pos, 'values' => $filtered];
            }
        }
        return $result;
    }

    /**
     * Collect facet data for output.
     *
     * @param string  $facet             Facet field.
     * @param array   $values            Facet values.
     * @param boolean $hierarchicalFacet Is this a hierarchical facet?
     *
     * @return array Filtered values
     */
    protected function extractFacetData(
        $facet, $values, $hierarchicalFacet = false
    ) {
        $fn = function ($value) use (
            $facet, $hierarchicalFacet
        ) {
            $label = $value['value'];
            $key = "autocomplete_$facet:$label";
            $translated = $this->translator->translate($key);
            if ($key !== $translated) {
                $label = $translated;
            } else {
                $label = $hierarchicalFacet
                ? $value['displayText']
                : $this->translator->translate($label);
            }

            $facetTabel = $this->translator->translate(
                $this->facetTranslations[$facet]
            );

            $count = $value['count'];
            $value = $value['value'];
            if (is_bool($value)) {
                $value = (int)$value;
                $label = $facetTabel;
            } else if (in_array($value, ['true', 'false'])) {
                $value = $value === 'true' ? '1' : '0';
                $label = $facetTabel;
            }

            $data = [$label, $count];
            $data[] = $facet . ':' . $value;
            return $data;
        };
        return array_map($fn, $values);
    }

    /**
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query Incoming user query
     *
     * @return string       Processed query
     */
    protected function mungeQuery($query)
    {
        return $query;
    }
}
