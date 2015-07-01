<?php
/**
 * WorldCatDiscovery Search Parameters
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
 * @package  Search_WorldCatDiscovery
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\WorldCatDiscovery;
use VuFindSearch\ParamBag;

/**
 * WorldCatDiscovery Search Parameters
 *
 * @category VuFind2
 * @package  Search_WorldCatDiscovery
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        $backendParams->set('facets', $this->getBackendFacetParameters());
        $backendParams->set('sortBy', $this->getSort());
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('WorldCatDiscovery');
        $defaultFacetLimit = isset($config->Facet_Settings->facet_limit)
        ? $config->Facet_Settings->facet_limit : 30;

        $finalFacets = [];
        foreach (array_keys($this->getFacetConfig()) as $facetName) {
            $finalFacets[] = "{$facetName}:{$defaultFacetLimit}";
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
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $fq = "{$filt['field']}:{$filt['value']}";
                    $params->add('filters', $fq);
                }
            }
        }
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
        $this->initFacetList('Facets', 'Results_Settings', 'WorldCatDiscovery');
        $this->initFacetList(
            'Advanced_Facets', 'Advanced_Facet_Settings', 'WorldCatDiscovery'
        );
    }
}
