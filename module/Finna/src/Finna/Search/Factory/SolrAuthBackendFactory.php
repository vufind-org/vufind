<?php

/**
 * Abstract factory for SOLR Auth backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2021.
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
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\Factory;

use Finna\Search\SolrAuth\SolrAuthExtensionsListener;
use VuFindSearch\Backend\Solr\Backend;

/**
 * Abstract factory for SOLR Auth backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrAuthBackendFactory extends \VuFind\Search\Factory\SolrAuthBackendFactory
{
    /**
     * Solr connector class
     *
     * @var string
     */
    protected $connectorClass = \VuFindSearch\Backend\Solr\Connector::class;

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        parent::createListeners($backend);

        $events = $this->serviceLocator->get('SharedEventManager');

        // Finna Solr Auth Extensions
        $solrExtensions = new SolrAuthExtensionsListener(
            $backend->getIdentifier(),
            $this->serviceLocator,
            $this->searchConfig,
            $this->facetConfig
        );
        $solrExtensions->attach($events);
    }
}
