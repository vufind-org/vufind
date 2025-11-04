<?php

/**
 * Factory for Primo Central backends.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\Factory;

use FinnaSearch\Backend\Primo\Connector;
use FinnaSearch\Backend\Primo\RestConnector;

/**
 * Factory for Primo Central backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PrimoBackendFactory extends \VuFind\Search\Factory\PrimoBackendFactory
{
    /**
     * Primo connector class
     *
     * @var string
     */
    protected $connectorClass = Connector::class;

    /**
     * Primo REST API connector class
     *
     * @var string
     */
    protected $restConnectorClass = RestConnector::class;

    /**
     * Create the Primo Central connector.
     *
     * Finna: Add hidden filters and set cache manager
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $connector = parent::createConnector();

        if ($this->primoConfig->HiddenFilters) {
            $connector->setHiddenFilters(
                $this->primoConfig->HiddenFilters->toArray()
            );
        }

        return $connector;
    }

    /**
     * Create the Primo Central REST connector.
     *
     * @return RestConnector
     */
    protected function createRestConnector()
    {
        $connector = parent::createRestConnector();

        if ($this->primoConfig->HiddenFilters) {
            $connector->setHiddenFilters(
                $this->primoConfig->HiddenFilters->toArray()
            );
        }

        return $connector;
    }
}
