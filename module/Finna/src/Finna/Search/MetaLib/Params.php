<?php
/**
 * MetaLib Search Parameters
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search_MetaLib
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Search\MetaLib;
use VuFindSearch\ParamBag;

/**
 * MetaLib Search Parameters
 *
 * @category VuFind2
 * @package  Search_MetaLib
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    use \Finna\Search\FinnaParams;

    const SPATIAL_DATERANGE_FIELD = null;

    /**
     * MetaLib Search set IRDs
     *
     * @var array
     */
    protected $irds;

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
        $this->metalibSearchSet = $request->get('set');
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
     * Get current MetaLib search set
     *
     * @return string
     */
    public function getMetaLibSearchSet()
    {
        return $this->metalibSearchSet;
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminifyFinnaSearch($minified)
    {
        if (isset($minified->f_mset)) {
            $this->metalibSearchSet = $minified->f_mset;
        }
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
        $backendParams->set('searchSet', $this->metalibSearchSet);
        return $backendParams;
    }
}
