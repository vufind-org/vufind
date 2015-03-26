<?php
/**
 * EDS API Params
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
use VuFindSearch\ParamBag;
use VuFindSearch\Backend\EDS\SearchRequestModel as SearchRequestModel;

/**
 * EDS API Params
 *
 * @category VuFind2
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
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
     * Is the request using this parameters objects for setup only?
     *
     * @var bool
     */
    public $isSetupOnly = false;

    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
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
        // this null in order to achieve the desired effect with Summon:
        $sort = $this->getSort();
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);

        if ($options->highlightEnabled()) {
            $backendParams->set('highlight', true);
        }

        $view = $this->getEdsView();
        if (isset($view)) {
            $backendParams->set('view', $view);
        }

        $mode = $options->getSearchMode();
        if (isset($mode)) {
            $backendParams->set('searchMode', $mode);
        }

        //process the setup only parameter
        if (true == $this->isSetupOnly) {
            $backendParams->set('setuponly', $this->isSetupOnly);
        }
        $this->createBackendFilterParameters($backendParams, $options);

        return $backendParams;
    }

    /**
     * Set up facets based on VuFind settings.
     *
     * @return array
     */
    protected function getBackendFacetParameters()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        $defaultFacetLimit = isset($config->Facet_Settings->facet_limit)
            ? $config->Facet_Settings->facet_limit : 30;

        $finalFacets = [];
        foreach ($this->getFullFacetSettings() as $facet) {
            // See if parameters are included as part of the facet name;
            // if not, override them with defaults.
            $parts = explode(',', $facet);
            $facetName = $parts[0];
            $defaultMode = ($this->getFacetOperator($facet) == 'OR') ? 'or' : 'and';
            $facetMode = isset($parts[1]) ? $parts[1] : $defaultMode;
            $facetPage = isset($parts[2]) ? $parts[2] : 1;
            $facetLimit = isset($parts[3]) ? $parts[3] : $defaultFacetLimit;
            $facetParams = "{$facetMode},{$facetPage},{$facetLimit}";
            $finalFacets[] = "{$facetName},{$facetParams}";
        }
        return $finalFacets;
    }

    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params  Parameter collection to update
     * @param Options  $options Options from which to add extra filter parameters
     *
     * @return void
     */
    public function createBackendFilterParameters(ParamBag $params, Options $options)
    {
        // Which filters should be applied to our query?
        $filterList = $this->getFilterList();
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $safeValue = SearchRequestModel::escapeSpecialCharacters(
                        $filt['value']
                    );
                    // Standard case:
                    $fq = "{$filt['field']}:{$safeValue}";
                    $params->add('filters', $fq);
                }
            }
        }
        $this->addLimitersAsCheckboxFacets($options);
        $this->addExpandersAsCheckboxFacets($options);
    }

    /**
     * Return an array structure containing information about all current filters.
     *
     * @param bool $excludeCheckboxFilters Should we exclude checkbox filters from
     * the list (to be used as a complement to getCheckboxFacets()).
     *
     * @return array                       Field, values and translation status
     */
    public function getFilterList($excludeCheckboxFilters = false)
    {
        $filters = parent::getFilterList($excludeCheckboxFilters);
        $label = $this->getFacetLabel('SEARCHMODE');
        if (isset($filters[$label])) {
            foreach (array_keys($filters[$label]) as $i) {
                $filters[$label][$i]['suppressDisplay'] = true;
            }
        }
        return $filters;
    }

    /**
     * Set up limiter based on VuFind settings.
     *
     * @param ParamBag $params Parameter collection to update
     *
     * @return void
     */
    public function createBackendLimiterParameters(ParamBag $params)
    {
        //group limiters with same id together
        $edsLimiters = [];
        foreach ($this->limiters as $limiter) {
            if (isset($limiter) && !empty($limiter)) {
                // split the id/value
                list($key, $value) = explode(':', $limiter, 2);
                $value = SearchRequestModel::escapeSpecialCharacters($value);
                $edsLimiters[$key] = (!isset($edsLimiters[$key]))
                     ? $value : $edsLimiters[$key] . ',' . $value;
            }
        }
        if (!empty($edsLimiters)) {
            foreach ($edsLimiters as $key => $value) {
                $params->add('limiters', $key . ':' . $value);
            }
        }
    }

    /**
     * Set up expanders based on VuFind settings.
     *
     * @param ParamBag $params Parameter collection to update
     *
     * @return void
     */
    public function createBackendExpanderParameters(ParamBag $params)
    {
        // Which filters should be applied to our query?
        if (!empty($this->expanders)) {
            // Loop through all filters and add appropriate values to request:
            $value = '';
            foreach ($this->expanders as $expander) {
                $value = (!empty($value))
                    ? $value . ',' . $expander : $expander;
            }
            if (!empty($value)) {
                $params->add('expander', $value);
            }
        }
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getView()
    {
        $viewArr = explode('|', $this->view);
        return $viewArr[0];
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getEdsView()
    {
        $viewArr = explode('|', $this->view);
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
        // we'll need these to do the proper search using the Summon class:
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
        return isset($this->fullFacetSettings) ? $this->fullFacetSettings : [];
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
        //Also store Limiter/Search Mode IDs/Values in the config file
        $facetId = $field;
        if (substr($field, 0, 6) == 'LIMIT|') {
            $facetId = substr($field, 6);
        }
        if (substr($field, 0, 11) == 'SEARCHMODE|') {
            $facetId = substr($field, 11);
        }
        return isset($this->facetConfig[$facetId])
            ? $this->facetConfig[$facetId] : $facetId;
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
        if (isset($ssLimiters)) {
            foreach ($ssLimiters as $ssLimiter) {
                $this->addCheckboxFacet(
                    $ssLimiter['selectedvalue'], $ssLimiter['description']
                );
            }

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
        if (isset($availableExpanders)) {
            foreach ($availableExpanders as $expander) {
                $this->addCheckboxFacet(
                    $expander['selectedvalue'], $expander['description']
                );
            }

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
                'selected' => ($key == $this->getView() . '|' . $this->getEdsView())
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
        return QueryAdapter::display($this->getQuery(), $translate, $showField);
    }
}