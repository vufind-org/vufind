<?php

/**
 * Factory for Primo Central backends.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use FinnaSearch\Backend\Primo\Connector;

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
class PrimoBackendFactory
    extends \VuFind\Search\Factory\PrimoBackendFactory
{
    /**
     * Create the Primo Central connector.
     *
     * @return Connector
     * @todo   Refactor so that the whole connector doesn't need to be duplicated
     * (instantiate the class separately from initialization or something)
     */
    protected function createConnector()
    {
        // Get the PermissionHandler
        $permHandler = $this->getPermissionHandler();

        // Load url and credentials:
        if (!isset($this->primoConfig->General->url)) {
            throw new \Exception('Missing url in Primo.ini');
        }
        $instCode = isset($permHandler)
            ? $permHandler->getInstCode()
            : null;

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $timeout = isset($this->primoConfig->General->timeout)
            ? $this->primoConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector(
            $this->primoConfig->General->url, $instCode, $client
        );
        $connector->setLogger($this->logger);

        if (isset($this->primoConfig->General->highlighting)
            && $this->primoConfig->General->highlighting
        ) {
            $connector->setHighlighting(true);
        }

        if ($this->primoConfig->HiddenFilters) {
            $connector->setHiddenFilters(
                $this->primoConfig->HiddenFilters->toArray()
            );
        }

        return $connector;
    }
}
