<?php

/**
 * Mix-in for constructing Solr search objects for tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;

/**
 * Mix-in for constructing Solr search objects for tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait SolrSearchObjectTrait
{
    /**
     * Get mock config manager.
     *
     * @return \VuFind\Config\PluginManager
     */
    public function getMockConfigManager(): \VuFind\Config\PluginManager
    {
        $manager = $this->createMock(\VuFind\Config\PluginManager::class);
        $manager->expects($this->any())->method('get')
            ->will($this->returnValue(new \Laminas\Config\Config([])));
        return $manager;
    }

    /**
     * Get Solr options.
     *
     * @return Options
     */
    public function getSolrOptions(): Options
    {
        return new Options(
            $this->getMockConfigManager()
        );
    }

    /**
     * Get Solr params.
     *
     * @param Options $options Solr options to inject (null for default)
     *
     * @return Params
     */
    public function getSolrParams(Options $options = null): Params
    {
        return new Params(
            $options ?? $this->getSolrOptions(),
            $this->getMockConfigManager()
        );
    }

    /**
     * Get Solr results.
     *
     * @param Params $params Solr parameters to inject (null for default)
     *
     * @return Results
     */
    public function getSolrResults(Params $params = null): Results
    {
        return new Results(
            $params ?? $this->getSolrParams(),
            $this->createMock(\VuFindSearch\Service::class),
            $this->createMock(\VuFind\Record\Loader::class)
        );
    }
}
