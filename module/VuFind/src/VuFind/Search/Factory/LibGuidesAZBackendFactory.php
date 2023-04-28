<?php

/**
 * Factory for LibGuides A-Z Databases backends.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use VuFindSearch\Backend\LibGuidesAZ\Backend;
use VuFindSearch\Backend\LibGuidesAZ\Connector;
use VuFindSearch\Backend\LibGuidesAZ\QueryBuilder;
use VuFindSearch\Backend\LibGuidesAZ\Response\RecordCollectionFactory;

/**
 * Factory for LibGuides A-Z Databases backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LibGuidesAZBackendFactory extends AbstractLibGuidesBackendFactory
{
    /**
     * Return the service name.
     *
     * @return string
     */
    protected function getServiceName()
    {
        return 'LibGuidesAZ';
    }

    /**
     * Instantiate the LibGuides A-Z Databases connector.
     *
     * @param string     $iid     Institution ID
     * @param HttpClient $client  HTTP client
     * @param float      $ver     API version number
     * @param string     $baseUrl API base URL (optional)
     *
     * @return Connector
     */
    protected function createConnectorInstance($iid, $client, $ver, $baseUrl)
    {
        return new Connector($iid, $client, $ver, $baseUrl);
    }

    /**
     * Instantiate the LibGuides A-Z Databases backend.
     *
     * @param Connector                        $connector     LibGuides connector
     * @param RecordCollectionFactoryInterface $factory       Record collection
     * factory (null for default)
     * @param string                           $defaultSearch Default search query
     *
     * @return Backend
     */
    protected function createBackendInstance($connector, $factory, $defaultSearch)
    {
        return new Backend($connector, $factory, $defaultSearch);
    }

    /**
     * Instantiate the LibGuides A-Z Databases record collection factory.
     *
     * @param callback $callback Record factory callback (null for default)
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactoryInstance($callback)
    {
        return new RecordCollectionFactory($callback);
    }

    /**
     * Instantiate the LibGuides A-Z Databases query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilderInstance()
    {
        $builder = new QueryBuilder();
        return $builder;
    }
}
