<?php

/**
 * Trait containing methods for building shared listeners.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Laminas\Config\Config;
use VuFind\Search\Base\HideFacetValueListener;
use VuFindSearch\Backend\BackendInterface;

/**
 * Trait containing methods for building shared listeners.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait SharedListenersTrait
{
    /**
     * Get a hide facet value listener for the backend
     *
     * @param BackendInterface $backend Search backend
     * @param Config           $facet   Configuration of facets
     *
     * @return mixed null|HideFacetValueListener
     */
    protected function getHideFacetValueListener(
        BackendInterface $backend,
        Config $facet
    ) {
        $hideFacetValue = isset($facet->HideFacetValue)
            ? $facet->HideFacetValue->toArray() : [];
        $showFacetValue = isset($facet->ShowFacetValue)
            ? $facet->ShowFacetValue->toArray() : [];
        if (empty($hideFacetValue) && empty($showFacetValue)) {
            return null;
        }
        return new HideFacetValueListener(
            $backend,
            $hideFacetValue,
            $showFacetValue
        );
    }
}
