<?php

/**
 * Base class for search backend handler maps.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend;

use VuFindSearch\ParamBag;

/**
 * Base class for search backend handler maps.
 *
 * The handler map maps search functions to parameterizable backend request
 * handlers. The base class implements the parameter preparation method which
 * applies query defaults, appends, and invariants to an existing set of
 * parameters.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractHandlerMap
{
    /**
     * Prepare final set of parameters for search function.
     *
     * Applies the query defaults, appends, and invariants.
     *
     * The concept of defaults, appends, and invariants follows SOLR with
     * regards to the order of the application process: Invariants come last
     * and overwrite runtime parameters, defaults, and appends.
     *
     * @param string   $function Name of search function
     * @param ParamBag $params   Parameters
     *
     * @return void
     */
    public function prepare($function, ParamBag $params)
    {
        $defaults   = $this->getDefaults($function)->getArrayCopy();
        $invariants = $this->getInvariants($function)->getArrayCopy();
        $appends    = $this->getAppends($function)->getArrayCopy();
        $this->apply($params, $defaults, $appends, $invariants);
    }

    /**
     * Return query invariants for search function.
     *
     * @param string $function Name of search function
     *
     * @return ParamBag Query invariants
     */
    abstract public function getInvariants($function);

    /**
     * Return query defaults for search function.
     *
     * @param string $function Name of search function
     *
     * @return ParamBag Query defaults
     */
    abstract public function getDefaults($function);

    /**
     * Return query appends for search function.
     *
     * @param string $function Name of search function
     *
     * @return ParamBag Query appends
     */
    abstract public function getAppends($function);

    /// Internal API

    /**
     * Apply query defaults, appends, invariants.
     *
     * @param ParamBag $params     Parameters
     * @param array    $defaults   Query defaults
     * @param array    $appends    Query appends
     * @param array    $invariants Query invariants
     *
     * @return void
     */
    protected function apply(
        ParamBag $params,
        array $defaults,
        array $appends,
        array $invariants
    ) {
        $final = $params->getArrayCopy();
        $final = array_replace($defaults, $final);
        $final = array_merge_recursive($final, $appends);
        $final = array_replace($final, $invariants);
        $params->exchangeArray($final);
    }
}
