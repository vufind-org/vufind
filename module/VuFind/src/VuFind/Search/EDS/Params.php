<?php

/**
 * EDS API Params
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

use VuFindSearch\ParamBag;

use function count;

/**
 * EDS API Params
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends AbstractEDSParams
{
    /**
     * Fields that the EDS API will always filter multiple values using OR, not AND.
     *
     * @var array
     */
    protected $forcedOrFields = [
        'ContentProvider',
        'SourceType',
    ];

    /**
     * Settings for the date facet only
     *
     * @var array
     */
    protected $dateFacetSettings = [];

    /**
     * Additional filters to display as side facets
     *
     * @var array
     */
    protected $extraFilterList = [];

    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections
        = ['FacetsTop', 'Facets'];

    /**
     * Config sections to search for checkbox facet labels if no override
     * configuration is set.
     *
     * @var array
     */
    protected $defaultFacetLabelCheckboxSections = ['CheckboxFacets'];

    /**
     * Facet settings
     *
     * @var array
     */
    protected $fullFacetSettings = [];

    /**
     * A flag indicating whether limiters and expanders have been added to the
     * checkbox facets. Used to defer adding them (and accessing the API) until
     * necessary.
     *
     * @var bool
     */
    protected $checkboxFacetsAugmented = false;

    /**
     * Default query adapter class (override to use EDS version)
     *
     * @var string
     */
    protected $queryAdapterClass = QueryAdapter::class;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($options, $configLoader);
    }

    /**
     * Pull the search parameters
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        parent::initFromRequest($request);

        //make sure that the searchmode parameter is set
        $searchmode = $request->get('searchmode');
        if (isset($searchmode)) {
            $this->getOptions()->setSearchMode($searchmode);
        } else {
            //get default search mode and set as a hidden filter
            $defaultSearchMode = $this->getOptions()->getDefaultMode();
            $this->getOptions()->setSearchMode($defaultSearchMode);
        }
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

        // The "relevance" sort option is a VuFind reserved word; we need to make
        // this null in order to achieve the desired effect with EDS:
        $sort = $this->getSort();
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);

        if ($options->highlightEnabled()) {
            $backendParams->set('highlight', true);
        }

        $view = $this->getEdsView();
        $backendParams->set('view', $view);

        $mode = $options->getSearchMode();
        if (isset($mode)) {
            $backendParams->set('searchMode', $mode);
        }

        $this->createBackendFilterParameters($backendParams);

        return $backendParams;
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getEdsView()
    {
        $viewArr = explode('|', $this->view ?? '');
        return (1 < count($viewArr)) ? $viewArr[1] : $this->options->getEdsView();
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
        // we'll need these to do the proper search using the EDS class:
        if (strstr($newField, 'PublicationDate')) {
            // Special case -- we don't need to send this to the EDS API,
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
        // Also store Limiter/Search Mode IDs/Values in the config file
        if (str_starts_with($field, 'LIMIT|')) {
            $facetId = substr($field, 6);
        } elseif (str_starts_with($field, 'SEARCHMODE|')) {
            $facetId = substr($field, 11);
        } else {
            $facetId = $field;
        }
        return parent::getFacetLabel($facetId, $value, $default ?: $facetId);
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
     * Populate common limiters as checkbox facets
     *
     * @param Options $options Options
     *
     * @return void
     */
    public function addLimitersAsCheckboxFacets(Options $options)
    {
        $ssLimiters = $options->getSearchScreenLimiters();
        foreach ($ssLimiters as $ssLimiter) {
            $this->addCheckboxFacet(
                $ssLimiter['selectedvalue'],
                $ssLimiter['description'],
                true
            );
        }
    }

    /**
     * Populate expanders as checkbox facets
     *
     * @param Options $options Options
     *
     * @return void
     */
    public function addExpandersAsCheckboxFacets(Options $options)
    {
        $availableExpanders = $options->getSearchScreenExpanders();
        foreach ($availableExpanders as $expander) {
            $this->addCheckboxFacet(
                $expander['selectedvalue'],
                $expander['description'],
                true
            );
        }
    }

    /**
     * Basic 'getter' for list of available view options.
     *
     * @return array
     */
    public function getViewList()
    {
        $list = [];
        foreach ($this->getOptions()->getViewOptions() as $key => $value) {
            $list[$key] = [
                'desc' => $value,
                'selected' => ($key == $this->getView() . '|' . $this->getEdsView()),
            ];
        }
        return $list;
    }

    /**
     * Override for build a string for onscreen display showing the
     *   query used in the search. It will include field level operators instead
     *   of group operators (Since EDS only uses one group.)
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        // Set up callbacks:
        $translate = [$this, 'translate'];
        $showField = [$this->getOptions(), 'getHumanReadableFieldName'];

        // Build display query:
        return $this->getQueryAdapter()->display($this->getQuery(), $translate, $showField);
    }

    /**
     * Return checkbox facets without any processing
     *
     * @return array
     */
    protected function getRawCheckboxFacets(): array
    {
        $this->augmentCheckboxFacets();
        return parent::getRawCheckboxFacets();
    }

    /**
     * Augment checkbox facets with limiters and expanders retrieved from the API
     * info
     *
     * @return void
     */
    protected function augmentCheckboxFacets(): void
    {
        if (!$this->checkboxFacetsAugmented) {
            $this->addLimitersAsCheckboxFacets($this->getOptions());
            $this->addExpandersAsCheckboxFacets($this->getOptions());
            $this->checkboxFacetsAugmented = true;
        }
    }
}
