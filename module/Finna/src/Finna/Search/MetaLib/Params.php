<?php
/**
 * MetaLib Search Parameters
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @category VuFind
 * @package  Search_MetaLib
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\MetaLib;
use VuFindSearch\ParamBag;

/**
 * MetaLib Search Parameters
 *
 * @category VuFind
 * @package  Search_MetaLib
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    use \Finna\Search\FinnaParams;

    /**
     * MetaLib Search set IRDs
     *
     * @var array
     */
    protected $irds;

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        parent::initFilters($request);
        if ($set = $request->get('set', '')) {
            $this->removeAllFilters();
            $this->addFilter('metalib_set:' . $request->get('set', ''));
        }
    }

    /**
     * Set search set IRDs
     *
     * @param array $irds IRDs
     *
     * @return void
     */
    public function setIrds($irds)
    {
        $this->irds = $irds;
    }

    /**
     * Get search set IRDs
     *
     * @return array
     */
    public function getIrds()
    {
        return $this->irds;
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
        return $field == 'metalib_set'
            ? 'metalib_set' : 'unrecognized_facet_label';
    }

    /**
     * Create search backend parameters.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        // The "relevance" sort option is a VuFind reserved word; we need to make
        // this null in order to achieve the desired effect with MetaLib:
        $sort = $this->getSort();
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);
        $backendParams->set('filterList', []);
        $backendParams->set('searchSet', $this->getMetalibSearchSet());
        return $backendParams;
    }

    /**
     * Get current MetaLib search set
     *
     * @return string
     */
    public function getMetaLibSearchSet()
    {
        if (!empty($this->filterList['metalib_set'][0])) {
            return $this->filterList['metalib_set'][0];
        }
        return '';
    }
}
